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
        $query = Venta::with(['cliente', 'vendedor', 'sucursal', 'detalles'])
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

        $ventasFormateadas = $ventas->map(function ($v) {
            return [
                'codigo_venta'    => $v->codigo_venta,
                'tipo_venta'      => $v->tipo_venta,
                'estado'          => $v->estado,
                'cliente'         => $v->cliente
                    ? trim(($v->cliente->nombres ?? '') . ' ' . ($v->cliente->apellidos ?? ''))
                    : ($v->cliente_nombre ?? 'Consumidor final'),
                'vendedor'        => $v->vendedor ? $v->vendedor->name : '-',
                'sucursal'        => $v->sucursal ? $v->sucursal->nombre : '-',
                'total_final'     => (float) $v->total_final,
                'total_descuentos'=> (float) $v->total_descuentos,
                'total_pagado'    => (float) $v->total_pagado,
                'saldo_pendiente' => (float) ($v->saldo_pendiente ?? 0),
                'metodo_pago'     => $v->metodo_pago,
                'fecha_venta'     => $v->created_at?->format('Y-m-d H:i'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => [
                'ventas'          => $ventasFormateadas,
                'estadisticas'    => $this->calcularEstadisticas($ventas),
                'total_registros' => $ventas->count(),
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
        $estadisticas = $this->calcularEstadisticas($ventas);

        $fechaDesde = $request->fecha_desde ?? $request->fecha_inicio ?? 'N/A';
        $fechaHasta = $request->fecha_hasta ?? $request->fecha_fin ?? 'N/A';

        $ventasFormateadas = $ventas->map(function ($v) {
            return [
                'codigo_venta'    => $v->codigo_venta,
                'tipo_venta'      => $v->tipo_venta,
                'estado'          => $v->estado,
                'cliente'         => $v->cliente
                    ? trim(($v->cliente->nombres ?? '') . ' ' . ($v->cliente->apellidos ?? ''))
                    : ($v->cliente_nombre ?? 'Consumidor final'),
                'vendedor'        => $v->vendedor ? $v->vendedor->name : '-',
                'total_final'     => (float) $v->total_final,
                'total_descuentos'=> (float) $v->total_descuentos,
                'metodo_pago'     => $v->metodo_pago,
                'fecha_venta'     => $v->created_at?->format('d/m/Y H:i'),
            ];
        });

        $html = view('reportes.ventas', [
            'ventas'       => $ventasFormateadas,
            'estadisticas' => $estadisticas,
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

        $fechaDesde = $request->fecha_desde ?? $request->fecha_inicio ?? 'completo';
        $fechaHasta = $request->fecha_hasta ?? $request->fecha_fin ?? now()->format('Y-m-d');

        $headers = [
            'Content-Type'        => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"reporte-ventas-{$fechaDesde}-{$fechaHasta}.csv\"",
        ];

        $callback = function () use ($ventas) {
            $handle = fopen('php://output', 'w');

            // BOM para Excel
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            // Cabeceras
            fputcsv($handle, [
                'Código Venta', 'Tipo Venta', 'Estado', 'Cliente', 'Vendedor',
                'Sucursal', 'Total Final', 'Descuentos', 'Total Pagado',
                'Saldo Pendiente', 'Método Pago', 'Fecha Venta',
            ]);

            foreach ($ventas as $v) {
                fputcsv($handle, [
                    $v->codigo_venta,
                    $v->tipo_venta,
                    $v->estado,
                    $v->cliente
                        ? trim(($v->cliente->nombres ?? '') . ' ' . ($v->cliente->apellidos ?? ''))
                        : ($v->cliente_nombre ?? 'Consumidor final'),
                    $v->vendedor ? $v->vendedor->name : '-',
                    $v->sucursal ? $v->sucursal->nombre : '-',
                    number_format((float) $v->total_final, 2),
                    number_format((float) $v->total_descuentos, 2),
                    number_format((float) $v->total_pagado, 2),
                    number_format((float) ($v->saldo_pendiente ?? 0), 2),
                    $v->metodo_pago,
                    $v->created_at?->format('d/m/Y H:i'),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
