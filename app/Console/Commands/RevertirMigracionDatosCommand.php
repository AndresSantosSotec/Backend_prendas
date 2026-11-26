<?php

namespace App\Console\Commands;

use App\Models\MigracionDatosLog;
use App\Services\MigracionService;
use Illuminate\Console\Command;

class RevertirMigracionDatosCommand extends Command
{
    protected $signature = 'migracion:revertir
                            {lote? : ID o codigo_lote del log (ej. MIG_xxxx)}
                            {--force : Sin confirmación}
                            {--ultimo : Revertir el último lote completado}';

    protected $description = 'Revierte un lote de Migración de Datos Históricos (Libro Maestro)';

    public function handle(MigracionService $service): int
    {
        $log = null;

        if ($this->option('ultimo')) {
            $log = MigracionDatosLog::where('estado', 'completado')
                ->orWhere('estado', 'completado_parcial')
                ->orderByDesc('id')
                ->first();
        } elseif ($this->argument('lote')) {
            $lote = $this->argument('lote');
            $log = is_numeric($lote)
                ? MigracionDatosLog::find((int) $lote)
                : MigracionDatosLog::where('codigo_lote', $lote)->first();
        }

        if (!$log) {
            $this->error('No se encontró el lote. Usa --ultimo o pasa ID/codigo_lote.');
            $this->table(
                ['id', 'codigo_lote', 'estado', 'tabla', 'insertados', 'fecha'],
                MigracionDatosLog::orderByDesc('id')->limit(10)->get()->map(fn ($l) => [
                    $l->id,
                    $l->codigo_lote,
                    $l->estado,
                    $l->tabla_destino,
                    $l->filas_insertadas,
                    optional($l->fecha_fin)->toDateTimeString(),
                ])->all()
            );
            return self::FAILURE;
        }

        $this->info("Lote #{$log->id} {$log->codigo_lote} estado={$log->estado} modelo={$log->tabla_destino}");
        $cuadre = $log->resumen['cuadre'] ?? null;
        if ($cuadre) {
            $this->line('Cuadre: ' . json_encode($cuadre, JSON_UNESCAPED_UNICODE));
        }

        if (!$this->option('force') && !$this->confirm('¿Revertir este lote (borrar registros creados)?', false)) {
            $this->warn('Cancelado.');
            return self::SUCCESS;
        }

        try {
            $log = $service->revertirLote($log);
            $this->info('Revertido OK. Estado=' . $log->estado);
            $this->line(json_encode($log->resumen['revertido'] ?? [], JSON_UNESCAPED_UNICODE));
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
