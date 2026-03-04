<?php

namespace App\Console\Commands;

use App\Models\CreditoPrendario;
use App\Models\Remate;
use App\Models\CreditoMovimiento;
use App\Services\AuditoriaService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

/**
 * Comando para procesar remates automáticos de contratos vencidos.
 *
 * Busca créditos vencidos/en_mora con más de X días vencidos y los remata,
 * moviendo sus prendas a estado en_venta.
 *
 * Uso:
 *   php artisan remates:procesar                  → Usa 90 días por defecto
 *   php artisan remates:procesar --dias=60        → Personalizar días mínimos
 *   php artisan remates:procesar --sucursal=1     → Solo una sucursal
 *   php artisan remates:procesar --dry-run        → Simular sin ejecutar
 */
class ProcesarRematesAutomaticos extends Command
{
    protected $signature = 'remates:procesar
        {--dias=90 : Días mínimos de vencimiento para rematar}
        {--sucursal= : ID de sucursal específica (opcional)}
        {--dry-run : Simular sin ejecutar cambios}';

    protected $description = 'Procesa remates automáticos de contratos vencidos según días configurados';

    public function handle(): int
    {
        $diasMinimos = (int) $this->option('dias');
        $sucursalId = $this->option('sucursal');
        $dryRun = $this->option('dry-run');

        $this->info("=== Procesamiento de Remates Automáticos ===");
        $this->info("Días mínimos de vencimiento: {$diasMinimos}");
        if ($sucursalId) {
            $this->info("Sucursal filtrada: {$sucursalId}");
        }
        if ($dryRun) {
            $this->warn("*** MODO SIMULACIÓN (dry-run) - No se ejecutarán cambios ***");
        }
        $this->newLine();

        // Buscar créditos candidatos a remate
        $query = CreditoPrendario::with(['prendas', 'cliente', 'sucursal'])
            ->whereIn('estado', ['vencido', 'en_mora'])
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<=', now()->subDays($diasMinimos))
            // Excluir créditos que ya tienen remate activo
            ->whereDoesntHave('remates', fn($q) => $q->whereIn('estado', ['pendiente', 'ejecutado']));

        if ($sucursalId) {
            $query->where('sucursal_id', $sucursalId);
        }

        $candidatos = $query->get();

        if ($candidatos->isEmpty()) {
            $this->info("No se encontraron créditos candidatos a remate.");
            return 0;
        }

        $this->info("Encontrados {$candidatos->count()} créditos candidatos a remate:");
        $this->newLine();

        // Mostrar tabla de candidatos
        $tableData = $candidatos->map(function ($credito) {
            $diasVencido = Carbon::parse($credito->fecha_vencimiento)->diffInDays(now());
            $deuda = ($credito->capital_pendiente ?? 0) + ($credito->intereses_pendientes ?? 0) + ($credito->mora_pendiente ?? 0);
            return [
                $credito->codigo_credito,
                $credito->cliente ? "{$credito->cliente->nombres} {$credito->cliente->apellidos}" : 'N/A',
                $credito->estado,
                $credito->fecha_vencimiento,
                $diasVencido . ' días',
                'Q ' . number_format($deuda, 2),
                $credito->prendas->count() . ' prenda(s)',
            ];
        })->toArray();

        $this->table(
            ['Código', 'Cliente', 'Estado', 'Vencimiento', 'Días Vencido', 'Deuda Total', 'Prendas'],
            $tableData
        );

        if ($dryRun) {
            $this->newLine();
            $this->warn("Modo dry-run: No se ejecutaron cambios.");
            return 0;
        }

        // Confirmar si es interactivo
        if (!$this->confirm("¿Desea proceder con el remate de {$candidatos->count()} créditos?", false)) {
            $this->info("Operación cancelada.");
            return 0;
        }

        // Procesar remates
        $exitosos = 0;
        $fallidos = 0;
        $bar = $this->output->createProgressBar($candidatos->count());
        $bar->start();

        foreach ($candidatos as $credito) {
            try {
                DB::transaction(function () use ($credito) {
                    $prendasRematadas = 0;

                    foreach ($credito->prendas as $prenda) {
                        if (!in_array($prenda->estado, ['en_custodia', 'recuperada'])) {
                            continue;
                        }

                        Remate::create([
                            'credito_id' => $credito->id,
                            'prenda_id' => $prenda->id,
                            'sucursal_id' => $credito->sucursal_id,
                            'usuario_id' => 1, // Sistema
                            'tipo' => Remate::TIPO_AUTOMATICO,
                            'estado' => Remate::ESTADO_EJECUTADO,
                            'capital_pendiente' => $credito->capital_pendiente ?? 0,
                            'intereses_pendientes' => $credito->intereses_pendientes ?? 0,
                            'mora_pendiente' => $credito->mora_pendiente ?? 0,
                            'valor_avaluo' => $prenda->valor_avaluo ?? $prenda->precio_avaluo ?? 0,
                            'fecha_vencimiento_credito' => $credito->fecha_vencimiento,
                            'motivo' => 'Remate automático - ' . ($credito->dias_vencido ?? 0) . ' días vencido',
                        ]);

                        $prenda->update([
                            'estado' => 'en_venta',
                            'precio_remate' => $prenda->valor_avaluo ?? $prenda->precio_avaluo ?? 0,
                            'fecha_remate' => now(),
                        ]);

                        AuditoriaService::logRemate($prenda, $credito);
                        $prendasRematadas++;
                    }

                    if ($prendasRematadas > 0) {
                        CreditoPrendario::$auditarDeshabilitado = true;
                        $credito->update(['estado' => 'rematado']);
                        CreditoPrendario::$auditarDeshabilitado = false;

                        CreditoMovimiento::create([
                            'credito_prendario_id' => $credito->id,
                            'tipo_movimiento' => 'remate',
                            'monto_total' => ($credito->capital_pendiente ?? 0) + ($credito->intereses_pendientes ?? 0) + ($credito->mora_pendiente ?? 0),
                            'capital' => $credito->capital_pendiente ?? 0,
                            'interes' => $credito->intereses_pendientes ?? 0,
                            'mora' => $credito->mora_pendiente ?? 0,
                            'saldo_capital' => 0,
                            'saldo_interes' => 0,
                            'saldo_mora' => 0,
                            'concepto' => 'Remate automático',
                            'observaciones' => "Remate automático por vencimiento",
                            'usuario_id' => 1,
                            'sucursal_id' => $credito->sucursal_id,
                            'estado' => 'activo',
                            'fecha_movimiento' => now(),
                            'fecha_registro' => now(),
                        ]);
                    }
                });

                $exitosos++;
            } catch (\Exception $e) {
                $fallidos++;
                Log::error("Error al rematar crédito {$credito->codigo_credito}: {$e->getMessage()}");
                $this->newLine();
                $this->error("Error en {$credito->codigo_credito}: {$e->getMessage()}");
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("=== Resultado ===");
        $this->info("Exitosos: {$exitosos}");
        if ($fallidos > 0) {
            $this->error("Fallidos: {$fallidos}");
        }

        Log::info("Remates automáticos procesados", [
            'dias_minimos' => $diasMinimos,
            'sucursal_id' => $sucursalId,
            'candidatos' => $candidatos->count(),
            'exitosos' => $exitosos,
            'fallidos' => $fallidos,
        ]);

        return $fallidos > 0 ? 1 : 0;
    }
}
