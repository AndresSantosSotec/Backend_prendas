<?php

namespace App\Http\Controllers;

use App\Models\CreditoPrendario;
use App\Services\PagoService;
use App\Exports\CreditosVigentesExport;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReporteCreditosController extends Controller
{
    private const ESTADOS_FINALES = [
        'pagado',
        'cancelado',
        'incobrable',
        'liquidado',
        'rematado',
        'vendido',
        'recuperado',
        'rescatado',
        'anulado',
        'rechazado',
    ];

    public function __construct(private readonly PagoService $pagoService)
    {
    }

    private function validateRequest(Request $request): array
    {
        return $request->validate([
            'fecha_corte' => 'nullable|date',
            'fecha_hasta' => 'nullable|date',
            'fecha_fin' => 'nullable|date',
            'fecha_desde' => 'nullable|date',
            'fecha_inicio' => 'nullable|date',
            'sucursal_id' => 'nullable|integer',
        ]);
    }

    private function resolveFechaCorte(Request $request): Carbon
    {
        $fecha = $request->fecha_corte
            ?? $request->fecha_hasta
            ?? $request->fecha_fin
            ?? now()->toDateString();

        return Carbon::parse($fecha)->endOfDay();
    }

    private function buildQuery(Request $request, Carbon $fechaCorte)
    {
        $query = CreditoPrendario::with([
            'cliente:id,nombres,apellidos',
            'sucursal:id,nombre',
            'prendas:id,credito_prendario_id,descripcion',
            'prendas.ventaDetalles' => fn ($vd) => $vd
                ->whereHas('venta', fn ($v) => $v->whereIn('estado', ['pagada', 'completada', 'plan_pagos'])
                    ->whereDate('fecha_venta', '<=', $fechaCorte->toDateString()))
                ->with(['venta:id,estado,fecha_venta,total_final,codigo_venta']),
            'prendas.venta' => fn ($v) => $v->whereIn('estado', ['pagada', 'completada', 'plan_pagos'])
                ->whereDate('fecha_venta', '<=', $fechaCorte->toDateString()),
            'planPagos' => fn ($planPagos) => $planPagos->orderBy('numero_cuota'),
            'movimientos' => fn ($movimientos) => $movimientos
                ->where('estado', 'activo')
                ->whereDate('fecha_movimiento', '<=', $fechaCorte->toDateString())
                ->orderBy('fecha_movimiento', 'asc')
                ->orderBy('id', 'asc'),
        ])
            ->whereNotNull('fecha_desembolso')
            ->whereDate('fecha_desembolso', '<=', $fechaCorte->toDateString())
            ->withoutTrashed();

        $fechaDesde = $request->fecha_desde ?? $request->fecha_inicio;
        if ($fechaDesde) {
            $query->whereDate('fecha_desembolso', '>=', $fechaDesde);
        }

        if ($request->sucursal_id) {
            $query->where('sucursal_id', $request->sucursal_id);
        }

        return $query->orderBy('fecha_desembolso', 'desc')->orderBy('id', 'desc');
    }

    private function resolveFechaFinalizacion(CreditoPrendario $credito): ?Carbon
    {
        if ($credito->estado === 'pagado' && $credito->fecha_ultimo_pago) {
            return Carbon::parse($credito->fecha_ultimo_pago)->endOfDay();
        }

        if ($credito->fecha_cancelacion) {
            return Carbon::parse($credito->fecha_cancelacion)->endOfDay();
        }

        if ($credito->fecha_incobrable) {
            return Carbon::parse($credito->fecha_incobrable)->endOfDay();
        }

        if (in_array($credito->estado, ['vendido', 'recuperado', 'rescatado', 'anulado', 'rechazado', 'liquidado', 'rematado'], true)) {
            return $credito->updated_at ? Carbon::parse($credito->updated_at)->endOfDay() : null;
        }

        return null;
    }

    private function fueFinalizadoAntesDeCorte(CreditoPrendario $credito, Carbon $fechaCorte): bool
    {
        if (!in_array($credito->estado, self::ESTADOS_FINALES, true)) {
            return false;
        }

        $fechaFinalizacion = $this->resolveFechaFinalizacion($credito);

        return $fechaFinalizacion ? $fechaFinalizacion->lte($fechaCorte) : false;
    }

    private function calcularInteresGenerado(CreditoPrendario $credito, Carbon $fechaCorte, float $interesCobrado): float
    {
        if ($credito->planPagos->isNotEmpty()) {
            return (float) $credito->planPagos
                ->sum(fn ($cuota) => (float) ($cuota->interes_proyectado ?? 0));
        }

        return (float) ($credito->interes_generado ?? 0);
    }

    private function calcularEstadoCorte(CreditoPrendario $credito, Carbon $fechaCorte, float $capitalPendiente): string
    {
        if ($capitalPendiente <= 0) {
            return 'pagado';
        }

        $tieneCuotasVencidas = $credito->planPagos->contains(function ($cuota) use ($fechaCorte) {
            if (!$cuota->fecha_vencimiento || !$cuota->fecha_vencimiento->lte($fechaCorte)) {
                return false;
            }

            return ((float) ($cuota->monto_pendiente ?? 0)) > 0
                || in_array($cuota->estado, ['pendiente', 'vencida', 'en_mora', 'pagada_parcial'], true);
        });

        if ($tieneCuotasVencidas) {
            return $credito->estado === 'en_mora' ? 'en_mora' : 'vencido';
        }

        if ($credito->fecha_vencimiento && $credito->fecha_vencimiento->lt($fechaCorte)) {
            return 'vencido';
        }

        return 'vigente';
    }

    private function compilarCreditos($creditos, Carbon $fechaCorte): array
    {
        $items = [];

        foreach ($creditos as $credito) {
            if ($this->fueFinalizadoAntesDeCorte($credito, $fechaCorte)) {
                continue;
            }

            $montoOtorgado = (float) ($credito->monto_desembolsado ?: $credito->monto_aprobado ?: $credito->monto_solicitado ?: 0);

            // Recuperación de capital por medio de ventas de prendas del crédito
            $recuperadoVentasDetalles = (float) $credito->prendas->sum(function ($prenda) {
                return $prenda->ventaDetalles->sum(fn ($d) => (float) ($d->total ?? 0));
            });
            $recuperadoVentasDirectas = (float) $credito->prendas->sum(function ($prenda) {
                if ($prenda->venta && $prenda->ventaDetalles->isEmpty()) {
                    return (float) ($prenda->venta->precio_final ?: $prenda->venta->total_final ?: 0);
                }
                return 0;
            });
            $recuperadoVentas = round($recuperadoVentasDetalles + $recuperadoVentasDirectas, 2);

            $capitalCobrado = (float) $credito->movimientos->sum(fn ($mov) => (float) ($mov->capital ?? 0));
            $interesCobrado = (float) $credito->movimientos->sum(fn ($mov) => (float) ($mov->interes ?? 0));
            $moraCobrada = (float) $credito->movimientos->sum(fn ($mov) => (float) ($mov->mora ?? 0));
            $otrosCobrados = (float) $credito->movimientos->sum(fn ($mov) => (float) ($mov->otros_cargos ?? 0));
            $totalCobradoMovimientos = (float) $credito->movimientos->sum(fn ($mov) => (float) ($mov->monto_total ?? 0));

            // El capital pendiente descuenta tanto los abonos de clientes como las ventas de prendas en garantía
            $capitalPendiente = round(max(0, $montoOtorgado - $capitalCobrado - $recuperadoVentas), 2);
            $interesGenerado = round(max(0, $this->calcularInteresGenerado($credito, $fechaCorte, $interesCobrado)), 2);
            $interesCobrado = round(max(0, $interesCobrado), 2);
            $interesPendiente = round(max(0, $interesGenerado - $interesCobrado), 2);
            $totalCobrado = round(max(0, $totalCobradoMovimientos + $recuperadoVentas), 2);

            if ($capitalPendiente <= 0) {
                continue;
            }

            $descripcionPrendas = $credito->prendas
                ->pluck('descripcion')
                ->filter()
                ->implode(' | ');

            $estadoCorte = $this->calcularEstadoCorte($credito, $fechaCorte, $capitalPendiente);

            $items[] = [
                'numero_credito' => $credito->numero_credito,
                'cliente' => trim(($credito->cliente->nombres ?? '') . ' ' . ($credito->cliente->apellidos ?? '')) ?: 'Cliente sin nombre',
                'sucursal' => $credito->sucursal?->nombre ?? '-',
                'estado_corte' => $estadoCorte,
                'fecha_desembolso' => $credito->fecha_desembolso?->format('d/m/Y'),
                'fecha_vencimiento' => $credito->fecha_vencimiento?->format('d/m/Y'),
                'articulos' => $descripcionPrendas ?: '-',
                'monto_otorgado' => round($montoOtorgado, 2),
                'capital_cobrado' => round($capitalCobrado, 2),
                'recuperado_ventas' => $recuperadoVentas,
                'capital_pendiente' => $capitalPendiente,
                'interes_generado' => $interesGenerado,
                'interes_cobrado' => $interesCobrado,
                'interes_pendiente' => $interesPendiente,
                'mora_cobrada' => round(max(0, $moraCobrada), 2),
                'otros_cobrados' => round(max(0, $otrosCobrados), 2),
                'total_cobrado' => $totalCobrado,
            ];
        }

        return $items;
    }

    private function calcularEstadisticas(array $items): array
    {
        return [
            'total_creditos' => count($items),
            'total_otorgado' => round(array_sum(array_column($items, 'monto_otorgado')), 2),
            'capital_cobrado' => round(array_sum(array_column($items, 'capital_cobrado')), 2),
            'recuperado_ventas' => round(array_sum(array_column($items, 'recuperado_ventas')), 2),
            'capital_pendiente' => round(array_sum(array_column($items, 'capital_pendiente')), 2),
            'interes_generado' => round(array_sum(array_column($items, 'interes_generado')), 2),
            'interes_cobrado' => round(array_sum(array_column($items, 'interes_cobrado')), 2),
            'interes_pendiente' => round(array_sum(array_column($items, 'interes_pendiente')), 2),
            'total_cobrado' => round(array_sum(array_column($items, 'total_cobrado')), 2),
        ];
    }

    public function vistaPrevia(Request $request)
    {
        $this->validateRequest($request);

        $fechaCorte = $this->resolveFechaCorte($request);
        $creditos = $this->buildQuery($request, $fechaCorte)->get();
        $items = $this->compilarCreditos($creditos, $fechaCorte);

        return response()->json([
            'success' => true,
            'data' => [
                'creditos' => $items,
                'estadisticas' => $this->calcularEstadisticas($items),
                'fecha_corte' => $fechaCorte->format('Y-m-d'),
                'total_registros' => count($items),
            ],
        ]);
    }

    public function generarPDF(Request $request)
    {
        $this->validateRequest($request);

        $fechaCorte = $this->resolveFechaCorte($request);
        $creditos = $this->buildQuery($request, $fechaCorte)->get();
        $items = $this->compilarCreditos($creditos, $fechaCorte);
        $estadisticas = $this->calcularEstadisticas($items);

        $html = view('reportes.creditos-vigentes', [
            'creditos' => $items,
            'estadisticas' => $estadisticas,
            'fecha_corte' => $fechaCorte->format('d/m/Y'),
            'generado_por' => Auth::user()->name ?? 'Sistema',
            'generado_en' => now()->format('d/m/Y H:i'),
        ])->render();

        $pdf = Pdf::loadHTML($html)->setPaper('A4', 'landscape');

        return $pdf->download('reporte-creditos-vigentes-' . $fechaCorte->format('Y-m-d') . '.pdf');
    }

    public function generarExcel(Request $request)
    {
        $this->validateRequest($request);

        $fechaCorte = $this->resolveFechaCorte($request);
        $creditos = $this->buildQuery($request, $fechaCorte)->get();
        $items = $this->compilarCreditos($creditos, $fechaCorte);
        $estadisticas = $this->calcularEstadisticas($items);

        $usuario = Auth::user()?->name ?? 'Sistema';
        $fileName = 'Reporte_Creditos_Vigentes_' . $fechaCorte->format('Y-m-d') . '.xlsx';

        return Excel::download(
            new CreditosVigentesExport($items, $estadisticas, $fechaCorte->format('d/m/Y'), $usuario),
            $fileName,
            \Maatwebsite\Excel\Excel::XLSX
        );
    }
}