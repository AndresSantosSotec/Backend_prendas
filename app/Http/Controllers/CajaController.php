<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CajaAperturaCierre;
use App\Models\MovimientoCaja;
use App\Http\Requests\CloseCashRegisterRequest;
use App\Http\Resources\CashRegisterClosureResource;
use App\Models\Boveda;
use App\Services\CashRegisterClosureService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class CajaController extends Controller
{
    protected CashRegisterClosureService $closureService;

    public function __construct(CashRegisterClosureService $closureService)
    {
        $this->closureService = $closureService;
    }
    /**
     * Obtener historial de aperturas y cierres (para el mes actual o filtro)
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        if (!$user->hasPermission('caja', 'ver_movimientos')) {
            return response()->json(['error' => 'No tienes permiso para ver movimientos de caja.'], 403);
        }

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
     * Obtener TODAS las cajas abiertas (Solo admin/superadmin)
     * Permite al admin ver y gestionar cajas de otros usuarios
     * GET /cajas/todas-abiertas
     */
    public function todasAbiertas(Request $request)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'No autenticado'], 401);

        if (!$user->hasPermission('caja', 'gestionar_cajas')) {
            return response()->json(['error' => 'No tienes permiso para ver las cajas de otros usuarios.'], 403);
        }

        $cajas = CajaAperturaCierre::where('estado', 'abierta')
            ->with(['sucursal', 'user'])
            ->orderBy('fecha_apertura', 'desc')
            ->get();

        // Calcular total esperado para cada caja
        $cajas->each(function ($caja) {
            $totalMovimientos = MovimientoCaja::where('caja_id', $caja->id)
                ->where('estado', 'aplicado')
                ->selectRaw("SUM(CASE WHEN tipo IN ('incremento', 'ingreso_pago') THEN monto ELSE -monto END) as total")
                ->value('total');

            $caja->total_esperado_sistema = $caja->saldo_inicial + ($totalMovimientos ?? 0);
            $caja->es_de_otro_dia = !Carbon::parse($caja->fecha_apertura)->isToday();
        });

        return response()->json([
            'success' => true,
            'data' => $cajas,
            'total' => $cajas->count()
        ]);
    }

    /**
     * Ver detalle de una caja específica (admin puede ver cualquiera)
     * GET /cajas/{id}/detalle
     */
    public function detalleCaja(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user) return response()->json(['error' => 'No autenticado'], 401);

        $caja = CajaAperturaCierre::with(['sucursal', 'user'])->findOrFail($id);

        // Solo el dueño o admin/superadmin pueden ver
        if ($caja->user_id !== $user->id && !$user->hasPermission('caja', 'gestionar_cajas')) {
            return response()->json(['error' => 'No tienes permiso para ver esta caja.'], 403);
        }

        // Calcular total esperado
        $totalMovimientos = MovimientoCaja::where('caja_id', $caja->id)
            ->where('estado', 'aplicado')
            ->selectRaw("SUM(CASE WHEN tipo IN ('incremento', 'ingreso_pago') THEN monto ELSE -monto END) as total")
            ->value('total');

        $caja->total_esperado_sistema = $caja->saldo_inicial + ($totalMovimientos ?? 0);
        $caja->es_de_otro_dia = !Carbon::parse($caja->fecha_apertura)->isToday();

        // Obtener movimientos
        $movimientos = MovimientoCaja::where('caja_id', $caja->id)
            ->with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'caja' => $caja,
            'movimientos' => $movimientos,
            'es_propia' => $caja->user_id === $user->id,
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
        if (!$user->hasPermission('caja', 'abrir')) {
            return response()->json(['error' => 'No tienes permiso para abrir caja.'], 403);
        }

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
        // Los administradores y superadmin pueden saltarse esta restricción
        if (!in_array($user->rol, ['superadmin', 'administrador'])) {
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
     * Cerrar caja (transfiere a la bóveda seleccionada o automáticamente si solo hay una)
     */
    public function cerrar(Request $request, $id)
    {
        $request->validate([
            'saldo_final_real' => 'required|numeric|min:0',
            'detalles_arqueo' => 'nullable|json',
            'boveda_destino_id' => 'nullable|integer|exists:bovedas,id',
        ]);

        $caja = CajaAperturaCierre::findOrFail($id);
        $user = Auth::user();

        // Verificar propiedad o rol administrador/superadmin
        if (!$user->hasPermission('caja', 'cerrar')) {
            return response()->json(['error' => 'No tienes permiso para cerrar caja.'], 403);
        }

        if ($caja->user_id !== $user->id && !$user->hasPermission('caja', 'gestionar_cajas')) {
            return response()->json(['error' => 'No tienes permiso para cerrar esta caja.'], 403);
        }

        if ($caja->estado === 'cerrada') {
            return response()->json(['error' => 'La caja ya está cerrada.'], 400);
        }

        // Determinar bóveda destino
        $boveda = null;
        if ($request->boveda_destino_id) {
            // El usuario eligió explícitamente una bóveda
            $boveda = Boveda::activas()->findOrFail($request->boveda_destino_id);
        } else {
            // Auto-seleccionar: buscar bóvedas activas de la sucursal de la caja
            $bovedasDisponibles = Boveda::activas()->deSucursal($caja->sucursal_id)->get();
            if ($bovedasDisponibles->count() === 1) {
                $boveda = $bovedasDisponibles->first();
            }
            // Si hay 0 o más de 1, no se transfiere automáticamente
        }

        $desglose = null;
        if ($request->detalles_arqueo) {
            $decoded = json_decode($request->detalles_arqueo, true);
            $desglose = is_array($decoded) ? $decoded : null;
        }

        // Usar el servicio de cierre que ya maneja la transferencia a bóveda
        [$cajaCerrada, $movimientoBoveda] = $this->closureService->closeAndTransferToVault($caja, [
            'monto_total_efectivo' => $request->saldo_final_real,
            'desglose_denominaciones' => $desglose,
            'enviar_a_boveda' => $boveda !== null,
            'boveda_destino_id' => $boveda?->id,
            'cajero_id' => $caja->user_id,
            'observaciones' => null,
        ]);

        $mensaje = 'Caja cerrada correctamente';
        if ($movimientoBoveda) {
            $mensaje .= '. Saldo transferido a bóveda automáticamente.';
        }

        return response()->json([
            'message' => $mensaje,
            'caja' => $cajaCerrada,
            'boveda_movimiento' => $movimientoBoveda,
        ]);
    }

    /**
     * Cerrar caja con opción de transferir el saldo final a una bóveda destino.
     *
     * Endpoint REST nuevo orientado a React:
     *  POST /api/v1/cash-registers/{id}/close
     */
    public function closeWithVault(CloseCashRegisterRequest $request, $id)
    {
        $caja = CajaAperturaCierre::findOrFail($id);
        $user = Auth::user();

        // Reutilizar la misma regla de permisos que el cierre clásico:
        // solo el dueño de la caja o un admin/superadmin pueden cerrarla
        if (!$user->hasPermission('caja', 'cerrar')) {
            return response()->json(['error' => 'No tienes permiso para cerrar caja.'], 403);
        }

        if ($caja->user_id !== $user->id && !$user->hasPermission('caja', 'gestionar_cajas')) {
            return response()->json(['error' => 'No tienes permiso para cerrar esta caja.'], 403);
        }

        if ($caja->estado === 'cerrada') {
            return response()->json(['error' => 'La caja ya está cerrada.'], 400);
        }

        // Ejecutar lógica de cierre + (opcional) transferencia a bóveda en una transacción
        [$cajaCerrada, $movimientoBoveda] = $this->closureService->closeAndTransferToVault($caja, [
            'monto_total_efectivo' => $request->input('monto_total_efectivo'),
            'desglose_denominaciones' => $request->input('desglose_denominaciones', []),
            'enviar_a_boveda' => $request->boolean('enviar_a_boveda'),
            'boveda_destino_id' => $request->input('boveda_destino_id'),
            'cajero_id' => $request->input('cajero_id') ?? $caja->user_id,
            'observaciones' => $request->input('observaciones'),
        ]);

        // Respuesta normalizada vía Resource para que el frontend tenga estructura estable
        return new CashRegisterClosureResource([
            'caja' => $cajaCerrada,
            'boveda_movimiento' => $movimientoBoveda,
        ]);
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

            // Admin/superadmin pueden registrar movimientos en cualquier caja abierta
            $user = Auth::user();
            if (!$user->hasPermission('caja', 'ver_movimientos')) {
                return response()->json(['error' => 'No tienes permiso para registrar movimientos en caja.'], 403);
            }

            if ($caja->user_id !== $user->id) {
                if (!$user->hasPermission('caja', 'gestionar_cajas')) {
                    return response()->json(['error' => 'No tienes permiso para registrar movimientos en esta caja.'], 403);
                }
                // Admin/superadmin: se permite registrar directamente en la caja indicada
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
     * Admin/superadmin pueden ver movimientos de cualquier caja
     */
    public function getMovimientos($id)
    {
        $user = Auth::user();
        $caja = CajaAperturaCierre::findOrFail($id);

        // Solo el dueño o admin/superadmin pueden ver los movimientos
        if ($caja->user_id !== $user->id && !$user->hasPermission('caja', 'gestionar_cajas')) {
            return response()->json(['error' => 'No tienes permiso para ver estos movimientos.'], 403);
        }

       $movimientos = MovimientoCaja::where('caja_id', $id)
           ->with('user')
           ->orderBy('created_at', 'desc')
           ->get();

       return response()->json($movimientos);
    }
}
