<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\CajaAperturaCierre;
use App\Models\MovimientoCaja;
use App\Models\BovedaMovimiento;
use App\Http\Requests\CloseCashRegisterRequest;
use App\Http\Resources\CashRegisterClosureResource;
use App\Models\Boveda;
use App\Models\ConfiguracionSistema;
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
                'estado'                         => 'abierta',
                'caja'                           => $cajaAbierta,
                'requiere_cierre'                => $esDeOtroDia,
                'cash_vault_integration_enabled' => ConfiguracionSistema::integracionCajaBovedaActiva(),
                'mensaje'                        => $esDeOtroDia
                    ? 'Tienes una caja pendiente de cierre del ' . Carbon::parse($cajaAbierta->fecha_apertura)->format('d/m/Y')
                    : null,
            ]);
        }

        return response()->json([
            'estado'                         => 'cerrada',
            'cash_vault_integration_enabled' => ConfiguracionSistema::integracionCajaBovedaActiva(),
            'mensaje'                        => 'No hay caja abierta. Puedes aperturar una nueva caja.',
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
     * En modo integrado: se requiere bóveda origen y se descuenta el saldo inicial de ella.
     */
    public function abrir(Request $request)
    {
        $integracionActiva = ConfiguracionSistema::integracionCajaBovedaActiva();

        // Reglas de validación dinámicas según modo
        $rules = [
            'saldo_inicial'  => 'required|numeric|min:0',
            'fecha_apertura' => 'required|date',
        ];

        // Si la integración está activa, boveda_origen_id es obligatoria
        if ($integracionActiva) {
            $rules['boveda_origen_id'] = 'required|integer|exists:bovedas,id';
        }

        $request->validate($rules);

        $user = Auth::user();

        // LOG: contexto de la solicitud para facilitar debugging
        Log::info('Intento de apertura de caja', [
            'user_id'            => $user->id,
            'user_email'         => $user->email,
            'user_rol'           => $user->rol,
            'fecha_solicitada'   => $request->fecha_apertura,
            'saldo_inicial'      => $request->saldo_inicial,
            'boveda_origen_id'   => $request->boveda_origen_id,
            'integracion_activa' => $integracionActiva,
            'permisos_caja'      => $user->permissions()->where('modulo', 'caja')->pluck('accion')->toArray(),
        ]);

        if (!$user->hasPermission('caja', 'abrir')) {
            Log::warning('Apertura de caja rechazada: sin permiso caja.abrir', [
                'user_id'  => $user->id,
                'user_rol' => $user->rol,
                'permisos' => $user->permissions()->where('modulo', 'caja')->pluck('accion')->toArray(),
            ]);
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
                    'message'    => 'Ya tienes una caja abierta para hoy. Continuando con la caja existente.',
                    'caja'       => $cajaPendiente,
                    'ya_existia' => true,
                ]);
            }

            // Si es de otro día, NO permitir apertura hasta cerrar
            Log::warning('Apertura de caja rechazada: caja pendiente de cierre de otro día', [
                'user_id'           => $user->id,
                'user_rol'          => $user->rol,
                'caja_pendiente_id' => $cajaPendiente->id,
                'fecha_pendiente'   => $cajaPendiente->fecha_apertura,
            ]);
            return response()->json([
                'error'          => 'No puedes abrir una nueva caja. Tienes una caja pendiente de cierre del ' .
                    Carbon::parse($cajaPendiente->fecha_apertura)->format('d/m/Y') .
                    '. Debes cerrarla primero.',
                'caja_pendiente' => true,
                'caja'           => $cajaPendiente,
            ], 400);
        }

        // ── MODO INTEGRADO: validar y debitar bóveda ──────────────────────────
        if ($integracionActiva) {
            $boveda = Boveda::activas()->findOrFail($request->boveda_origen_id);

            if (!$boveda->puedeRetirarMonto($request->saldo_inicial)) {
                return response()->json([
                    'error' => 'La bóveda seleccionada no tiene fondos suficientes. ' .
                        'Saldo disponible: ' . $boveda->saldo_actual,
                ], 422);
            }

            try {
                return DB::transaction(function () use ($user, $fecha, $request, $boveda) {
                    // 1. Crear apertura de caja
                    $caja = CajaAperturaCierre::create([
                        'user_id'          => $user->id,
                        'sucursal_id'      => $user->sucursal_id ?? 1,
                        'fecha_apertura'   => $fecha,
                        'hora_apertura'    => Carbon::now()->toTimeString(),
                        'saldo_inicial'    => $request->saldo_inicial,
                        'estado'           => 'abierta',
                        'boveda_origen_id' => $boveda->id,
                    ]);

                    // 2. Crear movimiento de salida en bóveda
                    $movBoveda = BovedaMovimiento::create([
                        'boveda_id'             => $boveda->id,
                        'usuario_id'            => $user->id,
                        'sucursal_id'           => $boveda->sucursal_id,
                        'tipo_movimiento'       => 'salida',
                        'monto'                 => $request->saldo_inicial,
                        'concepto'              => 'Apertura de caja #' . $caja->id . ' — saldo inicial',
                        'referencia'            => 'apertura_caja:' . $caja->id,
                        'estado'                => 'aprobado',
                        'aprobado_por'          => $user->id,
                        'fecha_aprobacion'      => now(),
                    ]);

                    // 3. Registrar ID del movimiento de bóveda en la caja
                    $caja->boveda_movimiento_apertura_id = $movBoveda->id;
                    $caja->save();

                    // 4. Actualizar saldo de bóveda
                    $boveda->actualizarSaldo();

                    $caja->load(['sucursal', 'user', 'bovedaOrigen']);
                    $caja->total_esperado_sistema = $caja->saldo_inicial;

                    return response()->json([
                        'message'          => 'Caja aperturada correctamente. Saldo debitado de bóveda.',
                        'caja'             => $caja,
                        'boveda_movimiento' => $movBoveda,
                    ]);
                });
            } catch (\Exception $e) {
                Log::error('Error en apertura de caja integrada: ' . $e->getMessage());
                return response()->json(['error' => 'Error al aperturar la caja: ' . $e->getMessage()], 500);
            }
        }

        // ── MODO DESCONECTADO: apertura normal ────────────────────────────────
        // Nota: se permite abrir nueva caja aunque ya se haya cerrado una el mismo día.
        $caja = new CajaAperturaCierre();
        $caja->user_id      = $user->id;
        $caja->sucursal_id  = $user->sucursal_id ?? 1;
        $caja->fecha_apertura = $fecha;
        $caja->hora_apertura  = Carbon::now()->toTimeString();
        $caja->saldo_inicial  = $request->saldo_inicial;
        $caja->estado         = 'abierta';
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
     *
     * En modo integrado:
     *  - Incremento: crea un movimiento en bóveda tipo egreso con estado 'pendiente_aprobacion'.
     *  - Decremento: crea un movimiento en bóveda tipo ingreso con estado 'pendiente_aprobacion'.
     *  El movimiento en caja queda en estado 'pendiente_boveda' hasta que un administrador de bóveda lo aprueba.
     *
     * En modo desconectado (actual):
     *  - Comportamiento idéntico al original: movimiento de caja en estado 'aplicado'.
     */
    public function registrarMovimiento(Request $request)
    {
        try {
            $integracionActiva = ConfiguracionSistema::integracionCajaBovedaActiva();

            $rules = [
                'caja_id'  => 'required|exists:caja_apertura_cierres,id',
                'tipo'     => 'required|in:incremento,decremento',
                'monto'    => 'required|numeric|min:0.01',
                'concepto' => 'required|string',
                'detalles' => 'nullable|json',
            ];

            // Si la integración está activa, boveda_id es obligatoria
            if ($integracionActiva) {
                $rules['boveda_id'] = 'required|integer|exists:bovedas,id';
            }

            $request->validate($rules);

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
            }

            // ── MODO INTEGRADO ─────────────────────────────────────────────────────
            if ($integracionActiva) {
                $boveda = Boveda::activas()->findOrFail($request->boveda_id);

                return DB::transaction(function () use ($user, $request, $caja, $boveda) {
                    if ($request->tipo === 'incremento') {
                        // Incremento: bóveda → caja (requiere aprobación del encargado de bóveda)
                        // 1. Crear movimiento de caja en estado pendiente
                        $movCaja = MovimientoCaja::create([
                            'caja_id'      => $caja->id,
                            'tipo'         => 'incremento',
                            'monto'        => $request->monto,
                            'concepto'     => $request->concepto,
                            'detalles_movimiento' => $request->detalles,
                            'user_id'      => $user->id,
                            'estado'       => 'pendiente',
                            'boveda_id'    => $boveda->id,
                            'estado_boveda' => 'pendiente_aprobacion',
                        ]);

                        // 2. Crear movimiento de bóveda en estado pendiente (salida)
                        $movBoveda = BovedaMovimiento::create([
                            'boveda_id'       => $boveda->id,
                            'usuario_id'      => $user->id,
                            'sucursal_id'     => $boveda->sucursal_id,
                            'tipo_movimiento' => 'salida',
                            'monto'           => $request->monto,
                            'concepto'        => 'Incremento de caja #' . $caja->id . ' — ' . $request->concepto,
                            'referencia'      => 'incremento_caja:' . $movCaja->id,
                            'estado'          => 'pendiente',
                        ]);

                        // 3. Enlazar movimiento de bóveda al movimiento de caja
                        $movCaja->boveda_movimiento_id = $movBoveda->id;
                        $movCaja->save();

                        $movCaja->load('user', 'boveda');

                        return response()->json([
                            'message'             => 'Solicitud de incremento enviada. Pendiente de aprobación del encargado de bóveda.',
                            'movimiento'          => $movCaja,
                            'boveda_movimiento'   => $movBoveda,
                            'requiere_aprobacion' => true,
                        ], 201);

                    } else {
                        // Decremento: caja → bóveda (se aplica inmediatamente)
                        // Verificar que la bóveda puede recibir el monto
                        if (!$boveda->puedeRecibirMonto($request->monto)) {
                            return response()->json([
                                'error' => 'La bóveda destino no puede recibir ese monto (supera saldo máximo permitido).',
                            ], 422);
                        }

                        // 1. Crear movimiento de caja aplicado
                        $movCaja = MovimientoCaja::create([
                            'caja_id'      => $caja->id,
                            'tipo'         => 'decremento',
                            'monto'        => $request->monto,
                            'concepto'     => $request->concepto,
                            'detalles_movimiento' => $request->detalles,
                            'user_id'      => $user->id,
                            'estado'       => 'aplicado',
                            'boveda_id'    => $boveda->id,
                            'estado_boveda' => 'aprobado',
                            'fecha_aprobacion_boveda' => now(),
                            'aprobado_por_id' => $user->id,
                        ]);

                        // 2. Crear movimiento de bóveda aprobado (entrada desde caja)
                        $movBoveda = BovedaMovimiento::create([
                            'boveda_id'       => $boveda->id,
                            'usuario_id'      => $user->id,
                            'sucursal_id'     => $boveda->sucursal_id,
                            'tipo_movimiento' => 'entrada',
                            'monto'           => $request->monto,
                            'concepto'        => 'Decremento de caja #' . $caja->id . ' — ' . $request->concepto,
                            'referencia'      => 'decremento_caja:' . $movCaja->id,
                            'estado'          => 'aprobado',
                            'aprobado_por'    => $user->id,
                            'fecha_aprobacion' => now(),
                        ]);

                        // 3. Enlazar y actualizar saldo de bóveda
                        $movCaja->boveda_movimiento_id = $movBoveda->id;
                        $movCaja->save();
                        $boveda->actualizarSaldo();

                        $movCaja->load('user', 'boveda');

                        return response()->json([
                            'message'           => 'Decremento registrado. Fondos transferidos a bóveda.',
                            'movimiento'        => $movCaja,
                            'boveda_movimiento' => $movBoveda,
                        ]);
                    }
                });
            }

            // ── MODO DESCONECTADO: movimiento simple ───────────────────────────────
            $movimiento = new MovimientoCaja();
            $movimiento->caja_id             = $request->caja_id;
            $movimiento->tipo                = $request->tipo;
            $movimiento->monto               = $request->monto;
            $movimiento->concepto            = $request->concepto;
            $movimiento->detalles_movimiento = $request->detalles;
            $movimiento->user_id             = Auth::id();
            $movimiento->estado              = 'aplicado';
            $movimiento->save();

            $movimiento->load('user');

            return response()->json([
                'message'    => 'Movimiento registrado correctamente',
                'movimiento' => $movimiento,
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'error'  => 'Error de validación',
                'errors' => $e->errors(),
            ], 422);
        } catch (\Exception $e) {
            Log::error('Error registrando movimiento de caja: ' . $e->getMessage());
            return response()->json([
                'error' => 'Error interno al registrar el movimiento',
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
