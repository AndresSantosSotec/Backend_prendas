<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Boveda;
use App\Models\BovedaMovimiento;
use App\Models\MovimientoCaja;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\BovedaDetalle;
use App\Exports\BovedasExport;
use App\Exports\BovedaMovimientosExport;
use App\Exports\BovedaConsolidacionExport;
use Maatwebsite\Excel\Facades\Excel;
use Barryvdh\DomPDF\Facade\Pdf;

class BovedaController extends Controller
{
    /**
     * Obtener lista de bóvedas con filtros
     */
    public function index(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();
        $query = Boveda::with(['sucursal', 'responsable']);

        if (!$user->hasPermission('boveda', 'ver')) {
            return response()->json(['error' => 'No tienes permisos para ver bÃ³vedas'], 403);
        }

        // Filtros por permisos
        if (!in_array($user->rol, ['superadmin', 'administrador'])) {
            // Usuarios normales solo ven bóvedas de su sucursal
            $query->where('sucursal_id', $user->sucursal_id);
        }

        // Filtros adicionales
        if ($request->sucursal_id) {
            $query->where('sucursal_id', $request->sucursal_id);
        }

        if ($request->has('activa')) {
            $query->where('activa', filter_var($request->activa, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->tipo) {
            $query->where('tipo', $request->tipo);
        }

        $bovedas = $query->orderBy('sucursal_id')->orderBy('codigo')->get();

        return response()->json([
            'bovedas' => $bovedas,
            'total' => $bovedas->count(),
        ]);
    }

    /**
     * Crear nueva bóveda
     */
    public function store(Request $request)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:500',
            'sucursal_id' => 'required|exists:sucursales,id',
            'saldo_inicial' => 'required|numeric|min:0',
            'tipo' => 'required|in:general,principal,auxiliar',
            'requiere_aprobacion' => 'boolean',
            'responsable_id' => 'nullable|exists:users,id',
            'desglose_denominaciones' => 'nullable|array',
        ]);

        /** @var User $user */
        $user = Auth::user();

        // Verificar permisos para crear bóveda
        if (!$user->hasPermission('boveda', 'crear')) {
            return response()->json(['error' => 'No tienes permisos para crear bóvedas'], 403);
        }

        // Solo admin puede crear bóvedas en otras sucursales
        if (!in_array($user->rol, ['superadmin', 'administrador']) && $request->sucursal_id != $user->sucursal_id) {
            return response()->json(['error' => 'Solo puedes crear bóvedas en tu sucursal'], 403);
        }

        try {
            DB::beginTransaction();

            $boveda = Boveda::create([
                'codigo' => Boveda::generarCodigo($request->sucursal_id),
                'nombre' => $request->nombre,
                'descripcion' => $request->descripcion,
                'sucursal_id' => $request->sucursal_id,
                'saldo_actual' => $request->saldo_inicial,
                'saldo_minimo' => $request->saldo_minimo ?? 0,
                'saldo_maximo' => $request->saldo_maximo,
                'tipo' => $request->tipo,
                'activa' => true,
                'requiere_aprobacion' => $request->requiere_aprobacion ?? false,
                'responsable_id' => $request->responsable_id,
                'creado_por' => $user->id,
            ]);

            // Si hay saldo inicial, registrar movimiento de apertura
            if ($request->saldo_inicial > 0) {
                $movimiento = BovedaMovimiento::create([
                    'boveda_id' => $boveda->id,
                    'usuario_id' => $user->id,
                    'sucursal_id' => $request->sucursal_id,
                    'tipo_movimiento' => 'entrada',
                    'monto' => $request->saldo_inicial,
                    'concepto' => 'Apertura de bóveda con saldo inicial',
                    'desglose_denominaciones' => $request->desglose_denominaciones,
                    'estado' => 'aprobado',
                    'aprobado_por' => $user->id,
                    'fecha_aprobacion' => now(),
                ]);

                // Guardar detalles de denominaciones normalizados
                if ($request->desglose_denominaciones && is_array($request->desglose_denominaciones)) {
                    BovedaDetalle::crearDesdeDesglose($movimiento->id, $request->desglose_denominaciones);
                }

                $boveda->ultima_apertura = now();
                $boveda->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Bóveda creada correctamente',
                'boveda' => $boveda->load(['sucursal', 'responsable'])
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => 'Error al crear bóveda: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtener detalles de una bóveda
     */
    public function show($id)
    {
        /** @var User $user */
        $user = Auth::user();
        $boveda = Boveda::with(['sucursal', 'responsable', 'movimientosAprobados' => function($query) {
            $query->orderBy('created_at', 'desc')->limit(50);
        }])->findOrFail($id);

        if (!$user->hasPermission('boveda', 'ver')) {
            return response()->json(['error' => 'No tienes permisos para ver bÃ³vedas'], 403);
        }

        // Verificar permisos
        if (!in_array($user->rol, ['superadmin', 'administrador']) &&
            $boveda->sucursal_id != $user->sucursal_id) {
            return response()->json(['error' => 'No tienes permisos para ver esta bóveda'], 403);
        }

        // Estadísticas
        $stats = [
            'total_entradas' => $boveda->movimientosAprobados()->entradas()->sum('monto'),
            'total_salidas' => $boveda->movimientosAprobados()->salidas()->sum('monto'),
            'movimientos_pendientes' => $boveda->movimientos()->pendientes()->count(),
            'ultima_entrada' => $boveda->movimientosAprobados()->entradas()->orderBy('created_at', 'desc')->first(),
            'ultima_salida' => $boveda->movimientosAprobados()->salidas()->orderBy('created_at', 'desc')->first(),
        ];

        return response()->json([
            'boveda' => $boveda,
            'estadisticas' => $stats
        ]);
    }

    /**
     * Actualizar bóveda
     */
    public function update(Request $request, $id)
    {
        $request->validate([
            'nombre' => 'required|string|max:100',
            'descripcion' => 'nullable|string|max:500',
            'tipo' => 'required|in:general,principal,auxiliar',
            'activa' => 'boolean',
            'requiere_aprobacion' => 'boolean',
            'responsable_id' => 'nullable|exists:users,id',
        ]);

        /** @var User $user */
        $user = Auth::user();
        $boveda = Boveda::findOrFail($id);

        // Verificar permisos
        if (!$user->hasPermission('boveda', 'editar')) {
            return response()->json(['error' => 'No tienes permisos para editar bóvedas'], 403);
        }

        if (!in_array($user->rol, ['superadmin', 'administrador']) && $boveda->sucursal_id != $user->sucursal_id) {
            return response()->json(['error' => 'Solo puedes editar bóvedas de tu sucursal'], 403);
        }

        $boveda->update($request->all());

        return response()->json([
            'message' => 'Bóveda actualizada correctamente',
            'boveda' => $boveda->load(['sucursal', 'responsable'])
        ]);
    }

    /**
     * Eliminar bóveda (soft delete)
     */
    public function destroy($id)
    {
        /** @var User $user */
        $user = Auth::user();
        $boveda = Boveda::findOrFail($id);

        // Verificar permisos
        if (!$user->hasPermission('boveda', 'eliminar')) {
            return response()->json(['error' => 'No tienes permisos para eliminar bóvedas'], 403);
        }

        // Verificar que no tenga movimientos recientes
        if ($boveda->movimientos()->whereDate('created_at', '>=', now()->subDays(30))->exists()) {
            return response()->json(['error' => 'No se puede eliminar una bóveda con movimientos recientes'], 400);
        }

        $boveda->delete();

        return response()->json(['message' => 'Bóveda eliminada correctamente']);
    }

    /**
     * Registrar movimiento en bóveda
     */
    public function registrarMovimiento(Request $request, $id)
    {
        $request->validate([
            'tipo_movimiento' => 'required|in:entrada,salida,transferencia_entrada,transferencia_salida',
            'monto' => 'required|numeric|min:0.01',
            'concepto' => 'required|string|max:500',
            'desglose_denominaciones' => 'nullable|array',
            'boveda_destino_id' => 'required_if:tipo_movimiento,transferencia_salida|exists:bovedas,id',
            'referencia' => 'nullable|string|max:100',
        ]);

        /** @var User $user */
        $user = Auth::user();
        $boveda = Boveda::findOrFail($id);

        // Verificar permisos
        if (!$user->hasPermission('boveda', 'movimientos')) {
            return response()->json(['error' => 'No tienes permisos para realizar movimientos'], 403);
        }

        if (!in_array($user->rol, ['superadmin', 'administrador']) && $boveda->sucursal_id != $user->sucursal_id) {
            return response()->json(['error' => 'Solo puedes operar bóvedas de tu sucursal'], 403);
        }

        // Verificar que la bóveda esté activa
        if (!$boveda->activa) {
            return response()->json(['error' => 'La bóveda no está activa'], 400);
        }

        // Validar límites de saldo
        if ($request->tipo_movimiento === 'entrada' || $request->tipo_movimiento === 'transferencia_entrada') {
            if (!$boveda->puedeRecibirMonto($request->monto)) {
                return response()->json(['error' => 'El monto excede el saldo máximo permitido de la bóveda'], 400);
            }
        } else {
            if (!$boveda->puedeRetirarMonto($request->monto)) {
                return response()->json(['error' => 'El monto dejaría la bóveda por debajo del saldo mínimo'], 400);
            }
        }

        // Validar transferencia
        if ($request->tipo_movimiento === 'transferencia_salida') {
            $bovedaDestino = Boveda::findOrFail($request->boveda_destino_id);

            if (!$bovedaDestino->puedeRecibirMonto($request->monto)) {
                return response()->json(['error' => 'La bóveda destino no puede recibir ese monto'], 400);
            }
        }

        try {
            DB::beginTransaction();

            // Determinar estado del movimiento
            $estado = ($boveda->requiere_aprobacion && !$user->hasPermission('boveda', 'aprobar')) ? 'pendiente' : 'aprobado';

            $movimiento = BovedaMovimiento::create([
                'boveda_id' => $boveda->id,
                'usuario_id' => $user->id,
                'sucursal_id' => $boveda->sucursal_id,
                'tipo_movimiento' => $request->tipo_movimiento,
                'monto' => $request->monto,
                'concepto' => $request->concepto,
                'desglose_denominaciones' => $request->desglose_denominaciones,
                'boveda_destino_id' => $request->boveda_destino_id,
                'referencia' => $request->referencia,
                'estado' => $estado,
                'aprobado_por' => $estado === 'aprobado' ? $user->id : null,
                'fecha_aprobacion' => $estado === 'aprobado' ? now() : null,
            ]);

            // Guardar detalles de denominaciones normalizados
            if ($request->desglose_denominaciones && is_array($request->desglose_denominaciones)) {
                BovedaDetalle::crearDesdeDesglose($movimiento->id, $request->desglose_denominaciones);
            }

            // Si es transferencia, crear movimiento contraparte
            if ($request->tipo_movimiento === 'transferencia_salida') {
                $movimientoEntrada = BovedaMovimiento::create([
                    'boveda_id' => $request->boveda_destino_id,
                    'usuario_id' => $user->id,
                    'sucursal_id' => $bovedaDestino->sucursal_id,
                    'tipo_movimiento' => 'transferencia_entrada',
                    'monto' => $request->monto,
                    'concepto' => "Transferencia recibida de bóveda {$boveda->codigo}",
                    'desglose_denominaciones' => $request->desglose_denominaciones,
                    'boveda_destino_id' => $boveda->id,
                    'referencia' => $request->referencia,
                    'estado' => $estado,
                    'aprobado_por' => $estado === 'aprobado' ? $user->id : null,
                    'fecha_aprobacion' => $estado === 'aprobado' ? now() : null,
                ]);

                // Guardar detalles de denominaciones para el movimiento de entrada
                if ($request->desglose_denominaciones && is_array($request->desglose_denominaciones)) {
                    BovedaDetalle::crearDesdeDesglose($movimientoEntrada->id, $request->desglose_denominaciones);
                }

                // Si está aprobado, actualizar saldo de bóveda destino
                if ($estado === 'aprobado') {
                    $bovedaDestino->actualizarSaldo();
                }
            }

            // Si está aprobado, actualizar saldo de bóveda origen
            if ($estado === 'aprobado') {
                $boveda->actualizarSaldo();
            }

            DB::commit();

            $message = $estado === 'aprobado' ? 'Movimiento registrado y aprobado' : 'Movimiento registrado, pendiente de aprobación';

            return response()->json([
                'message' => $message,
                'movimiento' => $movimiento->load(['boveda', 'bovedaDestino', 'usuario']),
                'saldo_actual' => $boveda->fresh()->saldo_actual
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['error' => 'Error al registrar movimiento: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtener movimientos pendientes de aprobación
     */
    public function movimientosPendientes(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->hasPermission('boveda', 'aprobar')) {
            return response()->json(['error' => 'No tienes permisos para ver movimientos pendientes'], 403);
        }

        $query = BovedaMovimiento::with(['boveda', 'bovedaDestino', 'usuario'])
            ->pendientes()
            ->orderBy('created_at', 'desc');

        // Filtrar por sucursal si no es admin
        if (!in_array($user->rol, ['superadmin', 'administrador'])) {
            $query->where('sucursal_id', $user->sucursal_id);
        }

        $movimientos = $query->get();

        return response()->json(['movimientos' => $movimientos]);
    }

    /**
     * Aprobar movimiento
     */
    public function aprobarMovimiento(Request $request, $id)
    {
        $user = Auth::user();
        $movimiento = BovedaMovimiento::findOrFail($id);

        if (!$movimiento->puedeAprobar($user)) {
            return response()->json(['error' => 'No puedes aprobar este movimiento'], 403);
        }

        try {
            return DB::transaction(function () use ($movimiento, $user) {
                // Verificar si este movimiento está enlazado a un movimiento de caja (modo integrado)
                $movCaja = MovimientoCaja::where('boveda_movimiento_id', $movimiento->id)
                    ->where('estado', 'pendiente')
                    ->first();

                // Aprobar el movimiento de bóveda
                if (!$movimiento->aprobar($user)) {
                    return response()->json(['error' => 'No se pudo aprobar el movimiento'], 400);
                }

                // Si hay movimiento de caja enlazado, actualizarlo a 'aplicado'
                if ($movCaja) {
                    $movCaja->estado              = 'aplicado';
                    $movCaja->estado_boveda       = 'aprobado';
                    $movCaja->fecha_aprobacion_boveda = now();
                    $movCaja->aprobado_por_id     = $user->id;
                    $movCaja->save();
                }

                return response()->json([
                    'message'          => 'Movimiento aprobado correctamente',
                    'movimiento'       => $movimiento->load(['boveda', 'bovedaDestino', 'usuario', 'aprobador']),
                    'movimiento_caja'  => $movCaja,
                ]);
            });
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al aprobar el movimiento: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Rechazar movimiento
     */
    public function rechazarMovimiento(Request $request, $id)
    {
        $request->validate([
            'motivo_rechazo' => 'required|string|max:500'
        ]);

        $user = Auth::user();
        $movimiento = BovedaMovimiento::findOrFail($id);

        if (!$movimiento->puedeAprobar($user)) {
            return response()->json(['error' => 'No puedes rechazar este movimiento'], 403);
        }

        try {
            return DB::transaction(function () use ($movimiento, $user, $request) {
                // Verificar si este movimiento está enlazado a un movimiento de caja (modo integrado)
                $movCaja = MovimientoCaja::where('boveda_movimiento_id', $movimiento->id)
                    ->where('estado', 'pendiente')
                    ->first();

                // Rechazar el movimiento de bóveda
                if (!$movimiento->rechazar($user, $request->motivo_rechazo)) {
                    return response()->json(['error' => 'No se pudo rechazar el movimiento'], 400);
                }

                // Si hay movimiento de caja enlazado, actualizarlo a 'rechazado'
                if ($movCaja) {
                    $movCaja->estado              = 'rechazado';
                    $movCaja->estado_boveda       = 'rechazado';
                    $movCaja->observaciones_boveda = $request->motivo_rechazo;
                    $movCaja->fecha_aprobacion_boveda = now();
                    $movCaja->aprobado_por_id     = $user->id;
                    $movCaja->save();
                }

                return response()->json([
                    'message'         => 'Movimiento rechazado correctamente',
                    'movimiento'      => $movimiento->load(['boveda', 'bovedaDestino', 'usuario', 'aprobador']),
                    'movimiento_caja' => $movCaja,
                ]);
            });
        } catch (\Exception $e) {
            return response()->json(['error' => 'Error al rechazar el movimiento: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Obtener historial de movimientos de una bóveda
     */
    public function historialMovimientos(Request $request, $id)
    {
        $user = Auth::user();
        $boveda = Boveda::findOrFail($id);

        // Verificar permisos
        if (!in_array($user->rol, ['superadmin', 'administrador']) && $boveda->sucursal_id != $user->sucursal_id) {
            return response()->json(['error' => 'No tienes permisos para ver esta bóveda'], 403);
        }

        $query = $boveda->movimientos()->with(['usuario', 'aprobador', 'bovedaDestino']);

        // Filtros
        if ($request->estado) {
            $query->where('estado', $request->estado);
        }

        if ($request->tipo_movimiento) {
            $query->where('tipo_movimiento', $request->tipo_movimiento);
        }

        if ($request->fecha_inicio) {
            $query->whereDate('created_at', '>=', $request->fecha_inicio);
        }

        if ($request->fecha_fin) {
            $query->whereDate('created_at', '<=', $request->fecha_fin);
        }

        $movimientos = $query->orderBy('created_at', 'desc')->paginate(50);

        return response()->json($movimientos);
    }

    /**
     * Reporte de consolidación de bóvedas
     */
    public function consolidacion(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->hasPermission('boveda', 'reportes')) {
            return response()->json(['error' => 'No tienes permisos para generar reportes'], 403);
        }

        $query = Boveda::with(['sucursal', 'movimientosAprobados']);

        // Filtrar por sucursal si no es admin
        if (!in_array($user->rol, ['superadmin', 'administrador']) && $request->sucursal_id) {
            if ($request->sucursal_id != $user->sucursal_id) {
                return response()->json(['error' => 'Solo puedes ver reportes de tu sucursal'], 403);
            }
            $query->where('sucursal_id', $request->sucursal_id);
        } elseif (!in_array($user->rol, ['superadmin', 'administrador'])) {
            $query->where('sucursal_id', $user->sucursal_id);
        }

        if ($request->sucursal_id) {
            $query->where('sucursal_id', $request->sucursal_id);
        }

        $bovedas = $query->activas()->get();

        $consolidacion = $bovedas->map(function ($boveda) use ($request) {
            $movimientosQuery = $boveda->movimientosAprobados();

            if ($request->fecha_inicio) {
                $movimientosQuery->whereDate('created_at', '>=', $request->fecha_inicio);
            }

            if ($request->fecha_fin) {
                $movimientosQuery->whereDate('created_at', '<=', $request->fecha_fin);
            }

            $movimientos = $movimientosQuery->get();
            $totalEntradas = $movimientos->filter(fn($m) => in_array($m->tipo_movimiento, ['entrada', 'transferencia_entrada', 'ingreso_cierre_diario']))->sum('monto');
            $totalSalidas = $movimientos->filter(fn($m) => in_array($m->tipo_movimiento, ['salida', 'transferencia_salida']))->sum('monto');

            return [
                'boveda' => $boveda,
                'total_entradas' => $totalEntradas,
                'total_salidas' => $totalSalidas,
                'saldo_inicial' => $movimientos->first()->monto ?? 0,
                'saldo_final' => $boveda->saldo_actual,
                'numero_movimientos' => $movimientos->count(),
                'ultima_actualizacion' => $movimientos->max('created_at'),
            ];
        });

        $totales = [
            'total_bovedas' => $consolidacion->count(),
            'saldo_consolidado' => $consolidacion->sum('saldo_final'),
            'total_entradas' => $consolidacion->sum('total_entradas'),
            'total_salidas' => $consolidacion->sum('total_salidas'),
            'total_movimientos' => $consolidacion->sum('numero_movimientos'),
        ];

        return response()->json([
            'consolidacion' => $consolidacion,
            'totales' => $totales,
            'periodo' => [
                'fecha_inicio' => $request->fecha_inicio,
                'fecha_fin' => $request->fecha_fin,
            ]
        ]);
    }

    /**
     * Exportar lista de bóvedas a Excel
     */
    public function exportarBovedas(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->hasPermission('boveda', 'reportes')) {
            return response()->json(['error' => 'No tienes permisos para exportar reportes'], 403);
        }

        $filters = [
            'sucursal_id' => $request->sucursal_id,
            'activa' => $request->activa,
            'tipo' => $request->tipo,
        ];

        $fileName = 'bovedas_' . date('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new BovedasExport($filters, $user->id, $user->rol),
            $fileName
        );
    }

    /**
     * Exportar movimientos de bóveda a Excel
     */
    public function exportarMovimientos(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        $boveda = Boveda::findOrFail($id);

        if (!$user->hasPermission('boveda', 'reportes')) {
            return response()->json(['error' => 'No tienes permisos para exportar reportes'], 403);
        }

        if (!in_array($user->rol, ['superadmin', 'administrador']) && $boveda->sucursal_id != $user->sucursal_id) {
            return response()->json(['error' => 'No tienes permisos para exportar esta bóveda'], 403);
        }

        $filters = [
            'estado' => $request->estado,
            'tipo_movimiento' => $request->tipo_movimiento,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin' => $request->fecha_fin,
        ];

        $fileName = 'movimientos_boveda_' . $boveda->codigo . '_' . date('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new BovedaMovimientosExport($id, $filters),
            $fileName
        );
    }

    /**
     * Exportar consolidación a Excel
     */
    public function exportarConsolidacion(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->hasPermission('boveda', 'reportes')) {
            return response()->json(['error' => 'No tienes permisos para exportar reportes'], 403);
        }

        $filters = [
            'sucursal_id' => $request->sucursal_id,
            'fecha_inicio' => $request->fecha_inicio,
            'fecha_fin' => $request->fecha_fin,
        ];

        $fileName = 'consolidacion_bovedas_' . date('Y-m-d_His') . '.xlsx';

        return Excel::download(
            new BovedaConsolidacionExport($filters, $user->id, $user->rol),
            $fileName
        );
    }

    /**
     * Exportar movimientos de bóveda a PDF
     */
    public function exportarMovimientosPDF(Request $request, $id)
    {
        /** @var User $user */
        $user = Auth::user();
        $boveda = Boveda::with('sucursal')->findOrFail($id);

        if (!$user->hasPermission('boveda', 'reportes')) {
            return response()->json(['error' => 'No tienes permisos para exportar reportes'], 403);
        }

        if (!in_array($user->rol, ['superadmin', 'administrador']) && $boveda->sucursal_id != $user->sucursal_id) {
            return response()->json(['error' => 'No tienes permisos para exportar esta bóveda'], 403);
        }

        $query = $boveda->movimientos()->with(['usuario', 'aprobador', 'bovedaDestino']);

        if ($request->estado) {
            $query->where('estado', $request->estado);
        }

        if ($request->tipo_movimiento) {
            $query->where('tipo_movimiento', $request->tipo_movimiento);
        }

        if ($request->fecha_inicio) {
            $query->whereDate('created_at', '>=', $request->fecha_inicio);
        }

        if ($request->fecha_fin) {
            $query->whereDate('created_at', '<=', $request->fecha_fin);
        }

        $movimientos = $query->orderBy('created_at', 'desc')->get();

        $data = [
            'boveda' => $boveda,
            'movimientos' => $movimientos,
            'periodo' => [
                'inicio' => $request->fecha_inicio,
                'fin' => $request->fecha_fin,
            ],
            'fecha_generacion' => now()->format('d/m/Y H:i'),
            'usuario' => $user->name,
        ];

        $pdf = Pdf::loadView('reports.boveda-movimientos', $data)
            ->setPaper('letter', 'landscape');

        $fileName = 'movimientos_' . $boveda->codigo . '_' . date('Y-m-d_His') . '.pdf';

        return response()->streamDownload(function() use ($pdf) {
            echo $pdf->output();
        }, $fileName, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"'
        ]);
    }

    /**
     * Exportar consolidación a PDF
     */
    public function exportarConsolidacionPDF(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->hasPermission('boveda', 'reportes')) {
            return response()->json(['error' => 'No tienes permisos para exportar reportes'], 403);
        }

        $query = Boveda::with(['sucursal', 'movimientosAprobados']);

        if (!in_array($user->rol, ['superadmin', 'administrador']) && $request->sucursal_id) {
            if ($request->sucursal_id != $user->sucursal_id) {
                return response()->json(['error' => 'Solo puedes ver reportes de tu sucursal'], 403);
            }
            $query->where('sucursal_id', $request->sucursal_id);
        } elseif (!in_array($user->rol, ['superadmin', 'administrador'])) {
            $query->where('sucursal_id', $user->sucursal_id);
        }

        if ($request->sucursal_id) {
            $query->where('sucursal_id', $request->sucursal_id);
        }

        $bovedas = $query->activas()->get();

        $consolidacion = $bovedas->map(function ($boveda) use ($request) {
            $movimientosQuery = $boveda->movimientosAprobados();

            if ($request->fecha_inicio) {
                $movimientosQuery->whereDate('created_at', '>=', $request->fecha_inicio);
            }

            if ($request->fecha_fin) {
                $movimientosQuery->whereDate('created_at', '<=', $request->fecha_fin);
            }

            $movimientos = $movimientosQuery->get();
            $entradas = $movimientos->filter(fn($m) => in_array($m->tipo_movimiento, ['entrada', 'transferencia_entrada', 'ingreso_cierre_diario']));
            $salidas = $movimientos->filter(fn($m) => in_array($m->tipo_movimiento, ['salida', 'transferencia_salida']));

            return [
                'boveda' => $boveda,
                'total_entradas' => $entradas->sum('monto'),
                'total_salidas' => $salidas->sum('monto'),
                'saldo_actual' => $boveda->saldo_actual,
                'numero_movimientos' => $movimientos->count(),
            ];
        });

        $totales = [
            'total_bovedas' => $consolidacion->count(),
            'saldo_consolidado' => $consolidacion->sum('saldo_actual'),
            'total_entradas' => $consolidacion->sum('total_entradas'),
            'total_salidas' => $consolidacion->sum('total_salidas'),
        ];

        $data = [
            'consolidacion' => $consolidacion,
            'totales' => $totales,
            'periodo' => [
                'inicio' => $request->fecha_inicio,
                'fin' => $request->fecha_fin,
            ],
            'fecha_generacion' => now()->format('d/m/Y H:i'),
            'usuario' => $user->name,
        ];

        $pdf = Pdf::loadView('reports.boveda-consolidacion', $data)
            ->setPaper('letter', 'landscape');

        $fileName = 'consolidacion_bovedas_' . date('Y-m-d_His') . '.pdf';

        return response()->streamDownload(function() use ($pdf) {
            echo $pdf->output();
        }, $fileName, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"'
        ]);
    }

    // =====================================================================
    // INTEGRACIÓN CAJA-BÓVEDA: Aprobación/Rechazo de movimientos pendientes
    // =====================================================================

    /**
     * Listar movimientos de caja pendientes de aprobación en bóveda.
     * GET /api/v1/boveda/movimientos-caja-pendientes
     */
    public function movimientosCajaPendientes(Request $request)
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->hasPermission('boveda', 'aprobar')) {
            return response()->json(['error' => 'No tienes permisos para ver movimientos pendientes de bóveda.'], 403);
        }

        $query = \App\Models\MovimientoCaja::with(['caja.user', 'caja.sucursal', 'user', 'bovedaOrigen'])
            ->where('estado_boveda', 'pendiente_aprobacion');

        // Filtrar por sucursal si no es superadmin
        if (!in_array($user->rol, ['superadmin', 'administrador'])) {
            $query->whereHas('caja', function ($q) use ($user) {
                $q->where('sucursal_id', $user->sucursal_id);
            });
        }

        if ($request->filled('boveda_id')) {
            $query->where('boveda_id', $request->boveda_id);
        }

        $movimientos = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success'    => true,
            'data'       => $movimientos,
            'total'      => $movimientos->count(),
        ]);
    }

    /**
     * Aprobar un movimiento de caja pendiente en bóveda.
     * POST /api/v1/boveda/movimientos-caja-pendientes/{movCajaId}/aprobar
     */
    public function aprobarMovimientoCaja(Request $request, $movCajaId)
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->hasPermission('boveda', 'aprobar')) {
            return response()->json(['error' => 'No tienes permisos para aprobar movimientos de bóveda.'], 403);
        }

        $request->validate([
            'observaciones' => 'nullable|string|max:500',
        ]);

        try {
            return DB::transaction(function () use ($user, $movCajaId, $request) {
                /** @var \App\Models\MovimientoCaja $movCaja */
                $movCaja = \App\Models\MovimientoCaja::lockForUpdate()->findOrFail($movCajaId);

                if ($movCaja->estado_boveda !== 'pendiente_aprobacion') {
                    return response()->json(['error' => 'Este movimiento ya fue procesado.'], 400);
                }

                // Aprobar el movimiento de bóveda asociado
                $movBoveda = BovedaMovimiento::lockForUpdate()->findOrFail($movCaja->boveda_movimiento_id);
                $boveda    = Boveda::lockForUpdate()->findOrFail($movBoveda->boveda_id);

                // Para egresos de bóveda (incremento de caja), verificar saldo
                if ($movBoveda->tipo_movimiento === 'salida') {
                    if ($boveda->saldo_actual < $movBoveda->monto) {
                        return response()->json([
                            'error' => sprintf(
                                'Saldo insuficiente en bóveda para aprobar. Disponible: %s, Requerido: %s',
                                number_format($boveda->saldo_actual, 2),
                                number_format($movBoveda->monto, 2)
                            )
                        ], 400);
                    }
                }

                // Marcar movimiento de bóveda como aprobado
                $movBoveda->estado          = 'aprobado';
                $movBoveda->aprobado_por    = $user->id;
                $movBoveda->fecha_aprobacion = now();
                $movBoveda->save();

                // Recalcular saldo de bóveda
                $boveda->actualizarSaldo();

                // Actualizar movimiento de caja
                $movCaja->estado_boveda          = 'aprobado';
                $movCaja->estado                 = 'aplicado'; // El movimiento ya es efectivo en caja
                $movCaja->fecha_aprobacion_boveda = now();
                $movCaja->aprobado_por_id        = $user->id;
                $movCaja->observaciones_boveda   = $request->observaciones;
                $movCaja->save();

                return response()->json([
                    'success'      => true,
                    'message'      => 'Movimiento aprobado correctamente. Saldo de bóveda actualizado.',
                    'movimiento'   => $movCaja->load(['caja.user', 'user']),
                    'saldo_boveda' => $boveda->fresh()->saldo_actual,
                ]);
            });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error aprobando movimiento de caja en bóveda: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno al aprobar el movimiento.'], 500);
        }
    }

    /**
     * Rechazar un movimiento de caja pendiente en bóveda.
     * POST /api/v1/boveda/movimientos-caja-pendientes/{movCajaId}/rechazar
     */
    public function rechazarMovimientoCaja(Request $request, $movCajaId)
    {
        /** @var User $user */
        $user = Auth::user();

        if (!$user->hasPermission('boveda', 'aprobar')) {
            return response()->json(['error' => 'No tienes permisos para rechazar movimientos de bóveda.'], 403);
        }

        $request->validate([
            'motivo' => 'required|string|max:500',
        ]);

        try {
            return DB::transaction(function () use ($user, $movCajaId, $request) {
                /** @var \App\Models\MovimientoCaja $movCaja */
                $movCaja = \App\Models\MovimientoCaja::lockForUpdate()->findOrFail($movCajaId);

                if ($movCaja->estado_boveda !== 'pendiente_aprobacion') {
                    return response()->json(['error' => 'Este movimiento ya fue procesado.'], 400);
                }

                // Marcar movimiento de bóveda como rechazado
                $movBoveda = BovedaMovimiento::lockForUpdate()->findOrFail($movCaja->boveda_movimiento_id);
                $movBoveda->estado          = 'rechazado';
                $movBoveda->aprobado_por    = $user->id;
                $movBoveda->fecha_aprobacion = now();
                $movBoveda->save();

                // Movimiento en caja queda rechazado (no se aplica)
                $movCaja->estado_boveda          = 'rechazado';
                $movCaja->estado                 = 'rechazado';
                $movCaja->fecha_aprobacion_boveda = now();
                $movCaja->aprobado_por_id        = $user->id;
                $movCaja->observaciones_boveda   = $request->motivo;
                $movCaja->save();

                return response()->json([
                    'success'    => true,
                    'message'    => 'Movimiento rechazado. No se aplicó ningún cambio en el saldo de bóveda.',
                    'movimiento' => $movCaja->load(['caja.user', 'user']),
                ]);
            });
        } catch (\Exception $e) {
            \Illuminate\Support\Facades\Log::error('Error rechazando movimiento de caja en bóveda: ' . $e->getMessage());
            return response()->json(['error' => 'Error interno al rechazar el movimiento.'], 500);
        }
    }

    /**
     * Actualizar concepto o referencia de un movimiento de bóveda
     * PUT /api/v1/bovedas-movimientos/{id}
     */
    public function actualizarMovimiento(Request $request, $id)
    {
        $user = Auth::user();
        if (!$user->hasPermission('boveda', 'editar') && !$user->hasPermission('boveda', 'movimientos')) {
            return response()->json(['error' => 'No tienes permisos para editar movimientos'], 403);
        }

        $request->validate([
            'concepto' => 'required|string|max:500',
            'referencia' => 'nullable|string|max:100',
        ]);

        $movimiento = BovedaMovimiento::findOrFail($id);
        $boveda = Boveda::findOrFail($movimiento->boveda_id);

        if (!in_array($user->rol, ['superadmin', 'administrador']) && $boveda->sucursal_id != $user->sucursal_id) {
            return response()->json(['error' => 'Solo puedes editar movimientos de tu sucursal'], 403);
        }

        $movimiento->update([
            'concepto' => $request->concepto,
            'referencia' => $request->referencia,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Movimiento actualizado correctamente',
            'movimiento' => $movimiento->load(['usuario', 'aprobador', 'bovedaDestino'])
        ]);
    }
}

