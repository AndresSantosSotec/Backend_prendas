<?php

namespace App\Console\Commands;

use App\Models\CreditoPrendario;
use App\Models\CreditoPlanPago;
use Illuminate\Console\Command;

class VerificarFechasPlanPagos extends Command
{
    protected $signature = 'creditos:verificar-fechas {--credito= : ID del crédito}';
    protected $description = 'Verifica las fechas del plan de pagos del último crédito o un crédito específico';

    public function handle()
    {
        $creditoId = $this->option('credito');

        if ($creditoId) {
            $credito = CreditoPrendario::find($creditoId);
        } else {
            $credito = CreditoPrendario::latest()->first();
        }

        if (!$credito) {
            $this->error('No se encontró ningún crédito');
            return 1;
        }

        $this->info("=== VERIFICACIÓN DE FECHAS ===");
        $this->info("Crédito: {$credito->numero_credito} (ID: {$credito->id})");
        $this->info("Estado: {$credito->estado}");
        $this->line('');

        $this->info("--- Fechas del Crédito ---");
        $this->table(
            ['Campo', 'Valor'],
            [
                ['fecha_solicitud', $credito->fecha_solicitud?->format('d/m/Y')],
                ['fecha_desembolso', $credito->fecha_desembolso?->format('d/m/Y')],
                ['fecha_primer_pago', $credito->fecha_primer_pago?->format('d/m/Y') ?? 'NULL'],
                ['fecha_vencimiento', $credito->fecha_vencimiento?->format('d/m/Y')],
            ]
        );

        $plan = CreditoPlanPago::where('credito_prendario_id', $credito->id)
            ->orderBy('numero_cuota')
            ->get();

        if ($plan->isEmpty()) {
            $this->warn('No hay plan de pagos generado para este crédito');
            return 0;
        }

        $this->line('');
        $this->info("--- Plan de Pagos ---");
        $this->table(
            ['Cuota', 'Fecha Vencimiento', 'Estado', 'Capital', 'Interés', 'Total'],
            $plan->map(function ($cuota) {
                return [
                    $cuota->numero_cuota,
                    $cuota->fecha_vencimiento->format('d/m/Y'),
                    $cuota->estado,
                    'Q ' . number_format($cuota->capital_proyectado, 2),
                    'Q ' . number_format($cuota->interes_proyectado, 2),
                    'Q ' . number_format($cuota->monto_cuota_proyectado, 2),
                ];
            })->toArray()
        );

        $this->line('');
        if (!$credito->fecha_primer_pago) {
            $this->error('⚠️  PROBLEMA DETECTADO:');
            $this->error('   fecha_primer_pago es NULL en el crédito');
            $this->error('   Esto causa que se use fecha_desembolso para generar el plan');
            $this->line('');
            $this->info('SOLUCIÓN:');
            $this->info('   1. Verificar que el frontend esté enviando "fecha_primer_pago"');
            $this->info('   2. Revisar logs en storage/logs/laravel.log');
            $this->info('   3. Buscar: "No se recibió fecha_primer_pago en el request"');
        } else {
            $primeraCuota = $plan->first();
            if ($primeraCuota && $primeraCuota->fecha_vencimiento->format('Y-m-d') !== $credito->fecha_primer_pago->format('Y-m-d')) {
                $this->error('⚠️  PROBLEMA DETECTADO:');
                $this->error("   fecha_primer_pago del crédito: {$credito->fecha_primer_pago->format('d/m/Y')}");
                $this->error("   Primera cuota del plan: {$primeraCuota->fecha_vencimiento->format('d/m/Y')}");
                $this->error('   ¡Las fechas NO coinciden!');
            } else {
                $this->info('✓ Las fechas son correctas');
            }
        }

        return 0;
    }
}
