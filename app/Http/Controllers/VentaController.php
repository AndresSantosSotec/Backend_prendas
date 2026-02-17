<?php

namespace App\Http\Controllers;

use App\Models\Prenda;
use App\Models\Venta;
use App\Services\VentaService;
use App\Services\VentaMultiPrendaService;
use App\Services\VentaCreditoService;
use App\Http\Resources\PrendaResource;
use App\Exports\VentasExport;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;

class VentaController extends Controller
{
    protected $ventaService;
    protected $ventaMultiPrendaService;
    protected $ventaCreditoService;

    public function __construct(
        VentaService $ventaService,
        VentaMultiPrendaService $ventaMultiPrendaService,
        VentaCreditoService $ventaCreditoService
    ) {
        $this->ventaService = $ventaService;
        $this->ventaMultiPrendaService = $ventaMultiPrendaService;
        $this->ventaCreditoService = $ventaCreditoService;
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
     * Procesar venta de prenda (DEPRECADO - usar store para multi-prenda)
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
     * Crear venta con múltiples prendas y múltiples pagos (NUEVO)
     * POST /ventas
     *
     * Body: {
     *   cliente_id: 1,
     *   tipo_venta: 'contado|apartado|plan_pago',
     *   items: [
     *     { prenda_id: 10, precio_unitario: 1500, descuento: 100 },
     *     { prenda_id: 11, precio_unitario: 500, descuento: 0 }
     *   ],
     *   pagos: [
     *     { metodo: 'efectivo', monto: 1000 },
     *     { metodo: 'tarjeta', monto: 900, referencia: 'TRX-123' }
     *   ]
     * }
     */
    public function store(Request $request)
    {
        $request->validate([
            'cliente_id' => 'nullable|exists:clientes,id',
            'consumidor_final' => 'nullable|boolean',
            'tipo_venta' => 'required|in:contado,credito,apartado,plan_pagos',
            'sucursal_id' => 'nullable|exists:sucursales,id',
            'tipo_comprobante' => 'nullable|in:FEL,RECIBO,NOTA',
            'items' => 'required|array|min:1',
            'items.*.prenda_id' => 'required|exists:prendas,id',
            'items.*.descuento' => 'nullable|numeric|min:0',
            'pagos' => 'nullable|array',
            'pagos.*.metodo' => 'required|in:efectivo,tarjeta,transferencia,cheque',
            'pagos.*.monto' => 'required|numeric|min:0',
            'pagos.*.referencia' => 'nullable|string|max:100',
            'observaciones' => 'nullable|string|max:1000',
            // Campos específicos para crédito
            'enganche' => 'nullable|numeric|min:0',
            'numero_cuotas' => 'nullable|integer|min:1|max:60',
            'tasa_interes_mensual' => 'nullable|numeric|min:0|max:100',
            // Campos específicos para apartado
            'anticipo' => 'nullable|numeric|min:0',
            'dias_apartado' => 'nullable|integer|min:1|max:365',
        ]);

        try {
            $venta = $this->ventaMultiPrendaService->crearVenta($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Venta creada exitosamente',
                'data' => $venta
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al crear venta multi-prenda: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);

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
            $query = Venta::with(['detalles.prenda', 'vendedor', 'sucursal', 'cliente']);

            // Filtros de estado extendidos
            if ($request->has('estado') && $request->estado !== 'todas') {
                if ($request->estado === 'devueltas') {
                    $query->where('estado', 'devuelta');
                } else {
                    $query->where('estado', $request->estado);
                }
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
                      ->orWhere('cliente_nit', 'like', "%{$busqueda}%")
                      ->orWhere('numero_documento', 'like', "%{$busqueda}%");
                });
            }

            // Validar rango de paginación (mínimo 10, máximo 100)
            $perPage = (int) $request->get('per_page', 10);
            $perPage = max(10, min(100, $perPage)); // Asegurar rango 10-100
            $ventas = $query->orderBy('fecha_venta', 'desc')->paginate($perPage);

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
            $venta = Venta::with([
                'detalles.prenda.categoriaProducto',
                'pagos',
                'creditoPrendario',
                'vendedor',
                'sucursal',
                'cliente',
                'moneda'
            ])->findOrFail($id);

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

            // Usar servicio apropiado según tipo de venta
            if ($venta->detalles()->count() > 0) {
                // Venta multi-prenda
                $resultado = $this->ventaMultiPrendaService->cancelarVenta($venta, $request->motivo);
            } else {
                // Venta antigua (1 prenda)
                $resultado = $this->ventaService->cancelarVenta($venta, $request->motivo);
            }

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
     * Registrar pago adicional (para apartados/planes de pago)
     * POST /ventas/{id}/pagos
     */
    public function registrarPago(Request $request, string $id)
    {
        $request->validate([
            'metodo' => 'required|in:efectivo,tarjeta,transferencia,cheque',
            'monto' => 'required|numeric|min:0',
            'referencia' => 'nullable|string|max:100',
        ]);

        try {
            $venta = Venta::findOrFail($id);
            $resultado = $this->ventaMultiPrendaService->registrarPagoAdicional($venta, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Pago registrado exitosamente',
                'data' => $resultado
            ]);
        } catch (\Exception $e) {
            Log::error('Error al registrar pago: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Certificar factura (FEL Guatemala)
     */
    public function certificar(string $id)
    {
        try {
            $venta = Venta::findOrFail($id);

            if ($venta->certificada) {
                throw new \Exception('Esta venta ya ha sido certificada');
            }

            // Simulación de certificación FEL
            $venta->certificada = true;
            $venta->no_autorizacion = strtoupper(bin2hex(random_bytes(16)));
            $venta->fecha_certificacion = now();
            $venta->generarNumeroDocumento();
            $venta->save();

            return response()->json([
                'success' => true,
                'message' => 'Factura certificada exitosamente',
                'data' => $venta
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Eliminar venta
     */
    public function destroy(string $id)
    {
        try {
            $venta = Venta::findOrFail($id);

            if ($venta->certificada) {
                throw new \Exception('No se puede eliminar una venta ya certificada. Debe anularla.');
            }

            $venta->delete();

            return response()->json([
                'success' => true,
                'message' => 'Venta eliminada exitosamente'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Exportar reporte PDF de una venta
     */
    public function generarPDF(string $id)
    {
        try {
            $venta = Venta::with(['detalles.prenda', 'pagos.metodoPago', 'vendedor', 'sucursal', 'cliente', 'moneda'])->findOrFail($id);

            $pdf = Pdf::loadView('reports.venta', compact('venta'));

            return $pdf->stream("Venta_{$venta->codigo_venta}.pdf");
        } catch (\Exception $e) {
            Log::error('Error al generar PDF de venta: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al generar el PDF: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar listado a Excel
     */
    public function exportarExcel(Request $request)
    {
        try {
            $filtros = $request->all();
            return Excel::download(new VentasExport($filtros), 'Listado_Ventas_' . date('Ymd_His') . '.xlsx');
        } catch (\Exception $e) {
            Log::error('Error al exportar Excel de ventas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar a Excel: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Exportar listado a PDF
     */
    public function exportarPDF(Request $request)
    {
        try {
            $filtros = $request->all();

            $query = Venta::with(['cliente', 'vendedor', 'sucursal']);

            if (!empty($filtros['estado']) && $filtros['estado'] !== 'todas') {
                $query->where('estado', $filtros['estado']);
            }

            if (!empty($filtros['busqueda'])) {
                $busqueda = $filtros['busqueda'];
                $query->where(function($q) use ($busqueda) {
                    $q->where('codigo_venta', 'like', "%{$busqueda}%")
                      ->orWhere('cliente_nombre', 'like', "%{$busqueda}%")
                      ->orWhere('cliente_nit', 'like', "%{$busqueda}%");
                });
            }

            if (!empty($filtros['fecha_desde'])) {
                $query->whereDate('fecha_venta', '>=', $filtros['fecha_desde']);
            }

            if (!empty($filtros['fecha_hasta'])) {
                $query->whereDate('fecha_venta', '<=', $filtros['fecha_hasta']);
            }

            $ventas = $query->orderBy('fecha_venta', 'desc')->get();

            $totales = [
                'subtotal' => $ventas->sum('subtotal'),
                'descuentos' => $ventas->sum('total_descuentos'),
                'total' => $ventas->sum('total_final'),
            ];

            $pdf = Pdf::loadView('reports.ventas-listado', compact('ventas', 'filtros', 'totales'));
            $pdf->setPaper('letter', 'landscape');

            return $pdf->download('Listado_Ventas_' . date('Ymd_His') . '.pdf');
        } catch (\Exception $e) {
            Log::error('Error al exportar PDF de ventas: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al exportar a PDF: ' . $e->getMessage()
            ], 500);
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
                                      ->where('estado', 'pagada')
                                      ->count(),
                'monto_total' => Venta::whereBetween('fecha_venta', [$fechaDesde, $fechaHasta])
                                     ->where('estado', 'pagada')
                                     ->sum('total_final'),
                'promedio_venta' => Venta::whereBetween('fecha_venta', [$fechaDesde, $fechaHasta])
                                        ->where('estado', 'pagada')
                                        ->avg('total_final'),
                'descuento_total' => Venta::whereBetween('fecha_venta', [$fechaDesde, $fechaHasta])
                                         ->where('estado', 'pagada')
                                         ->sum('total_descuentos'),
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

    /**
     * Registrar abono/pago a venta a crédito
     * POST /ventas/{id}/abonos
     */
    public function registrarAbono(Request $request, string $id)
    {
        $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'metodo' => 'required|in:efectivo,tarjeta,transferencia,cheque',
            'referencia' => 'nullable|string|max:100',
            'banco' => 'nullable|string|max:100',
            'observaciones' => 'nullable|string|max:500'
        ]);

        try {
            $venta = Venta::with(['detalles.prenda', 'pagos'])->findOrFail($id);

            $resultado = $this->ventaCreditoService->registrarAbono($venta, $request->all());

            return response()->json([
                'success' => true,
                'message' => $resultado['liquidada']
                    ? 'Venta liquidada completamente'
                    : 'Abono registrado exitosamente',
                'data' => $resultado
            ]);
        } catch (\Exception $e) {
            Log::error('Error al registrar abono: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Obtener resumen de pagos de una venta
     * GET /ventas/{id}/resumen-pagos
     */
    public function resumenPagos(string $id)
    {
        try {
            $venta = Venta::with('pagos.metodoPago')->findOrFail($id);
            $resumen = $this->ventaCreditoService->obtenerResumenPagos($venta);

            return response()->json([
                'success' => true,
                'data' => $resumen
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    /**
     * Listar ventas pendientes de pago (apartados y plan de pagos)
     * GET /ventas/pendientes-pago
     */
    public function ventasPendientesPago(Request $request)
    {
        try {
            $query = Venta::with(['cliente', 'detalles.prenda', 'vendedor'])
                ->whereIn('tipo_venta', ['apartado', 'plan_pagos'])
                ->whereNotIn('estado', ['pagada', 'cancelada', 'anulada'])
                ->where('saldo_pendiente', '>', 0);

            // Filtros
            if ($request->has('cliente_id')) {
                $query->where('cliente_id', $request->cliente_id);
            }

            if ($request->has('vencidas')) {
                $query->where('fecha_vencimiento', '<', now());
            }

            if ($request->has('por_vencer')) {
                $query->whereBetween('fecha_vencimiento', [now(), now()->addDays(7)]);
            }

            // Validar rango de paginación (mínimo 10, máximo 100)
            $perPage = (int) ($request->per_page ?? 10);
            $perPage = max(10, min(100, $perPage)); // Asegurar rango 10-100

            $ventas = $query->orderBy('fecha_vencimiento', 'asc')
                ->paginate($perPage);

            // Agregar resumen a cada venta
            $ventas->getCollection()->transform(function ($venta) {
                $venta->resumen_pagos = $this->ventaCreditoService->obtenerResumenPagos($venta);
                return $venta;
            });

            return response()->json([
                'success' => true,
                'data' => $ventas->items(),
                'pagination' => [
                    'current_page' => $ventas->currentPage(),
                    'last_page' => $ventas->lastPage(),
                    'per_page' => $ventas->perPage(),
                    'total' => $ventas->total()
                ]
            ]);
        } catch (\Exception $e) {
            Log::error('Error al listar ventas pendientes: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error al cargar ventas pendientes'
            ], 500);
        }
    }
}

