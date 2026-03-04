<?php

namespace App\Http\Controllers;

use App\Models\Remate;
use App\Models\CreditoPrendario;
use App\Models\Prenda;
use App\Models\CreditoMovimiento;
use App\Services\AuditoriaService;
use App\Services\CajaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RemateController extends Controller
{
    /**
     * Listar remates con filtros
     * GET /remates
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Remate::with(['credito.cliente', 'prenda', 'sucursal', 'usuario']);

            // Filtro por sucursal (SucursalScope)
            if ($request->has('_sucursal_scope')) {
                $query->where('sucursal_id', $request->_sucursal_scope);
            }

            // Filtros opcionales
            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }
            if ($request->filled('tipo')) {
                $query->where('tipo', $request->tipo);
            }
            if ($request->filled('sucursal_id')) {
                $query->where('sucursal_id', $request->sucursal_id);
            }
            if ($request->filled('fecha_desde')) {
                $query->whereDate('fecha_remate', '>=', $request->fecha_desde);
            }
            if ($request->filled('fecha_hasta')) {
                $query->whereDate('fecha_remate', '<=', $request->fecha_hasta);
            }
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('codigo_remate', 'like', "%{$search}%")
                      ->orWhereHas('credito', fn($cq) => $cq->where('codigo_credito', 'like', "%{$search}%"))
                      ->orWhereHas('credito.cliente', fn($cq) => $cq->where('nombres', 'like', "%{$search}%")
                          ->orWhere('apellidos', 'like', "%{$search}%"));
                });
            }

            $remates = $query->orderByDesc('fecha_remate')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $remates
            ]);
        } catch (\Exception $e) {
            Log::error('Error al listar remates: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ver detalle de un remate
     * GET /remates/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $remate = Remate::with(['credito.cliente', 'credito.prendas', 'prenda', 'sucursal', 'usuario'])
                ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $remate
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Remate no encontrado'
            ], 404);
        }
    }

    /**
     * Listar créditos candidatos a remate (vencidos + en_mora con X días)
     * GET /remates/candidatos
     */
    public function candidatos(Request $request): JsonResponse
    {
        try {
            $diasMinimos = (int) $request->get('dias_minimos', 30);

            $query = CreditoPrendario::with(['cliente', 'prendas', 'sucursal'])
                ->whereIn('estado', ['vencido', 'en_mora'])
                ->whereNotNull('fecha_vencimiento')
                ->whereDate('fecha_vencimiento', '<=', now()->subDays($diasMinimos));

            // Filtro de sucursal
            if ($request->has('_sucursal_scope')) {
                $query->where('sucursal_id', $request->_sucursal_scope);
            }

            // Excluir créditos que ya tienen remate activo
            $query->whereDoesntHave('remates', fn($q) => $q->whereIn('estado', ['pendiente', 'ejecutado']));

            $candidatos = $query->orderBy('fecha_vencimiento')
                ->paginate($request->get('per_page', 15));

            // Añadir info calculada
            $candidatos->getCollection()->transform(function ($credito) {
                $credito->dias_vencido = $credito->fecha_vencimiento
                    ? max(0, Carbon::parse($credito->fecha_vencimiento)->diffInDays(now()))
                    : 0;
                $credito->deuda_total = ($credito->capital_pendiente ?? 0)
                    + ($credito->intereses_pendientes ?? 0)
                    + ($credito->mora_pendiente ?? 0);
                return $credito;
            });

            return response()->json([
                'success' => true,
                'data' => $candidatos
            ]);
        } catch (\Exception $e) {
            Log::error('Error al listar candidatos a remate: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ejecutar remate manual de un crédito
     * POST /remates
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'credito_id' => 'required|exists:creditos_prendarios,id',
            'motivo' => 'nullable|string|max:500',
            'observaciones' => 'nullable|string|max:1000',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $credito = CreditoPrendario::with(['prendas', 'sucursal'])
                    ->findOrFail($request->credito_id);

                // Validaciones
                if (!in_array($credito->estado, ['vencido', 'en_mora'])) {
                    throw new \Exception("Solo se pueden rematar créditos vencidos o en mora. Estado actual: {$credito->estado}");
                }

                // Verificar que no tenga remate activo
                $remateExistente = Remate::where('credito_id', $credito->id)
                    ->whereIn('estado', ['pendiente', 'ejecutado'])
                    ->first();
                if ($remateExistente) {
                    throw new \Exception("Este crédito ya tiene un remate en proceso ({$remateExistente->codigo_remate})");
                }

                $user = Auth::user();
                $sucursalId = $request->_sucursal_scope ?? $user->sucursal_id ?? $credito->sucursal_id;

                // Crear remates por cada prenda del crédito
                $rematesCreados = [];
                foreach ($credito->prendas as $prenda) {
                    // Saltar prendas que ya no están en custodia
                    if (!in_array($prenda->estado, ['en_custodia', 'recuperada'])) {
                        continue;
                    }

                    $remate = Remate::create([
                        'credito_id' => $credito->id,
                        'prenda_id' => $prenda->id,
                        'sucursal_id' => $sucursalId,
                        'usuario_id' => $user->id,
                        'tipo' => Remate::TIPO_MANUAL,
                        'estado' => Remate::ESTADO_EJECUTADO,
                        'capital_pendiente' => $credito->capital_pendiente ?? 0,
                        'intereses_pendientes' => $credito->intereses_pendientes ?? 0,
                        'mora_pendiente' => $credito->mora_pendiente ?? 0,
                        'valor_avaluo' => $prenda->valor_avaluo ?? $prenda->precio_avaluo ?? 0,
                        'fecha_vencimiento_credito' => $credito->fecha_vencimiento,
                        'motivo' => $request->motivo ?? 'Remate manual por vencimiento',
                        'observaciones' => $request->observaciones,
                    ]);

                    // Actualizar prenda: marcar en_venta (para que aparezca en el POS)
                    $prenda->update([
                        'estado' => 'en_venta',
                        'precio_remate' => $prenda->valor_avaluo ?? $prenda->precio_avaluo ?? 0,
                        'fecha_remate' => now(),
                    ]);

                    // Auditoría por prenda
                    AuditoriaService::logRemate($prenda, $credito);

                    $rematesCreados[] = $remate;
                }

                if (empty($rematesCreados)) {
                    throw new \Exception("No se encontraron prendas elegibles para remate en este crédito");
                }

                // Actualizar estado del crédito
                CreditoPrendario::$auditarDeshabilitado = true;
                $credito->update(['estado' => 'rematado']);
                CreditoPrendario::$auditarDeshabilitado = false;

                // Registrar movimiento
                CreditoMovimiento::create([
                    'credito_prendario_id' => $credito->id,
                    'tipo_movimiento' => 'remate',
                    'monto_total' => ($credito->capital_pendiente ?? 0) + ($credito->intereses_pendientes ?? 0) + ($credito->mora_pendiente ?? 0),
                    'capital' => $credito->capital_pendiente ?? 0,
                    'interes' => $credito->intereses_pendientes ?? 0,
                    'mora' => $credito->mora_pendiente ?? 0,
                    'saldo_capital' => 0,
                    'saldo_interes' => 0,
                    'saldo_mora' => 0,
                    'concepto' => 'Remate de crédito vencido',
                    'observaciones' => $request->motivo ?? 'Remate manual',
                    'usuario_id' => $user->id,
                    'sucursal_id' => $sucursalId,
                    'estado' => 'activo',
                    'fecha_movimiento' => now(),
                    'fecha_registro' => now(),
                ]);

                Log::info("Remate ejecutado", [
                    'credito_id' => $credito->id,
                    'codigo_credito' => $credito->codigo_credito,
                    'remates_creados' => count($rematesCreados),
                    'usuario_id' => $user->id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Remate ejecutado exitosamente. ' . count($rematesCreados) . ' prenda(s) puesta(s) en venta.',
                    'data' => [
                        'remates' => Remate::with(['prenda'])->whereIn('id', collect($rematesCreados)->pluck('id'))->get(),
                        'credito' => $credito->fresh(['prendas', 'cliente']),
                    ]
                ]);
            });
        } catch (\Exception $e) {
            Log::error('Error al ejecutar remate: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Cancelar un remate (revertir a estado anterior)
     * POST /remates/{id}/cancelar
     */
    public function cancelar(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'motivo' => 'required|string|max:500',
        ]);

        try {
            return DB::transaction(function () use ($request, $id) {
                $remate = Remate::findOrFail($id);

                if ($remate->estado === Remate::ESTADO_CANCELADO) {
                    throw new \Exception("Este remate ya fue cancelado");
                }
                if ($remate->estado === Remate::ESTADO_VENDIDO) {
                    throw new \Exception("No se puede cancelar un remate cuya prenda ya fue vendida");
                }

                // Revertir prenda a en_custodia
                $prenda = Prenda::findOrFail($remate->prenda_id);
                $prenda->update([
                    'estado' => 'en_custodia',
                    'precio_remate' => null,
                    'fecha_remate' => null,
                ]);

                // Actualizar remate
                $remate->update([
                    'estado' => Remate::ESTADO_CANCELADO,
                    'observaciones' => ($remate->observaciones ? $remate->observaciones . "\n" : '') . "Cancelado: " . $request->motivo,
                ]);

                // Si no quedan remates activos para este crédito, revertir estado del crédito
                $rematesActivos = Remate::where('credito_id', $remate->credito_id)
                    ->whereIn('estado', [Remate::ESTADO_PENDIENTE, Remate::ESTADO_EJECUTADO])
                    ->count();

                if ($rematesActivos === 0) {
                    $credito = CreditoPrendario::find($remate->credito_id);
                    if ($credito && $credito->estado === 'rematado') {
                        CreditoPrendario::$auditarDeshabilitado = true;
                        $credito->update(['estado' => 'vencido']);
                        CreditoPrendario::$auditarDeshabilitado = false;
                    }
                }

                return response()->json([
                    'success' => true,
                    'message' => 'Remate cancelado exitosamente',
                    'data' => $remate->fresh(['prenda', 'credito'])
                ]);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Estadísticas de remates
     * GET /remates/estadisticas
     */
    public function estadisticas(Request $request): JsonResponse
    {
        try {
            $query = Remate::query();

            if ($request->has('_sucursal_scope')) {
                $query->where('sucursal_id', $request->_sucursal_scope);
            }

            $stats = [
                'total_remates' => (clone $query)->count(),
                'pendientes' => (clone $query)->where('estado', 'pendiente')->count(),
                'ejecutados' => (clone $query)->where('estado', 'ejecutado')->count(),
                'vendidos' => (clone $query)->where('estado', 'vendido')->count(),
                'cancelados' => (clone $query)->where('estado', 'cancelado')->count(),
                'manuales' => (clone $query)->where('tipo', 'manual')->count(),
                'automaticos' => (clone $query)->where('tipo', 'automatico')->count(),
                'deuda_total_rematada' => (clone $query)->whereIn('estado', ['ejecutado', 'vendido'])->sum('deuda_total'),
                'valor_avaluo_total' => (clone $query)->whereIn('estado', ['ejecutado', 'vendido'])->sum('valor_avaluo'),
            ];

            // Candidatos a remate (vencidos > 30 días)
            $candidatosQuery = CreditoPrendario::whereIn('estado', ['vencido', 'en_mora'])
                ->whereNotNull('fecha_vencimiento')
                ->whereDate('fecha_vencimiento', '<=', now()->subDays(30))
                ->whereDoesntHave('remates', fn($q) => $q->whereIn('estado', ['pendiente', 'ejecutado']));

            if ($request->has('_sucursal_scope')) {
                $candidatosQuery->where('sucursal_id', $request->_sucursal_scope);
            }

            $stats['candidatos_remate'] = $candidatosQuery->count();

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
