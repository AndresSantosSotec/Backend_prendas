<?php

namespace App\Http\Controllers;

use App\Services\CompraService;
use App\Services\ContabilidadAutomaticaService;
use App\Http\Resources\CompraResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;

class CompraController extends Controller
{
    protected $compraService;
    protected $contabilidadService;

    public function __construct(
        CompraService $compraService,
        ContabilidadAutomaticaService $contabilidadService
    ) {
        $this->compraService = $compraService;
        $this->contabilidadService = $contabilidadService;
    }

    /**
     * Listar compras con filtros y paginación
     */
    public function index(Request $request)
    {
        try {
            $filtros = $request->only([
                'sucursal_id',
                'estado',
                'cliente_id',
                'fecha_desde',
                'fecha_hasta',
                'search',
            ]);

            $perPage = $request->input('per_page', 15);

            $compras = $this->compraService->listarCompras($filtros)->paginate($perPage);

            return CompraResource::collection($compras);

        } catch (\Exception $e) {
            Log::error('Error al listar compras: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al obtener las compras',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Registrar una nueva compra directa
     */
    public function store(Request $request)
    {
        $request->validate([
            'cliente_id' => 'required|exists:clientes,id',
            'categoria_producto_id' => 'required|exists:categoria_productos,id',
            'descripcion' => 'required|string|max:500',
            'valor_tasacion' => 'nullable|numeric|min:0',
            'monto_pagado' => 'required|numeric|min:0',
            'precio_venta' => 'required|numeric|min:0',
            'metodo_pago' => 'nullable|string|in:efectivo,transferencia,cheque,mixto',
            'observaciones' => 'nullable|string|max:1000',
            'condicion' => 'nullable|string|in:excelente,muy_buena,buena,regular,mala',
            'marca' => 'nullable|string|max:100',
            'modelo' => 'nullable|string|max:100',
            'serie' => 'nullable|string|max:100',
            'color' => 'nullable|string|max:50',
            'campos_dinamicos' => 'nullable|array',
        ]);

        try {
            $compra = $this->compraService->procesarCompraDirecta($request->all());

            // Registrar asiento contable automático
            try {
                $this->contabilidadService->registrarAsiento('compra_directa', [
                    'sucursal_id' => $compra->sucursal_id,
                    'usuario_id' => Auth::id(),
                    'monto' => $compra->monto_pagado,
                    'compra_id' => $compra->id,
                    'numero_documento' => $compra->numero_compra,
                    'glosa' => "Compra directa #{$compra->numero_compra} - {$compra->descripcion}",
                    'fecha_documento' => $compra->fecha_compra,
                ]);
            } catch (\Exception $contError) {
                Log::warning('Error al registrar asiento contable para compra: ' . $contError->getMessage());
            }

            return response()->json([
                'success' => true,
                'message' => 'Compra registrada exitosamente. El producto ya está en el inventario.',
                'data' => new CompraResource($compra)
            ], 201);

        } catch (\Exception $e) {
            Log::error('Error al registrar compra directa: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al procesar la compra',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 400);
        }
    }

    /**
     * Obtener detalle completo de una compra
     */
    public function show($id)
    {
        try {
            $compra = $this->compraService->obtenerDetalle($id);

            return response()->json([
                'success' => true,
                'data' => new CompraResource($compra)
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener detalle de compra: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Compra no encontrada',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 404);
        }
    }

    /**
     * Cancelar una compra (si no ha sido vendida)
     */
    public function cancel($id, Request $request)
    {
        try {
            $compra = $this->compraService->obtenerDetalle($id);

            if ($compra->estado === 'vendida') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede cancelar una compra cuya prenda ya fue vendida'
                ], 422);
            }

            $compra->update([
                'estado' => 'cancelada',
                'observaciones' => ($compra->observaciones ?? '') . "\n[CANCELADA] " . ($request->input('motivo') ?? 'Sin motivo especificado')
            ]);

            // Marcar la prenda también
            if ($compra->prenda) {
                $compra->prenda->update([
                    'observaciones' => ($compra->prenda->observaciones ?? '') . "\n[Compra cancelada]"
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Compra cancelada exitosamente',
                'data' => new CompraResource($compra->fresh())
            ]);

        } catch (\Exception $e) {
            Log::error('Error al cancelar compra: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cancelar la compra',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Obtener estadísticas de compras
     */
    public function stats(Request $request)
    {
        try {
            $filtros = $request->only(['sucursal_id', 'fecha_desde', 'fecha_hasta']);

            $query = $this->compraService->listarCompras($filtros);

            $stats = [
                'total_compras' => $query->count(),
                'total_invertido' => $query->sum('monto_pagado'),
                'valor_inventario_actual' => $query->where('estado', 'activa')->sum('precio_venta_sugerido'),
                'compras_activas' => $query->where('estado', 'activa')->count(),
                'compras_vendidas' => $query->where('estado', 'vendida')->count(),
                'compras_canceladas' => $query->where('estado', 'cancelada')->count(),
                'margen_promedio' => round($query->avg(DB::raw('((precio_venta_sugerido - monto_pagado) / monto_pagado) * 100')), 2),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al calcular estadísticas',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Actualizar una compra
     */
    public function update($id, Request $request)
    {
        try {
            $compra = $this->compraService->obtenerDetalle($id);

            if ($compra->estado === 'vendida') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede editar una compra cuya prenda ya fue vendida'
                ], 422);
            }

            $request->validate([
                'descripcion' => 'required|string|max:500',
                'marca' => 'nullable|string|max:100',
                'modelo' => 'nullable|string|max:100',
                'serie' => 'nullable|string|max:100',
                'color' => 'nullable|string|max:50',
                'condicion' => 'nullable|string|in:excelente,muy_buena,buena,regular,mala',
                'precio_venta_sugerido' => 'nullable|numeric|min:0',
                'observaciones' => 'nullable|string|max:1000',
            ]);

            $compra->update($request->only([
                'descripcion',
                'marca',
                'modelo',
                'serie',
                'color',
                'condicion',
                'precio_venta_sugerido',
                'observaciones'
            ]));

            // Actualizar también la prenda si existe
            if ($compra->prenda) {
                $compra->prenda->update([
                    'descripcion' => $request->descripcion,
                    'marca' => $request->marca,
                    'modelo' => $request->modelo,
                    'color' => $request->color,
                    'condicion' => $request->condicion,
                    'precio_venta' => $request->precio_venta_sugerido ?? $compra->prenda->precio_venta,
                ]);
            }

            return response()->json([
                'success' => true,
                'message' => 'Compra actualizada exitosamente',
                'data' => new CompraResource($compra->fresh())
            ]);

        } catch (\Exception $e) {
            Log::error('Error al actualizar compra: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar la compra',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Eliminar una compra (solo si no está vendida)
     */
    public function destroy($id)
    {
        try {
            $compra = $this->compraService->obtenerDetalle($id);

            if ($compra->estado === 'vendida') {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar una compra cuya prenda ya fue vendida'
                ], 422);
            }

            // Eliminar la prenda asociada si existe
            if ($compra->prenda) {
                $compra->prenda->delete();
            }

            // Eliminar campos dinámicos
            $compra->camposDinamicos()->delete();

            // Eliminar la compra
            $compra->delete();

            return response()->json([
                'success' => true,
                'message' => 'Compra eliminada exitosamente'
            ]);

        } catch (\Exception $e) {
            Log::error('Error al eliminar compra: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al eliminar la compra',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Generar PDF del recibo de compra
     */
    public function generarReciboPDF($id)
    {
        try {
            $compra = $this->compraService->obtenerDetalle($id);

            $pdf = Pdf::loadView('pdf.recibo-compra', [
                'compra' => $compra,
                'fecha_actual' => now()->format('d/m/Y H:i')
            ]);

            return $pdf->download('recibo-compra-' . $compra->codigo_compra . '.pdf');

        } catch (\Exception $e) {
            Log::error('Error al generar PDF de compra: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el recibo',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }
}

