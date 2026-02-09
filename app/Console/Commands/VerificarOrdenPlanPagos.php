<?php

namespace App\Console\Commands;

use App\Models\CreditoPrendario;
use App\Models\CreditoPlanPago;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class VerificarOrdenPlanPagos extends Command
{
    protected $signature = 'creditos:verificar-orden-plan {--credito= : ID del crédito} {--corregir : Corregir fechas si están fuera de orden}';
    protected $description = 'Verifica que el plan de pagos esté ordenado correctamente por fecha';

    public function handle()
    {
        $creditoId = $this->option('credito');
        $corregir = $this->option('corregir');

        $query = $creditoId
            ? CreditoPrendario::where('id', $creditoId)
            : CreditoPrendario::whereHas('planPagos');

        $creditos = $query->with('planPagos')->get();

        if ($creditos->isEmpty()) {
            $this->error('No se encontraron créditos con plan de pagos');
            return 1;
        }

        $problemasEncontrados = 0;

        foreach ($creditos as $credito) {
            $plan = $credito->planPagos->sortBy('numero_cuota')->values();

            if ($plan->isEmpty()) {
                continue;
            }

            $this->info("\n=== Crédito: {$credito->numero_credito} (ID: {$credito->id}) ===");

            $fechaAnterior = null;
            $ordenCorrecto = true;
            $cuotasProblema = [];

            foreach ($plan as $index => $cuota) {
                $fechaActual = $cuota->fecha_vencimiento;

                if ($fechaAnterior && $fechaActual < $fechaAnterior) {
                    $ordenCorrecto = false;
                    $cuotasProblema[] = [
                        'cuota' => $cuota->numero_cuota,
                        'fecha' => $fechaActual->format('d/m/Y'),
                        'problema' => 'Fecha anterior a la cuota previa'
                    ];
                }

                $fechaAnterior = $fechaActual;
            }

            if (!$ordenCorrecto) {
                $problemasEncontrados++;
                $this->error("❌ PROBLEMA DETECTADO: Fechas fuera de orden");

                $this->table(
                    ['Cuota', 'Fecha', 'Problema'],
                    array_map(function($p) {
                        return [$p['cuota'], $p['fecha'], $p['problema']];
                    }, $cuotasProblema)
                );

                if ($corregir) {
                    $this->warn("Corrigiendo fechas...");
                    $this->corregirFechas($credito);
                    $this->info("✓ Fechas corregidas");
                }
            } else {
                $this->info("✓ Orden correcto");
                $this->table(
                    ['Cuota', 'Fecha'],
                    $plan->map(fn($c) => [$c->numero_cuota, $c->fecha_vencimiento->format('d/m/Y')])->toArray()
                );
            }
        }

        $this->newLine();
        if ($problemasEncontrados > 0) {
            $this->error("Se encontraron problemas en {$problemasEncontrados} crédito(s)");
            if (!$corregir) {
                $this->info("Ejecuta con --corregir para corregir automáticamente");
            }
        } else {
            $this->info("✓ Todos los planes de pago están ordenados correctamente");
        }

        return 0;
    }

    private function corregirFechas(CreditoPrendario $credito)
    {
        DB::beginTransaction();
        try {
            $plan = $credito->planPagos()->orderBy('numero_cuota')->get();

            $fechaPrimerPago = $credito->fecha_primer_pago
                ? \Carbon\Carbon::parse($credito->fecha_primer_pago)
                : $credito->fecha_desembolso;

            foreach ($plan as $index => $cuota) {
                $nuevaFecha = $fechaPrimerPago->copy()->addMonths($cuota->numero_cuota - 1);

                $cuota->update([
                    'fecha_vencimiento' => $nuevaFecha
                ]);

                $this->line("  Cuota {$cuota->numero_cuota}: {$nuevaFecha->format('d/m/Y')}");
            }

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("Error al corregir: " . $e->getMessage());
        }
    }
}
