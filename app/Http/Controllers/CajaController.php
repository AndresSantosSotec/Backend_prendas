<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CajaAperturaCierre;
use App\Models\MovimientoCaja;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CajaController extends Controller
{
    /**
     * Obtener historial de aperturas y cierres (para el mes actual o filtro)
     */
    public function index(Request $request)
    {
        $query = CajaAperturaCierre::with('user', 'sucursal')
            ->orderBy('fecha_apertura', 'desc');

        // Si no es admin, solo ver las propias? O depediendo de permisos.
        // Por ahora filtro por usuario si no se especifica lo contrario o si es un rol bajo
        // (Asumimos lógica simplificada, ajustar según RBAC real)
        if ($request->has('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        // Filtro por mes actual por defecto si no hay fechas
        if (!$request->has('fecha_inicio')) {
            $query->whereMonth('fecha_apertura', Carbon::now()->month)
                  ->whereYear('fecha_apertura', Carbon::now()->year);
        }

        $cajas = $query->paginate(10);
        return response()->json($cajas);
    }

    /**
     * Verificar estado de caja del usuario actual
     * IMPORTANTE: Busca cualquier caja abierta, no solo la del día actual
     */
    public function checkEstado(Request $request)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'No autenticado'], 401);

        // Buscar CUALQUIER caja abierta del usuario (sin importar la fecha)
        $cajaAbierta = CajaAperturaCierre::where('user_id', $user->id)
            ->where('estado', 'abierta')
            ->with(['sucursal', 'user'])
            ->orderBy('fecha_apertura', 'desc')
            ->first();

        if ($cajaAbierta) {
            // Calcular movimientos actuales para devolver "total esperado"
            $totalMovimientos = MovimientoCaja::where('caja_id', $cajaAbierta->id)
                ->where('estado', 'aplicado')
                ->selectRaw("SUM(CASE WHEN tipo IN ('incremento', 'ingreso_pago') THEN monto ELSE -monto END) as total")
                ->value('total');

            $totalEsperado = $cajaAbierta->saldo_inicial + ($totalMovimientos ?? 0);
            $cajaAbierta->total_esperado_sistema = $totalEsperado;

            // Verificar si la caja es de un día anterior (pendiente de cierre)
            $esDeOtroDia = !Carbon::parse($cajaAbierta->fecha_apertura)->isToday();

            return response()->json([
                'estado' => 'abierta',
                'caja' => $cajaAbierta,
                'requiere_cierre' => $esDeOtroDia,
                'mensaje' => $esDeOtroDia
                    ? 'Tienes una caja pendiente de cierre del ' . Carbon::parse($cajaAbierta->fecha_apertura)->format('d/m/Y')
                    : null
            ]);
        }

        return response()->json([
            'estado' => 'cerrada',
            'mensaje' => 'No hay caja abierta. Puedes aperturar una nueva caja.'
        ]);
    }

    /**
     * Abrir caja
     * NO se puede abrir si hay una caja abierta pendiente (aunque sea de otro día)
     */
    public function abrir(Request $request)
    {
        $request->validate([
            'saldo_inicial' => 'required|numeric|min:0',
            'fecha_apertura' => 'required|date'
        ]);

        $user = Auth::user();
        $fecha = $request->fecha_apertura ?? Carbon::now()->toDateString();

        // PRIMERO: Verificar si hay alguna caja abierta pendiente (de cualquier fecha)
        $cajaPendiente = CajaAperturaCierre::where('user_id', $user->id)
            ->where('estado', 'abierta')
            ->first();

        if ($cajaPendiente) {
            // Si la caja pendiente es de hoy, retornarla para continuar
            if (Carbon::parse($cajaPendiente->fecha_apertura)->isToday()) {
                $totalMovimientos = MovimientoCaja::where('caja_id', $cajaPendiente->id)
                    ->where('estado', 'aplicado')
                    ->selectRaw("SUM(CASE WHEN tipo IN ('incremento', 'ingreso_pago') THEN monto ELSE -monto END) as total")
                    ->value('total');

                $cajaPendiente->total_esperado_sistema = $cajaPendiente->saldo_inicial + ($totalMovimientos ?? 0);
                $cajaPendiente->load(['sucursal', 'user']);

                return response()->json([
                    'message' => 'Ya tienes una caja abierta para hoy. Continuando con la caja existente.',
                    'caja' => $cajaPendiente,
                    'ya_existia' => true
                ]);
            }

            // Si es de otro día, NO permitir apertura hasta cerrar
            return response()->json([
                'error' => 'No puedes abrir una nueva caja. Tienes una caja pendiente de cierre del ' .
                    Carbon::parse($cajaPendiente->fecha_apertura)->format('d/m/Y') .
                    '. Debes cerrarla primero.',
                'caja_pendiente' => true,
                'caja' => $cajaPendiente
            ], 400);
        }

        // Verificar si ya hay una caja cerrada para hoy
        // Los administradores pueden saltarse esta restricción si necesitan reabrir o abrir una nueva
        if ($user->rol !== 'administrador') {
            $cajaCerradaHoy = CajaAperturaCierre::where('user_id', $user->id)
                ->whereDate('fecha_apertura', $fecha)
                ->where('estado', 'cerrada')
                ->first();

            if ($cajaCerradaHoy) {
                return response()->json([
                    'error' => 'Ya cerraste tu caja hoy. No puedes abrir otra caja el mismo día.',
                    'caja_cerrada' => true
                ], 400);
            }
        }

        // Crear nueva apertura
        $caja = new CajaAperturaCierre();
        $caja->user_id = $user->id;
        $caja->sucursal_id = $user->sucursal_id ?? 1;
        $caja->fecha_apertura = $fecha;
        $caja->hora_apertura = Carbon::now()->toTimeString();
        $caja->saldo_inicial = $request->saldo_inicial;
        $caja->estado = 'abierta';
        $caja->save();

        $caja->load(['sucursal', 'user']);
        $caja->total_esperado_sistema = $caja->saldo_inicial;

        return response()->json(['message' => 'Caja aperturada correctamente', 'caja' => $caja]);
    }

    /**
     * Cerrar caja
     */
    public function cerrar(Request $request, $id)
    {
        $request->validate([
            'saldo_final_real' => 'required|numeric|min:0', // Lo que contó el usuario (montoContado)
            'detalles_arqueo' => 'nullable|json' // Desglose de billetes
        ]);

        $caja = CajaAperturaCierre::findOrFail($id);
        $user = Auth::user();

        // Verificar propiedad o rol administrador
        if ($caja->user_id !== $user->id && $user->rol !== 'administrador') {
            return response()->json(['error' => 'No tienes permiso para cerrar esta caja.'], 403);
        }

        if ($caja->estado === 'cerrada') {
            return response()->json(['error' => 'La caja ya está cerrada.'], 400);
        }

        // Calcular saldo esperado del sistema
        $totalMovimientos = MovimientoCaja::where('caja_id', $caja->id)
            ->where('estado', 'aplicado')
            ->selectRaw("SUM(CASE WHEN tipo IN ('incremento', 'ingreso_pago') THEN monto ELSE -monto END) as total")
            ->value('total');

        $saldoSistema = $caja->saldo_inicial + ($totalMovimientos ?? 0);

        $saldoReal = $request->saldo_final_real;
        $diferencia = $saldoReal - $saldoSistema;

        $resultado = 'Cuadra perfectamente';
        if ($diferencia > 0) $resultado = 'Sobrante';
        if ($diferencia < 0) $resultado = 'Faltante';

        $caja->saldo_final = $saldoReal;
        $caja->fecha_cierre = Carbon::now();
        $caja->diferencia = $diferencia;
        $caja->resultado_arqueo = $resultado;
        $caja->detalles_arqueo = $request->detalles_arqueo;
        $caja->estado = 'cerrada';
        $caja->save();

        return response()->json(['message' => 'Caja cerrada correctamente', 'caja' => $caja]);
    }

    /**
     * Registrar movimiento manual (Incremento/Decremento)
     */
    public function registrarMovimiento(Request $request)
    {
        try {
            $request->validate([
                'caja_id' => 'required|exists:caja_apertura_cierres,id',
                'tipo' => 'required|in:incremento,decremento',
                'monto' => 'required|numeric|min:0.01',
                'concepto' => 'required|string',
                'detalles' => 'nullable|json'
            ]);

            // Verificar que la caja esté abierta
            $caja = CajaAperturaCierre::find($request->caja_id);
            if (!$caja) {
                return response()->json(['error' => 'Caja no encontrada'], 404);
            }

            if ($caja->estado !== 'abierta') {
                return response()->json(['error' => 'La caja está cerrada. No se pueden registrar movimientos.'], 400);
            }

            // Verificar que la caja pertenece al usuario actual (o es administrador)
            $user = Auth::user();
            if ($caja->user_id !== $user->id && $user->rol !== 'administrador') {
                return response()->json(['error' => 'No tienes permiso para registrar movimientos en esta caja.'], 403);
            }

            $movimiento = new MovimientoCaja();
            $movimiento->caja_id = $request->caja_id;
            $movimiento->tipo = $request->tipo;
            $movimiento->monto = $request->monto;
            $movimiento->concepto = $request->concepto;
            $movimiento->detalles_movimiento = $request->detalles;
            $movimiento->user_id = Auth::id();
            $movimiento->estado = 'aplicado';
            $movimiento->save();

            // Cargar relaciones para la respuesta
            $movimiento->load('user');

            return response()->json([
                'message' => 'Movimiento registrado correctamente',
                'movimiento' => $movimiento
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error' => 'Error de validación',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error registrando movimiento de caja: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error interno al registrar el movimiento'
            ], 500);
        }
    }

    /**
     * Obtener movimientos de una caja
     */
    public function getMovimientos($id)
    {
       $movimientos = MovimientoCaja::where('caja_id', $id)
           ->with('user')
           ->orderBy('created_at', 'desc')
           ->get();

       return response()->json($movimientos);
    }
}
