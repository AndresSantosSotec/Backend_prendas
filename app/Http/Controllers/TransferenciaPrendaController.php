<?php

namespace App\Http\Controllers;

use App\Models\TransferenciaPrenda;
use App\Models\Prenda;
use App\Services\AuditoriaService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class TransferenciaPrendaController extends Controller
{
    /**
     * Listar transferencias con filtros
     * GET /transferencias-prendas
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = TransferenciaPrenda::with([
                'prenda', 'credito.cliente',
                'sucursalOrigen', 'sucursalDestino',
                'usuarioSolicita', 'usuarioAutoriza', 'usuarioRecibe'
            ]);

            // Filtro por sucursal (ver las que involucran mi sucursal)
            if ($request->has('_sucursal_scope')) {
                $sId = $request->_sucursal_scope;
                $query->where(function ($q) use ($sId) {
                    $q->where('sucursal_origen_id', $sId)
                      ->orWhere('sucursal_destino_id', $sId);
                });
            }

            if ($request->filled('estado')) {
                $query->where('estado', $request->estado);
            }
            if ($request->filled('sucursal_origen_id')) {
                $query->where('sucursal_origen_id', $request->sucursal_origen_id);
            }
            if ($request->filled('sucursal_destino_id')) {
                $query->where('sucursal_destino_id', $request->sucursal_destino_id);
            }
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('codigo_transferencia', 'like', "%{$search}%")
                      ->orWhereHas('prenda', fn($pq) => $pq->where('descripcion', 'like', "%{$search}%")
                          ->orWhere('codigo', 'like', "%{$search}%"));
                });
            }

            $transferencias = $query->orderByDesc('fecha_solicitud')
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'success' => true,
                'data' => $transferencias
            ]);
        } catch (\Exception $e) {
            Log::error('Error al listar transferencias: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Solicitar transferencia de prenda
     * POST /transferencias-prendas
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'prenda_id' => 'required|exists:prendas,id',
            'sucursal_destino_id' => 'required|exists:sucursales,id',
            'motivo' => 'required|string|max:500',
        ]);

        try {
            return DB::transaction(function () use ($request) {
                $prenda = Prenda::findOrFail($request->prenda_id);
                $user = Auth::user();
                $sucursalOrigenId = $request->_sucursal_scope ?? $user->sucursal_id ?? $prenda->sucursal_id;

                // Validaciones
                if ($sucursalOrigenId == $request->sucursal_destino_id) {
                    throw new \Exception("La sucursal de origen y destino no pueden ser la misma");
                }

                // Verificar que no haya transferencia pendiente para esta prenda
                $pendiente = TransferenciaPrenda::where('prenda_id', $prenda->id)
                    ->whereIn('estado', ['solicitada', 'autorizada', 'en_transito'])
                    ->first();
                if ($pendiente) {
                    throw new \Exception("Esta prenda ya tiene una transferencia pendiente ({$pendiente->codigo_transferencia})");
                }

                $transferencia = TransferenciaPrenda::create([
                    'prenda_id' => $prenda->id,
                    'credito_id' => $prenda->credito_prendario_id ?? null,
                    'sucursal_origen_id' => $sucursalOrigenId,
                    'sucursal_destino_id' => $request->sucursal_destino_id,
                    'usuario_solicita_id' => $user->id,
                    'estado' => TransferenciaPrenda::ESTADO_SOLICITADA,
                    'motivo' => $request->motivo,
                ]);

                AuditoriaService::logAccion(
                    modulo: 'transferencias',
                    accion: 'solicitar_transferencia',
                    descripcion: "Transferencia solicitada: Prenda #{$prenda->id} de sucursal {$sucursalOrigenId} a {$request->sucursal_destino_id}",
                    tabla: 'transferencias_prendas',
                    registro: $transferencia
                );

                Log::info("Transferencia solicitada", [
                    'transferencia_id' => $transferencia->id,
                    'prenda_id' => $prenda->id,
                    'origen' => $sucursalOrigenId,
                    'destino' => $request->sucursal_destino_id,
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Transferencia solicitada exitosamente',
                    'data' => $transferencia->fresh(['prenda', 'sucursalOrigen', 'sucursalDestino', 'usuarioSolicita'])
                ], 201);
            });
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Ver detalle de transferencia
     * GET /transferencias-prendas/{id}
     */
    public function show(string $id): JsonResponse
    {
        try {
            $transferencia = TransferenciaPrenda::with([
                'prenda', 'credito.cliente',
                'sucursalOrigen', 'sucursalDestino',
                'usuarioSolicita', 'usuarioAutoriza', 'usuarioRecibe'
            ])->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $transferencia
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Transferencia no encontrada'
            ], 404);
        }
    }

    /**
     * Autorizar transferencia (admin/superadmin)
     * POST /transferencias-prendas/{id}/autorizar
     */
    public function autorizar(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'observaciones' => 'nullable|string|max:500',
        ]);

        try {
            return DB::transaction(function () use ($request, $id) {
                $transferencia = TransferenciaPrenda::findOrFail($id);

                if ($transferencia->estado !== TransferenciaPrenda::ESTADO_SOLICITADA) {
                    throw new \Exception("Solo se pueden autorizar transferencias en estado 'solicitada'. Estado actual: {$transferencia->estado}");
                }

                $transferencia->update([
                    'estado' => TransferenciaPrenda::ESTADO_AUTORIZADA,
                    'usuario_autoriza_id' => Auth::id(),
                    'observaciones_autorizacion' => $request->observaciones,
                    'fecha_autorizacion' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Transferencia autorizada',
                    'data' => $transferencia->fresh()
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
     * Enviar prenda (marcar en tránsito)
     * POST /transferencias-prendas/{id}/enviar
     */
    public function enviar(Request $request, string $id): JsonResponse
    {
        try {
            return DB::transaction(function () use ($id) {
                $transferencia = TransferenciaPrenda::findOrFail($id);

                if ($transferencia->estado !== TransferenciaPrenda::ESTADO_AUTORIZADA) {
                    throw new \Exception("Solo se pueden enviar transferencias autorizadas. Estado actual: {$transferencia->estado}");
                }

                $transferencia->update([
                    'estado' => TransferenciaPrenda::ESTADO_EN_TRANSITO,
                    'fecha_envio' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Prenda marcada como en tránsito',
                    'data' => $transferencia->fresh()
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
     * Recibir prenda en sucursal destino
     * POST /transferencias-prendas/{id}/recibir
     */
    public function recibir(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'observaciones' => 'nullable|string|max:500',
        ]);

        try {
            return DB::transaction(function () use ($request, $id) {
                $transferencia = TransferenciaPrenda::findOrFail($id);

                if (!in_array($transferencia->estado, [TransferenciaPrenda::ESTADO_AUTORIZADA, TransferenciaPrenda::ESTADO_EN_TRANSITO])) {
                    throw new \Exception("Solo se pueden recibir transferencias autorizadas o en tránsito. Estado actual: {$transferencia->estado}");
                }

                // Actualizar sucursal de la prenda
                $prenda = Prenda::findOrFail($transferencia->prenda_id);
                $prenda->update([
                    'sucursal_id' => $transferencia->sucursal_destino_id,
                ]);

                $transferencia->update([
                    'estado' => TransferenciaPrenda::ESTADO_RECIBIDA,
                    'usuario_recibe_id' => Auth::id(),
                    'observaciones_recepcion' => $request->observaciones,
                    'fecha_recepcion' => now(),
                ]);

                AuditoriaService::logAccion(
                    modulo: 'transferencias',
                    accion: 'recibir_transferencia',
                    descripcion: "Prenda #{$prenda->id} recibida en sucursal {$transferencia->sucursal_destino_id}",
                    tabla: 'transferencias_prendas',
                    registro: $transferencia
                );

                return response()->json([
                    'success' => true,
                    'message' => 'Prenda recibida exitosamente en la sucursal',
                    'data' => $transferencia->fresh(['prenda', 'sucursalOrigen', 'sucursalDestino'])
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
     * Rechazar transferencia
     * POST /transferencias-prendas/{id}/rechazar
     */
    public function rechazar(Request $request, string $id): JsonResponse
    {
        $request->validate([
            'motivo' => 'required|string|max:500',
        ]);

        try {
            return DB::transaction(function () use ($request, $id) {
                $transferencia = TransferenciaPrenda::findOrFail($id);

                if (in_array($transferencia->estado, [TransferenciaPrenda::ESTADO_RECIBIDA, TransferenciaPrenda::ESTADO_CANCELADA, TransferenciaPrenda::ESTADO_RECHAZADA])) {
                    throw new \Exception("No se puede rechazar una transferencia en estado: {$transferencia->estado}");
                }

                $transferencia->update([
                    'estado' => TransferenciaPrenda::ESTADO_RECHAZADA,
                    'usuario_autoriza_id' => Auth::id(),
                    'motivo_rechazo' => $request->motivo,
                    'fecha_autorizacion' => now(),
                ]);

                return response()->json([
                    'success' => true,
                    'message' => 'Transferencia rechazada',
                    'data' => $transferencia->fresh()
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
     * Estadísticas de transferencias
     * GET /transferencias-prendas/estadisticas
     */
    public function estadisticas(Request $request): JsonResponse
    {
        try {
            $query = TransferenciaPrenda::query();

            if ($request->has('_sucursal_scope')) {
                $sId = $request->_sucursal_scope;
                $query->where(function ($q) use ($sId) {
                    $q->where('sucursal_origen_id', $sId)
                      ->orWhere('sucursal_destino_id', $sId);
                });
            }

            $stats = [
                'total' => (clone $query)->count(),
                'solicitadas' => (clone $query)->where('estado', 'solicitada')->count(),
                'autorizadas' => (clone $query)->where('estado', 'autorizada')->count(),
                'en_transito' => (clone $query)->where('estado', 'en_transito')->count(),
                'recibidas' => (clone $query)->where('estado', 'recibida')->count(),
                'rechazadas' => (clone $query)->where('estado', 'rechazada')->count(),
                'canceladas' => (clone $query)->where('estado', 'cancelada')->count(),
            ];

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
