<?php

namespace App\Console\Commands;

use App\Models\CreditoPrendario;
use App\Models\CreditoPlanPago;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RepararCapitalCuotasCommand extends Command
{
    protected $signature = 'creditos:reparar-capital-cuotas 
                            {--credito= : Código (numero_credito) o ID de crédito específico} 
                            {--dry-run : Solo diagnosticar y listar sin guardar cambios}';

    protected $description = 'Detecta y repara créditos cuyo plan de pagos quedó con capital en 0.00 teniendo saldo pendiente real';

    public function handle(): int
    {
        $creditoFiltro = $this->option('credito');
        $dryRun = (bool) $this->option('dry-run');

        $this->info($dryRun ? '=== MODO DIAGNÓSTICO (DRY-RUN) ===' : '=== REPARACIÓN DE CAPITAL EN CUOTAS ===');

        $query = CreditoPrendario::query()
            ->where('capital_pendiente', '>', 0)
            ->whereNotIn('estado', ['pagado', 'cancelado', 'rechazado', 'anulado']);

        if ($creditoFiltro) {
            $query->where(function ($q) use ($creditoFiltro) {
                $q->where('id', $creditoFiltro)
                  ->orWhere('numero_credito', $creditoFiltro);
            });
        }

        $creditos = $query->with(['planPagos' => function ($q) {
            $q->whereIn('estado', ['pendiente', 'vencida', 'en_mora', 'pagada_parcial'])
              ->orderBy('numero_cuota', 'asc');
        }])->get();

        $afectados = [];

        foreach ($creditos as $credito) {
            $cuotasActivas = $credito->planPagos;

            if ($cuotasActivas->isEmpty()) {
                continue;
            }

            $capitalCuotas = (float) $cuotasActivas->sum('capital_pendiente');
            $capitalCredito = (float) $credito->capital_pendiente;

            // Descuadre detectado si el capital de las cuotas es 0 o difiere del capital del crédito
            if ($capitalCuotas == 0 || abs($capitalCredito - $capitalCuotas) > 0.01) {
                $afectados[] = [
                    'credito' => $credito,
                    'capital_credito' => $capitalCredito,
                    'capital_cuotas' => $capitalCuotas,
                    'cuotas_activas' => $cuotasActivas
                ];
            }
        }

        if (empty($afectados)) {
            $this->info('No se encontraron créditos con descuadre de capital en sus cuotas.');
            return 0;
        }

        $this->warn('Se encontraron ' . count($afectados) . ' crédito(s) con descuadre:');

        $filasTabla = [];
        foreach ($afectados as $item) {
            $c = $item['credito'];
            $filasTabla[] = [
                $c->id,
                $c->numero_credito,
                $c->estado,
                'Q ' . number_format($item['capital_credito'], 2),
                'Q ' . number_format($item['capital_cuotas'], 2),
                $item['cuotas_activas']->count(),
                $item['cuotas_activas']->pluck('numero_cuota')->join(', ')
            ];
        }

        $this->table(
            ['ID', 'Número Crédito', 'Estado', 'Capital Crédito', 'Capital en Cuotas', 'Cant. Cuotas', 'Cuotas N°'],
            $filasTabla
        );

        if ($dryRun) {
            $this->comment('Ejecute sin --dry-run para aplicar las correcciones.');
            return 0;
        }

        if (!$this->confirm('¿Desea proceder a reparar estos ' . count($afectados) . ' crédito(s)?', true)) {
            $this->line('Operación cancelada.');
            return 0;
        }

        DB::beginTransaction();
        try {
            $reparados = 0;

            foreach ($afectados as $item) {
                $credito = $item['credito'];
                $cuotasActivas = $item['cuotas_activas'];
                $capitalCredito = $item['capital_credito'];
                $numCuotas = $cuotasActivas->count();

                // Si solo hay 1 cuota activa, se le asigna todo el capital pendiente
                // Si hay más, se distribuye equitativamente entre las cuotas pendientes
                $capitalPorCuota = round($capitalCredito / $numCuotas, 2);
                $capitalAsignado = 0;

                foreach ($cuotasActivas->values() as $idx => $cuota) {
                    $capitalEstaCuota = ($idx === $numCuotas - 1)
                        ? round($capitalCredito - $capitalAsignado, 2)
                        : $capitalPorCuota;
                    $capitalAsignado += $capitalEstaCuota;

                    $capitalProyectadoNuevo = $capitalEstaCuota + (float) ($cuota->capital_pagado ?? 0);
                    $interesProyectado = (float) ($cuota->interes_proyectado ?? 0);
                    $moraProyectada = (float) ($cuota->mora_proyectada ?? 0);
                    $otrosProyectados = (float) ($cuota->otros_cargos_proyectados ?? 0);

                    $interesPendiente = (float) ($cuota->interes_pendiente ?? 0);
                    $moraPendiente = (float) ($cuota->mora_pendiente ?? 0);
                    $otrosPendientes = (float) ($cuota->otros_cargos_pendientes ?? 0);

                    $cuota->update([
                        'capital_proyectado' => $capitalProyectadoNuevo,
                        'capital_pendiente' => $capitalEstaCuota,
                        'monto_cuota_proyectado' => $capitalProyectadoNuevo + $interesProyectado + $moraProyectada + $otrosProyectados,
                        'monto_pendiente' => $capitalEstaCuota + $interesPendiente + $moraPendiente + $otrosPendientes,
                        'saldo_capital_credito' => $capitalCredito,
                        'es_cuota_gracia' => false,
                        'observaciones' => trim(($cuota->observaciones ?? '') . ' | Capital corregido por comando de soporte el ' . now()->format('Y-m-d H:i'))
                    ]);
                }

                $reparados++;
                $this->info("✓ Crédito #{$credito->numero_credito} (ID: {$credito->id}) reparado con éxito.");
            }

            DB::commit();
            $this->info("\n¡Éxito! Se repararon {$reparados} crédito(s) correctamente.");
            return 0;

        } catch (\Throwable $e) {
            DB::rollBack();
            $this->error('Error durante la reparación: ' . $e->getMessage());
            Log::error('Error en comando creditos:reparar-capital-cuotas', ['exception' => $e]);
            return 1;
        }
    }
}
