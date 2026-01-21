<?php

namespace App\Http\Controllers;

use App\Models\Prenda;
use App\Models\Venta;
use App\Services\VentaService;
use App\Http\Resources\PrendaResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VentaController extends Controller
{
    protected $ventaService;

    public function __construct(VentaService $ventaService)
    {
        $this->ventaService = $ventaService;
    }

    /**
     * Listar prendas disponibles para venta
     */
    public function prendasEnVenta(Request $request)
    {
        try {
            $filtros = $request->all();
            $prendas = $this->ventaService->getPrendasEnVenta($filtros);

            return response()->json([
                'success' => true,
                'data' => PrendaResource::collection($prendas->items()),
                'pagination' => [
                    'current_page' => $prendas->currentPage(),
                    'last_page' => $prendas->lastPage(),
                    'per_page' => $prendas->perPage(),
                    'total' => $prendas->total(),
                    'from' => $prendas->firstItem(),
                    'to' => $prendas->lastItem()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al listar prendas en venta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar prendas en venta',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Marcar prenda para venta
     */
    public function marcarParaVenta(Request $request, string $id)
    {
        $request->validate([
            'precio_venta' => 'nullable|numeric|min:0',
            'motivo' => 'nullable|string|max:500'
        ]);

        try {
            $prenda = Prenda::findOrFail($id);
            $resultado = $this->ventaService->marcarPrendaEnVenta($prenda, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Prenda marcada para venta exitosamente',
                'data' => $resultado
            ]);
        } catch (\Exception $e) {
            Log::error('Error al marcar prenda para venta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Procesar venta de prenda
     */
    public function procesarVenta(Request $request, string $id)
    {
        $request->validate([
            'precio_final' => 'required|numeric|min:0',
            'cliente_nombre' => 'required|string|max:200',
            'cliente_nit' => 'nullable|string|max:20',
            'cliente_telefono' => 'nullable|string|max:20',
            'cliente_email' => 'nullable|email|max:100',
            'metodo_pago' => 'required|in:efectivo,tarjeta,transferencia,cheque,mixto',
            'referencia_pago' => 'nullable|string|max:100',
            'observaciones' => 'nullable|string|max:1000'
        ]);

        try {
            $prenda = Prenda::findOrFail($id);
            $venta = $this->ventaService->procesarVenta($prenda, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Venta procesada exitosamente',
                'data' => $venta
            ]);
        } catch (\Exception $e) {
            Log::error('Error al procesar venta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Listar ventas realizadas
     */
    public function index(Request $request)
    {
        try {
            $query = Venta::with(['prenda.categoriaProducto', 'vendedor', 'sucursal']);

            // Filtros
            if ($request->has('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->has('fecha_desde')) {
                $query->whereDate('fecha_venta', '>=', $request->fecha_desde);
            }

            if ($request->has('fecha_hasta')) {
                $query->whereDate('fecha_venta', '<=', $request->fecha_hasta);
            }

            if ($request->has('vendedor_id')) {
                $query->where('vendedor_id', $request->vendedor_id);
            }

            if ($request->has('busqueda')) {
                $busqueda = $request->busqueda;
                $query->where(function($q) use ($busqueda) {
                    $q->where('codigo_venta', 'like', "%{$busqueda}%")
                      ->orWhere('cliente_nombre', 'like', "%{$busqueda}%")
                      ->orWhere('cliente_nit', 'like', "%{$busqueda}%");
                });
            }

            $ventas = $query->orderBy('fecha_venta', 'desc')
                           ->paginate($request->per_page ?? 50);

            return response()->json([
                'success' => true,
                'data' => $ventas->items(),
                'pagination' => [
                    'current_page' => $ventas->currentPage(),
                    'last_page' => $ventas->lastPage(),
                    'per_page' => $ventas->perPage(),
                    'total' => $ventas->total(),
                    'from' => $ventas->firstItem(),
                    'to' => $ventas->lastItem()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al listar ventas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar ventas',
                'error' => config('app.debug') ? $e->getMessage() : null
            ], 500);
        }
    }

    /**
     * Obtener detalle de venta
     */
    public function show(string $id)
    {
        try {
            $venta = Venta::with(['prenda.categoriaProducto', 'creditoPrendario', 'vendedor', 'sucursal'])
                         ->findOrFail($id);

            return response()->json([
                'success' => true,
                'data' => $venta
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener venta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Venta no encontrada'
            ], 404);
        }
    }

    /**
     * Cancelar venta
     */
    public function cancelar(Request $request, string $id)
    {
        $request->validate([
            'motivo' => 'required|string|max:500'
        ]);

        try {
            $venta = Venta::findOrFail($id);
            $resultado = $this->ventaService->cancelarVenta($venta, $request->motivo);

            return response()->json([
                'success' => true,
                'message' => 'Venta cancelada exitosamente',
                'data' => $resultado
            ]);
        } catch (\Exception $e) {
            Log::error('Error al cancelar venta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Obtener estadísticas de ventas
     */
    public function estadisticas(Request $request)
    {
        try {
            $fechaDesde = $request->fecha_desde ?? now()->startOfMonth();
            $fechaHasta = $request->fecha_hasta ?? now();

            $stats = [
                'total_ventas' => Venta::whereBetween('fecha_venta', [$fechaDesde, $fechaHasta])
                                      ->where('estado', 'completada')
                                      ->count(),
                'monto_total' => Venta::whereBetween('fecha_venta', [$fechaDesde, $fechaHasta])
                                     ->where('estado', 'completada')
                                     ->sum('precio_final'),
                'promedio_venta' => Venta::whereBetween('fecha_venta', [$fechaDesde, $fechaHasta])
                                        ->where('estado', 'completada')
                                        ->avg('precio_final'),
                'descuento_total' => Venta::whereBetween('fecha_venta', [$fechaDesde, $fechaHasta])
                                         ->where('estado', 'completada')
                                         ->sum('descuento'),
                'prendas_disponibles' => Prenda::where('estado', 'en_venta')->count()
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener estadísticas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar estadísticas'
            ], 500);
        }
    }

    /**
     * Debug: Ver prendas y sus estados (TEMPORAL - ELIMINAR EN PRODUCCIÓN)
     */
    public function debug()
    {
        $totalPrendas = Prenda::count();
        $prendasEnVenta = Prenda::where('estado', 'en_venta')->count();
        $prendasConValor = Prenda::where('estado', 'en_venta')
                                  ->whereNotNull('valor_venta')
                                  ->where('valor_venta', '>', 0)
                                  ->count();

        $ejemplos = Prenda::select('id', 'descripcion', 'estado', 'valor_venta')
                          ->take(20)
                          ->get();

        $prendasEnVentaDetalle = Prenda::where('estado', 'en_venta')
                                       ->with(['categoriaProducto', 'creditoPrendario.cliente'])
                                       ->get();

        return response()->json([
            'success' => true,
            'estadisticas' => [
                'total_prendas' => $totalPrendas,
                'prendas_en_venta' => $prendasEnVenta,
                'prendas_en_venta_con_valor' => $prendasConValor
            ],
            'ejemplos_prendas' => $ejemplos,
            'prendas_en_venta_detalle' => PrendaResource::collection($prendasEnVentaDetalle)
        ]);
    }
}
