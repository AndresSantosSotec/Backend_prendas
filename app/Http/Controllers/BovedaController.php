<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Boveda;
use App\Models\BovedaMovimiento;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use App\Models\BovedaDetalle;

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

        // Filtros por permisos
        if ($user->rol !== 'administrador' && $user->rol !== 'gerente') {
            // Usuarios normales solo ven bóvedas de su sucursal
            $query->where('sucursal_id', $user->sucursal_id);
        }

        // Filtros adicionales
        if ($request->sucursal_id) {
            $query->where('sucursal_id', $request->sucursal_id);
        }

        if ($request->activa !== null) {
            $query->where('activa', $request->activa);
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
            'saldo_minimo' => 'nullable|numeric|min:0',
            'saldo_maximo' => 'nullable|numeric|gt:saldo_minimo',
            'tipo' => 'required|in:general,principal,auxiliar',
            'requiere_aprobacion' => 'boolean',
            'responsable_id' => 'nullable|exists:users,id',
        ]);

        /** @var User $user */
        $user = Auth::user();

        // Verificar permisos para crear bóveda
        if (!$user->hasPermission('bovedas', 'crear') && $user->rol !== 'administrador') {
            return response()->json(['error' => 'No tienes permisos para crear bóvedas'], 403);
        }

        // Solo admin puede crear bóvedas en otras sucursales
        if ($user->rol !== 'administrador' && $request->sucursal_id != $user->sucursal_id) {
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
                BovedaMovimiento::create([
                    'boveda_id' => $boveda->id,
                    'usuario_id' => $user->id,
                    'sucursal_id' => $request->sucursal_id,
                    'tipo_movimiento' => 'entrada',
                    'monto' => $request->saldo_inicial,
                    'concepto' => 'Apertura de bóveda con saldo inicial',
                    'estado' => 'aprobado',
                    'aprobado_por' => $user->id,
                    'fecha_aprobacion' => now(),
                ]);

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

        // Verificar permisos
        if ($user->rol !== 'administrador' && $user->rol !== 'gerente' &&
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
            'saldo_minimo' => 'nullable|numeric|min:0',
            'saldo_maximo' => 'nullable|numeric|gt:saldo_minimo',
            'tipo' => 'required|in:general,principal,auxiliar',
            'activa' => 'boolean',
            'requiere_aprobacion' => 'boolean',
            'responsable_id' => 'nullable|exists:users,id',
        ]);

        /** @var User $user */
        $user = Auth::user();
        $boveda = Boveda::findOrFail($id);

        // Verificar permisos
        if (!$user->hasPermission('bovedas', 'editar') && $user->rol !== 'administrador' && $user->rol !== 'gerente') {
            return response()->json(['error' => 'No tienes permisos para editar bóvedas'], 403);
        }

        if ($user->rol !== 'administrador' && $boveda->sucursal_id != $user->sucursal_id) {
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
        if (!$user->hasPermission('bovedas', 'eliminar') && $user->rol !== 'administrador') {
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
        if (!$user->hasPermission('bovedas', 'movimientos') && $user->rol !== 'administrador' && $user->rol !== 'gerente') {
            return response()->json(['error' => 'No tienes permisos para realizar movimientos'], 403);
        }

        if ($user->rol !== 'administrador' && $boveda->sucursal_id != $user->sucursal_id) {
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
            $estado = ($boveda->requiere_aprobacion && !$user->hasRole(['admin', 'gerente'])) ? 'pendiente' : 'aprobado';

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

        if (!$user->hasPermission('bovedas', 'aprobar') && $user->rol !== 'administrador' && $user->rol !== 'gerente') {
            return response()->json(['error' => 'No tienes permisos para ver movimientos pendientes'], 403);
        }

        $query = BovedaMovimiento::with(['boveda', 'bovedaDestino', 'usuario'])
            ->pendientes()
            ->orderBy('created_at', 'desc');

        // Filtrar por sucursal si no es admin
        if ($user->rol !== 'administrador') {
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

        if ($movimiento->aprobar($user)) {
            return response()->json([
                'message' => 'Movimiento aprobado correctamente',
                'movimiento' => $movimiento->load(['boveda', 'bovedaDestino', 'usuario', 'aprobador'])
            ]);
        }

        return response()->json(['error' => 'No se pudo aprobar el movimiento'], 400);
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

        if ($movimiento->rechazar($user, $request->motivo_rechazo)) {
            return response()->json([
                'message' => 'Movimiento rechazado correctamente',
                'movimiento' => $movimiento->load(['boveda', 'bovedaDestino', 'usuario', 'aprobador'])
            ]);
        }

        return response()->json(['error' => 'No se pudo rechazar el movimiento'], 400);
    }

    /**
     * Obtener historial de movimientos de una bóveda
     */
    public function historialMovimientos(Request $request, $id)
    {
        $user = Auth::user();
        $boveda = Boveda::findOrFail($id);

        // Verificar permisos
        if ($user->rol !== 'administrador' && $user->rol !== 'gerente' && $boveda->sucursal_id != $user->sucursal_id) {
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

        if (!$user->hasPermission('bovedas', 'reportes') && $user->rol !== 'administrador' && $user->rol !== 'gerente') {
            return response()->json(['error' => 'No tienes permisos para generar reportes'], 403);
        }

        $query = Boveda::with(['sucursal', 'movimientosAprobados']);

        // Filtrar por sucursal si no es admin
        if ($user->rol !== 'administrador' && $request->sucursal_id) {
            if ($request->sucursal_id != $user->sucursal_id) {
                return response()->json(['error' => 'Solo puedes ver reportes de tu sucursal'], 403);
            }
            $query->where('sucursal_id', $request->sucursal_id);
        } elseif ($user->rol !== 'administrador') {
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

            return [
                'boveda' => $boveda,
                'total_entradas' => $movimientos->entradas()->sum('monto'),
                'total_salidas' => $movimientos->salidas()->sum('monto'),
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
}
