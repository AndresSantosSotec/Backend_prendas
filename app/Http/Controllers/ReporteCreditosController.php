<?php

namespace App\Http\Controllers;

use App\Models\CreditoPrendario;
use App\Services\PagoService;
use Barryvdh\DomPDF\Facade\Pdf;
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
            'planPagos' => fn ($planPagos) => $planPagos->orderBy('numero_cuota'),
            'movimientos' => fn ($movimientos) => $movimientos
                ->where('estado', 'activo')
                ->whereDate('fecha_movimiento', '<=', $fechaCorte->toDateString())
                ->orderBy('fecha_movimiento', 'asc')
                ->orderBy('id', 'asc'),
            'remates' => fn ($remates) => $remates->orderByDesc('fecha_remate'),
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

        if ($credito->estado === 'rematado' && $credito->remates->isNotEmpty()) {
            $fechaRemate = $credito->remates->first()?->fecha_remate;
            if ($fechaRemate) {
                return Carbon::parse($fechaRemate)->endOfDay();
            }
        }

        if (in_array($credito->estado, ['vendido', 'recuperado', 'rescatado', 'anulado', 'rechazado', 'liquidado'], true)) {
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
                ->filter(fn ($cuota) => $cuota->fecha_vencimiento && $cuota->fecha_vencimiento->lte($fechaCorte))
                ->sum(fn ($cuota) => (float) ($cuota->interes_proyectado ?? 0));
        }

        $calculo = $this->pagoService->calcularDeudaAlDia($credito, $fechaCorte->copy());
        $interesPendiente = (float) ($calculo['interes_acumulado'] ?? 0);

        return $interesCobrado + $interesPendiente;
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

            $capitalCobrado = (float) $credito->movimientos->sum(fn ($mov) => (float) ($mov->capital ?? 0));
            $interesCobrado = (float) $credito->movimientos->sum(fn ($mov) => (float) ($mov->interes ?? 0));
            $moraCobrada = (float) $credito->movimientos->sum(fn ($mov) => (float) ($mov->mora ?? 0));
            $otrosCobrados = (float) $credito->movimientos->sum(fn ($mov) => (float) ($mov->otros_cargos ?? 0));
            $totalCobrado = (float) $credito->movimientos->sum(fn ($mov) => (float) ($mov->monto_total ?? 0));

            $capitalPendiente = round(max(0, $montoOtorgado - $capitalCobrado), 2);
            $interesGenerado = round(max(0, $this->calcularInteresGenerado($credito, $fechaCorte, $interesCobrado)), 2);
            $interesCobrado = round(max(0, $interesCobrado), 2);
            $interesPendiente = round(max(0, $interesGenerado - $interesCobrado), 2);

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
                'capital_pendiente' => $capitalPendiente,
                'interes_generado' => $interesGenerado,
                'interes_cobrado' => $interesCobrado,
                'interes_pendiente' => $interesPendiente,
                'mora_cobrada' => round(max(0, $moraCobrada), 2),
                'otros_cobrados' => round(max(0, $otrosCobrados), 2),
                'total_cobrado' => round(max(0, $totalCobrado), 2),
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

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="reporte-creditos-vigentes-' . $fechaCorte->format('Y-m-d') . '.csv"',
        ];

        $callback = function () use ($items) {
            $handle = fopen('php://output', 'w');

            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            fputcsv($handle, [
                'No. Crédito',
                'Cliente',
                'Sucursal',
                'Estado al corte',
                'Fecha desembolso',
                'Fecha vencimiento',
                'Artículos',
                'Monto otorgado',
                'Capital cobrado',
                'Capital pendiente',
                'Interés generado',
                'Interés cobrado',
                'Interés pendiente',
                'Total cobrado',
            ]);

            foreach ($items as $item) {
                fputcsv($handle, [
                    $item['numero_credito'],
                    $item['cliente'],
                    $item['sucursal'],
                    ucfirst(str_replace('_', ' ', $item['estado_corte'])),
                    $item['fecha_desembolso'],
                    $item['fecha_vencimiento'],
                    $item['articulos'],
                    number_format($item['monto_otorgado'], 2, '.', ''),
                    number_format($item['capital_cobrado'], 2, '.', ''),
                    number_format($item['capital_pendiente'], 2, '.', ''),
                    number_format($item['interes_generado'], 2, '.', ''),
                    number_format($item['interes_cobrado'], 2, '.', ''),
                    number_format($item['interes_pendiente'], 2, '.', ''),
                    number_format($item['total_cobrado'], 2, '.', ''),
                ]);
            }

            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}