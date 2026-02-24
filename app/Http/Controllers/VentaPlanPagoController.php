<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use App\Models\VentaCredito;
use App\Models\VentaCreditoPlanPago;
use App\Services\VentaPlanPagoService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;

/**
 * Controller para gestionar planes de pago de ventas a crédito
 * Endpoints REST para el módulo de React
 */
class VentaPlanPagoController extends Controller
{
    protected VentaPlanPagoService $planPagoService;

    public function __construct(VentaPlanPagoService $planPagoService)
    {
        $this->planPagoService = $planPagoService;
    }

    /**
     * POST /api/ventas/{id}/generar-plan
     * Generar plan de pagos para una venta
     *
     * Body: {
     *   enganche: 500,
     *   numero_cuotas: 6,
     *   tasa_interes: 5.0,
     *   frecuencia_pago: "mensual",
     *   fecha_primera_cuota: "2026-03-23" (opcional),
     *   dias_gracia: 0,
     *   tasa_mora: 2.0,
     *   caja_id: 1,
     *   observaciones: "Plan especial"
     * }
     */
    public function generarPlan(Request $request, string $id)
    {
        $request->validate([
            'enganche' => 'required|numeric|min:0',
            'numero_cuotas' => 'required|integer|min:1|max:60',
            'tasa_interes' => 'required|numeric|min:0|max:100',
            'frecuencia_pago' => 'required|in:semanal,quincenal,mensual',
            'fecha_primera_cuota' => 'nullable|date|after:today',
            'dias_gracia' => 'nullable|integer|min:0|max:30',
            'tasa_mora' => 'nullable|numeric|min:0|max:100',
            'caja_id' => 'required|exists:cajas,id',
            'observaciones' => 'nullable|string|max:500',
        ]);

        try {
            $venta = Venta::with(['cliente', 'detalles.prenda'])->findOrFail($id);

            $ventaCredito = $this->planPagoService->generarPlan($venta, $request->all());

            return response()->json([
                'success' => true,
                'message' => 'Plan de pagos generado exitosamente',
                'data' => [
                    'credito' => $ventaCredito,
                    'cuotas' => $ventaCredito->planPagos,
                ],
            ], 201);
        } catch (\Exception $e) {
            Log::error('Error al generar plan de pagos: ' . $e->getMessage(), [
                'venta_id' => $id,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * GET /api/ventas/apartados
     * Listar ventas con estado "apartado" (enganche pagado pero sin plan generado)
     */
    public function listarApartados(Request $request)
    {
        try {
            // Ventas en estado apartado que NO tienen plan generado
            $query = Venta::with(['cliente', 'detalles.prenda', 'vendedor', 'sucursal'])
                ->where('tipo_venta', 'apartado')
                ->where('estado', 'apartado')
                ->whereDoesntHave('ventaCredito')
                ->where('saldo_pendiente', '>', 0);

            // Filtros opcionales
            if ($request->has('cliente_id')) {
                $query->where('cliente_id', $request->cliente_id);
            }

            if ($request->has('sucursal_id')) {
                $query->where('sucursal_id', $request->sucursal_id);
            }

            if ($request->has('fecha_desde')) {
                $query->whereDate('fecha_venta', '>=', $request->fecha_desde);
            }

            if ($request->has('fecha_hasta')) {
                $query->whereDate('fecha_venta', '<=', $request->fecha_hasta);
            }

            if ($request->has('busqueda')) {
                $busqueda = $request->busqueda;
                $query->where(function ($q) use ($busqueda) {
                    $q->where('codigo_venta', 'like', "%{$busqueda}%")
                        ->orWhere('cliente_nombre', 'like', "%{$busqueda}%")
                        ->orWhereHas('cliente', function ($qq) use ($busqueda) {
                            $qq->where('nombre_completo', 'like', "%{$busqueda}%");
                        });
                });
            }

            $perPage = min((int) ($request->per_page ?? 15), 100);
            $apartados = $query->orderBy('fecha_venta', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => $apartados->items(),
                'pagination' => [
                    'current_page' => $apartados->currentPage(),
                    'last_page' => $apartados->lastPage(),
                    'per_page' => $apartados->perPage(),
                    'total' => $apartados->total(),
                    'from' => $apartados->firstItem(),
                    'to' => $apartados->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error al listar apartados: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar apartados',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * GET /api/ventas/planes-pago
     * Listar ventas con planes de pago activos (con cuotas generadas)
     */
    public function listarPlanesPago(Request $request)
    {
        try {
            $query = VentaCredito::with([
                'venta.cliente',
                'venta.detalles.prenda',
                'venta.vendedor',
                'venta.sucursal',
                'planPagos' => function ($q) {
                    $q->orderBy('numero_cuota');
                },
            ])
                ->whereIn('estado', ['vigente', 'en_mora', 'vencido'])
                ->where('saldo_actual', '>', 0);

            // Filtros opcionales
            if ($request->has('cliente_id')) {
                $query->where('cliente_id', $request->cliente_id);
            }

            if ($request->has('sucursal_id')) {
                $query->where('sucursal_id', $request->sucursal_id);
            }

            if ($request->has('estado')) {
                $query->where('estado', $request->estado);
            }

            if ($request->has('vencidos')) {
                $query->where('fecha_vencimiento', '<', now());
            }

            if ($request->has('por_vencer')) {
                $query->whereBetween('fecha_vencimiento', [now(), now()->addDays(7)]);
            }

            if ($request->has('busqueda')) {
                $busqueda = $request->busqueda;
                $query->where(function ($q) use ($busqueda) {
                    $q->where('numero_credito', 'like', "%{$busqueda}%")
                        ->orWhereHas('venta', function ($qq) use ($busqueda) {
                            $qq->where('codigo_venta', 'like', "%{$busqueda}%");
                        })
                        ->orWhereHas('cliente', function ($qq) use ($busqueda) {
                            $qq->where('nombre_completo', 'like', "%{$busqueda}%");
                        });
                });
            }

            $perPage = min((int) ($request->per_page ?? 15), 100);
            $planesPago = $query->orderBy('fecha_vencimiento', 'asc')->paginate($perPage);

            // Enriquecer con resumen
            $planesPago->getCollection()->transform(function ($ventaCredito) {
                return [
                    'id' => $ventaCredito->id,
                    'numero_credito' => $ventaCredito->numero_credito,
                    'estado' => $ventaCredito->estado,
                    'venta' => $ventaCredito->venta,
                    'cliente' => $ventaCredito->cliente,
                    'monto_venta' => $ventaCredito->monto_venta,
                    'enganche' => $ventaCredito->enganche,
                    'total_credito' => $ventaCredito->total_credito,
                    'saldo_actual' => $ventaCredito->saldo_actual,
                    'numero_cuotas' => $ventaCredito->numero_cuotas,
                    'cuotas_pagadas' => $ventaCredito->cuotas_pagadas,
                    'cuotas_pendientes' => $ventaCredito->numero_cuotas - $ventaCredito->cuotas_pagadas,
                    'monto_cuota' => $ventaCredito->monto_cuota,
                    'fecha_vencimiento' => $ventaCredito->fecha_vencimiento,
                    'fecha_ultimo_pago' => $ventaCredito->fecha_ultimo_pago,
                    'dias_mora' => $ventaCredito->dias_mora,
                    'mora_generada' => $ventaCredito->mora_generada,
                    'porcentaje_pagado' => $ventaCredito->numero_cuotas > 0
                        ? round(($ventaCredito->cuotas_pagadas / $ventaCredito->numero_cuotas) * 100, 2)
                        : 0,
                    'cuotas' => $ventaCredito->planPagos,
                    'created_at' => $ventaCredito->created_at,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $planesPago->items(),
                'pagination' => [
                    'current_page' => $planesPago->currentPage(),
                    'last_page' => $planesPago->lastPage(),
                    'per_page' => $planesPago->perPage(),
                    'total' => $planesPago->total(),
                    'from' => $planesPago->firstItem(),
                    'to' => $planesPago->lastItem(),
                ],
            ]);
        } catch (\Exception $e) {
            Log::error('Error al listar planes de pago: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar planes de pago',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * GET /api/ventas/{id}/plan-pago
     * Obtener detalle completo del plan de pagos de una venta
     */
    public function obtenerDetallePlan(string $id)
    {
        try {
            $venta = Venta::with(['cliente', 'vendedor', 'sucursal', 'detalles.prenda'])->findOrFail($id);

            $detalle = $this->planPagoService->obtenerDetallePlan($venta);

            if (!$detalle) {
                return response()->json([
                    'success' => false,
                    'message' => 'Esta venta no tiene plan de pagos generado',
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => $detalle,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener detalle de plan: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar detalle del plan',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }

    /**
     * POST /api/ventas/cuotas/{cuotaId}/pagar
     * Registrar pago a una cuota específica
     *
     * Body: {
     *   monto: 500,
     *   caja_id: 1,
     *   observacion: "Pago parcial cuota 2"
     * }
     */
    public function pagarCuota(Request $request, string $cuotaId)
    {
        $request->validate([
            'monto' => 'required|numeric|min:0.01',
            'caja_id' => 'required|exists:cajas,id',
            'observacion' => 'nullable|string|max:500',
        ]);

        try {
            $cuota = VentaCreditoPlanPago::with(['ventaCredito.venta'])->findOrFail($cuotaId);

            $resultado = $this->planPagoService->registrarPago($cuota, $request->all());

            return response()->json([
                'success' => true,
                'message' => $resultado['liquidado']
                    ? '¡Crédito liquidado completamente!'
                    : 'Pago registrado exitosamente',
                'data' => $resultado,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al registrar pago de cuota: ' . $e->getMessage(), [
                'cuota_id' => $cuotaId,
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    /**
     * GET /api/ventas/planes-pago/resumen
     * Obtener resumen general de planes de pago (estadísticas)
     */
    public function resumenGeneral(Request $request)
    {
        try {
            $sucursalId = $request->sucursal_id ?? Auth::user()->sucursal_id;

            $query = VentaCredito::query();

            if ($sucursalId) {
                $query->where('sucursal_id', $sucursalId);
            }

            $resumen = [
                'total_creditos_activos' => $query->clone()->whereIn('estado', ['vigente', 'en_mora'])->count(),
                'total_creditos_vencidos' => $query->clone()->where('estado', 'vencido')->count(),
                'total_creditos_pagados' => $query->clone()->where('estado', 'pagado')->count(),
                'monto_total_cartera' => $query->clone()->whereIn('estado', ['vigente', 'en_mora', 'vencido'])->sum('saldo_actual'),
                'monto_total_mora' => $query->clone()->whereIn('estado', ['vigente', 'en_mora', 'vencido'])->sum('mora_generada'),
                'cuotas_vencidas_total' => $query->clone()->whereIn('estado', ['vigente', 'en_mora', 'vencido'])->sum('cuotas_vencidas'),
            ];

            // Top clientes con mayor deuda
            $topDeudores = VentaCredito::with('cliente')
                ->select('cliente_id', DB::raw('SUM(saldo_actual) as deuda_total'))
                ->whereIn('estado', ['vigente', 'en_mora', 'vencido'])
                ->when($sucursalId, function ($q) use ($sucursalId) {
                    $q->where('sucursal_id', $sucursalId);
                })
                ->groupBy('cliente_id')
                ->orderBy('deuda_total', 'desc')
                ->limit(10)
                ->get();

            $resumen['top_deudores'] = $topDeudores;

            return response()->json([
                'success' => true,
                'data' => $resumen,
            ]);
        } catch (\Exception $e) {
            Log::error('Error al obtener resumen de planes de pago: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Error al cargar resumen',
                'error' => config('app.debug') ? $e->getMessage() : null,
            ], 500);
        }
    }
}
