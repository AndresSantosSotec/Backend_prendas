<?php

namespace App\Services;

use App\Models\CreditoPrendario;
use App\Models\CreditoPlanPago;
use App\Models\PlanInteresCategoria;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Servicio para recalcular y persistir la mora de un crédito.
 * Cuando una cuota está vencida (fecha_vencimiento + dias_gracia < hoy), se calcula la mora
 * según el tipo parametrizado en el crédito (porcentaje o monto fijo por día) y se actualiza
 * mora_proyectada, dias_mora y estado en cada cuota y en el crédito.
 */
class MoraService
{
    /**
     * Recalcula la mora de todas las cuotas vencidas del crédito y persiste los valores.
     * Debe llamarse antes de getPlanPagos y antes de ejecutar un pago para que la mora
     * aparezca correctamente y se cobre al pagar.
     *
     * @param CreditoPrendario $credito
     * @param Carbon|null $fechaCalculo Fecha de referencia (default: hoy)
     * @return array ['cuotas_actualizadas' => int, 'mora_total_generada' => float, 'dias_mora_max' => int]
     */
    public function recalcularMoraCredito(CreditoPrendario $credito, ?Carbon $fechaCalculo = null): array
    {
        $fechaCalculo = $fechaCalculo ?? Carbon::now()->startOfDay();
        $diasGracia = (int) ($credito->dias_gracia ?? 0);
        $tipoMora = $credito->tipo_mora ?? 'porcentaje';
        $tasaMora = (float) ($credito->tasa_mora ?? 0);
        $moraMontoFijo = (float) ($credito->mora_monto_fijo ?? 0);

        // Fallback: si tasa_mora es 0, intentar obtener del plan de interés asociado
        if ($tasaMora <= 0 && $tipoMora === 'porcentaje' && $credito->plan_interes_id) {
            $plan = PlanInteresCategoria::find($credito->plan_interes_id);
            if ($plan && (float)($plan->tasa_moratorios ?? 0) > 0) {
                $tasaMora = (float) $plan->tasa_moratorios;
            }
        }

        $cuotasActualizadas = 0;
        $moraTotalGenerada = 0.0;
        $diasMoraMax = 0;

        $cuotas = CreditoPlanPago::where('credito_prendario_id', $credito->id)
            ->whereIn('estado', ['pendiente', 'pagada_parcial', 'vencida', 'en_mora'])
            ->orderBy('numero_cuota')
            ->get();

        foreach ($cuotas as $cuota) {
            if (!$cuota->fecha_vencimiento || $fechaCalculo->lte($cuota->fecha_vencimiento)) {
                if ($cuota->dias_mora > 0 || $cuota->mora_proyectada > 0) {
                    $cuota->dias_mora = 0;
                    $cuota->mora_proyectada = 0;
                    $cuota->mora_pendiente = max(0, ($cuota->mora_proyectada ?? 0) - ($cuota->mora_pagada ?? 0));
                    $cuota->monto_pendiente = $cuota->capital_pendiente + $cuota->interes_pendiente + $cuota->mora_pendiente + ($cuota->otros_cargos_pendientes ?? 0);
                    $cuota->estado = in_array($cuota->estado, ['vencida', 'en_mora']) ? 'pendiente' : $cuota->estado;
                    $cuota->fecha_inicio_mora = null;
                    $cuota->save();
                    $cuotasActualizadas++;
                }
                continue;
            }

            $diasDesdeVencimiento = (int) abs($fechaCalculo->diffInDays($cuota->fecha_vencimiento));
            $diasMoraEfectivos = max(0, $diasDesdeVencimiento - $diasGracia);

            if ($diasMoraEfectivos <= 0) {
                if ($cuota->dias_mora > 0 || $cuota->mora_proyectada > 0) {
                    $cuota->dias_mora = 0;
                    $cuota->mora_proyectada = 0;
                    $cuota->mora_pendiente = max(0, 0 - ($cuota->mora_pagada ?? 0));
                    $cuota->monto_pendiente = $cuota->capital_pendiente + $cuota->interes_pendiente + $cuota->mora_pendiente + ($cuota->otros_cargos_pendientes ?? 0);
                    $cuota->estado = $cuota->estado === 'en_mora' ? 'vencida' : $cuota->estado;
                    $cuota->fecha_inicio_mora = null;
                    $cuota->save();
                    $cuotasActualizadas++;
                }
                continue;
            }

            $moraCalculada = $this->calcularMoraCuota(
                $credito,
                $cuota,
                $diasMoraEfectivos,
                $tipoMora,
                $tasaMora,
                $moraMontoFijo
            );

            $moraPendiente = max(0, $moraCalculada - ($cuota->mora_pagada ?? 0));
            $montoPendiente = (float) $cuota->capital_pendiente + (float) $cuota->interes_pendiente + $moraPendiente + (float) ($cuota->otros_cargos_pendientes ?? 0);

            $cuota->dias_mora = $diasMoraEfectivos;
            $cuota->mora_proyectada = round($moraCalculada, 2);
            $cuota->mora_pendiente = round($moraPendiente, 2);
            $cuota->monto_pendiente = round($montoPendiente, 2);
            $cuota->estado = 'en_mora';
            if (!$cuota->fecha_inicio_mora) {
                $cuota->fecha_inicio_mora = Carbon::parse($cuota->fecha_vencimiento)->addDays($diasGracia);
            }
            $cuota->save();

            $cuotasActualizadas++;
            $moraTotalGenerada += $moraCalculada;
            $diasMoraMax = max($diasMoraMax, $diasMoraEfectivos);
        }

        $sumMoraGenerada = CreditoPlanPago::where('credito_prendario_id', $credito->id)
            ->where('dias_mora', '>', 0)
            ->sum('mora_proyectada');

        $credito->mora_generada = round($sumMoraGenerada, 2);
        $credito->dias_mora = $diasMoraMax;
        if ($diasMoraMax > 0 && !in_array($credito->estado, ['vigente', 'vencido', 'en_mora'])) {
            // Solo cambiar a en_mora si el crédito está en un estado que permite mora
            if (in_array($credito->estado, ['vigente', 'vencido'])) {
                $credito->estado = 'en_mora';
            }
        }
        $credito->save();

        return [
            'cuotas_actualizadas' => $cuotasActualizadas,
            'mora_total_generada' => round($sumMoraGenerada, 2),
            'dias_mora_max' => $diasMoraMax,
        ];
    }

    /**
     * Calcula el monto de mora para una cuota según tipo (porcentaje o monto fijo).
     */
    public function calcularMoraCuota(
        CreditoPrendario $credito,
        CreditoPlanPago $cuota,
        int $diasMora,
        string $tipoMora,
        float $tasaMora,
        float $moraMontoFijo
    ): float {
        if ($diasMora <= 0) {
            return 0;
        }

        if ($tipoMora === 'monto_fijo' && $moraMontoFijo > 0) {
            return round($moraMontoFijo * $diasMora, 2);
        }

        if ($tipoMora === 'porcentaje' && $tasaMora > 0) {
            $tasaDiaria = ($tasaMora / 100) / 30;
            $base = (float) $credito->capital_pendiente;
            return round($base * $tasaDiaria * $diasMora, 2);
        }

        return 0;
    }
}
