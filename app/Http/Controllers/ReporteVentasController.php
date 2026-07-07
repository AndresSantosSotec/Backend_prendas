<?php

namespace App\Http\Controllers;

use App\Models\Venta;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Barryvdh\DomPDF\Facade\Pdf;

class ReporteVentasController extends Controller
{
    // ─── Helpers comunes ──────────────────────────────────────────────────────

    private function buildQuery(Request $request)
    {
        $query = Venta::with(['cliente', 'vendedor', 'sucursal', 'detalles.prenda', 'detalles.compra', 'prenda'])
            ->withoutTrashed();

        $fechaDesde = $request->fecha_desde ?? $request->fecha_inicio;
        $fechaHasta = $request->fecha_hasta ?? $request->fecha_fin;

        if ($fechaDesde) {
            $query->whereDate('created_at', '>=', $fechaDesde);
        }
        if ($fechaHasta) {
            $query->whereDate('created_at', '<=', $fechaHasta);
        }
        if ($request->sucursal_id) {
            $query->where('sucursal_id', $request->sucursal_id);
        }
        if ($request->estado && $request->estado !== 'all') {
            $query->where('estado', $request->estado);
        }
        if ($request->tipo_venta && $request->tipo_venta !== 'all') {
            $query->where('tipo_venta', $request->tipo_venta);
        }

        return $query->orderBy('created_at', 'desc');
    }

    private function calcularEstadisticas($ventas): array
    {
        $totalVentas      = $ventas->count();
        $totalIngresos    = $ventas->whereIn('estado', ['pagada', 'completada'])->sum('total_final');
        $totalDescuentos  = $ventas->sum('total_descuentos');
        $ventasContado    = $ventas->where('tipo_venta', 'contado')->count();
        $ventasCredito    = $ventas->where('tipo_venta', 'credito')->count();
        $ventasApartado   = $ventas->where('tipo_venta', 'apartado')->count();
        $ticketPromedio   = $totalVentas > 0 ? ($ventas->sum('total_final') / $totalVentas) : 0;

        return [
            'total_ventas'     => $totalVentas,
            'total_ingresos'   => round($totalIngresos, 2),
            'total_descuentos' => round($totalDescuentos, 2),
            'ticket_promedio'  => round($ticketPromedio, 2),
            'ventas_contado'   => $ventasContado,
            'ventas_credito'   => $ventasCredito,
            'ventas_apartado'  => $ventasApartado,
        ];
    }

    private function compilarArticulosVendidos($ventas): array
    {
        $items = [];
        foreach ($ventas as $venta) {
            $detalles = $venta->detalles;
            if ($detalles && $detalles->count() > 0) {
                foreach ($detalles as $detalle) {
                    $precioCompra = 0;
                    if ($detalle->prenda_id) {
                        $precioCompra = $detalle->prenda?->valor_prestamo ?? 0;
                    } elseif ($detalle->producto_id) {
                        $precioCompra = $detalle->compra?->monto_pagado ?? 0;
                    }

                    $precioVenta = $detalle->total;
                    $diferencia = $precioVenta - $precioCompra;
                    $porcentajeDiferencia = $precioCompra > 0 ? round(($diferencia / $precioCompra) * 100, 2) : 0;

                    $items[] = [
                        'codigo_venta'    => $venta->codigo_venta,
                        'tipo_venta'      => $venta->tipo_venta,
                        'estado'          => $venta->estado,
                        'cliente'         => $venta->cliente
                            ? trim(($venta->cliente->nombres ?? '') . ' ' . ($venta->cliente->apellidos ?? ''))
                            : ($venta->cliente_nombre ?? 'Consumidor final'),
                        'vendedor'        => $venta->vendedor ? $venta->vendedor->name : '-',
                        'sucursal'        => $venta->sucursal ? $venta->sucursal->nombre : '-',
                        'descripcion'     => $detalle->descripcion,
                        'precio_compra'   => (float)$precioCompra,
                        'precio_venta'    => (float)$precioVenta,
                        'utilidad'        => (float)$diferencia,
                        'porcentaje_diferencia' => (float)$porcentajeDiferencia,
                        'margen'          => (float)$porcentajeDiferencia,
                        'metodo_pago'     => $venta->metodo_pago,
                        'fecha_venta'     => $venta->created_at?->format('d/m/Y H:i'),
                    ];
                }
            } else {
                $precioCompra = 0;
                $descripcion = 'Venta de Prenda';
                if ($venta->prenda_id) {
                    $precioCompra = $venta->prenda?->valor_prestamo ?? 0;
                    $descripcion = $venta->prenda?->descripcion ?? $descripcion;
                }

                $precioVenta = $venta->precio_final;
                $diferencia = $precioVenta - $precioCompra;
                $porcentajeDiferencia = $precioCompra > 0 ? round(($diferencia / $precioCompra) * 100, 2) : 0;

                $items[] = [
                    'codigo_venta'    => $venta->codigo_venta,
                    'tipo_venta'      => $venta->tipo_venta,
                    'estado'          => $venta->estado,
                    'cliente'         => $venta->cliente
                        ? trim(($venta->cliente->nombres ?? '') . ' ' . ($venta->cliente->apellidos ?? ''))
                        : ($venta->cliente_nombre ?? 'Consumidor final'),
                    'vendedor'        => $venta->vendedor ? $venta->vendedor->name : '-',
                    'sucursal'        => $venta->sucursal ? $venta->sucursal->nombre : '-',
                    'descripcion'     => $descripcion,
                    'precio_compra'   => (float)$precioCompra,
                    'precio_venta'    => (float)$precioVenta,
                    'utilidad'        => (float)$diferencia,
                    'porcentaje_diferencia' => (float)$porcentajeDiferencia,
                    'margen'          => (float)$porcentajeDiferencia,
                    'metodo_pago'     => $venta->metodo_pago,
                    'fecha_venta'     => $venta->created_at?->format('d/m/Y H:i'),
                ];
            }
        }
        return $items;
    }

    // ─── Vista previa (JSON) ──────────────────────────────────────────────────

    public function vistaPrevia(Request $request)
    {
        $request->validate([
            'fecha_desde'  => 'nullable|date',
            'fecha_hasta'  => 'nullable|date',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin'    => 'nullable|date',
            'sucursal_id'  => 'nullable|integer',
            'estado'       => 'nullable|string',
            'tipo_venta'   => 'nullable|string',
        ]);

        $ventas = $this->buildQuery($request)->get();
        $items = $this->compilarArticulosVendidos($ventas);

        return response()->json([
            'success' => true,
            'data' => [
                'ventas'          => $items,
                'estadisticas'    => $this->calcularEstadisticas($ventas),
                'total_registros' => count($items),
            ],
        ]);
    }

    // ─── PDF ─────────────────────────────────────────────────────────────────

    public function generarPDF(Request $request)
    {
        $request->validate([
            'fecha_desde'  => 'nullable|date',
            'fecha_hasta'  => 'nullable|date',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin'    => 'nullable|date',
            'sucursal_id'  => 'nullable|integer',
            'estado'       => 'nullable|string',
            'tipo_venta'   => 'nullable|string',
        ]);

        $ventas = $this->buildQuery($request)->get();
        $items = $this->compilarArticulosVendidos($ventas);
        $estadisticas = $this->calcularEstadisticas($ventas);

        $fechaDesde = $request->fecha_desde ?? $request->fecha_inicio ?? 'N/A';
        $fechaHasta = $request->fecha_hasta ?? $request->fecha_fin ?? 'N/A';

        $totalCosto = 0;
        $totalVenta = 0;
        foreach ($items as $item) {
            $totalCosto += $item['precio_compra'];
            $totalVenta += $item['precio_venta'];
        }

        $totales = [
            'costo' => $totalCosto,
            'venta' => $totalVenta,
            'utilidad' => $totalVenta - $totalCosto,
            'margen' => $totalCosto > 0 ? round((($totalVenta - $totalCosto) / $totalCosto) * 100, 2) : 0,
        ];

        $html = view('reportes.ventas', [
            'ventas'       => $items,
            'estadisticas' => $estadisticas,
            'totales'      => $totales,
            'fecha_desde'  => $fechaDesde,
            'fecha_hasta'  => $fechaHasta,
            'generado_por' => Auth::user()->name ?? 'Sistema',
            'generado_en'  => now()->format('d/m/Y H:i'),
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('A4', 'landscape');

        return $pdf->download("reporte-ventas-{$fechaDesde}-{$fechaHasta}.pdf");
    }

    // ─── Excel (CSV) ──────────────────────────────────────────────────────────

    public function generarExcel(Request $request)
    {
        $request->validate([
            'fecha_desde'  => 'nullable|date',
            'fecha_hasta'  => 'nullable|date',
            'fecha_inicio' => 'nullable|date',
            'fecha_fin'    => 'nullable|date',
            'sucursal_id'  => 'nullable|integer',
            'estado'       => 'nullable|string',
            'tipo_venta'   => 'nullable|string',
        ]);

        $ventas = $this->buildQuery($request)->get();
        $items = $this->compilarArticulosVendidos($ventas);

        $fechaDesde = $request->fecha_desde ?? $request->fecha_inicio ?? 'completo';
        $fechaHasta = $request->fecha_hasta ?? $request->fecha_fin ?? now()->format('Y-m-d');

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"reporte-ventas-{$fechaDesde}-{$fechaHasta}.csv\"",
        ];

        $callback = function () use ($items) {
            $handle = fopen('php://output', 'w');

            // BOM para Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Cabeceras
            fputcsv($handle, [
                'Código Venta', 'Fecha', 'Cliente', 'Artículo (Descripción)', 'Precio Compra (Costo)', 'Precio Venta', 'Utilidad', '% Diferencia', 'Estado'
            ]);

            foreach ($items as $item) {
                fputcsv($handle, [
                    $item['codigo_venta'],
                    $item['fecha_venta'],
                    $item['cliente'],
                    $item['descripcion'],
                    number_format($item['precio_compra'], 2, '.', ''),
                    number_format($item['precio_venta'], 2, '.', ''),
                    number_format($item['utilidad'], 2, '.', ''),
                    number_format($item['margen'], 2, '.', '') . '%',
                    ucfirst($item['estado']),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
