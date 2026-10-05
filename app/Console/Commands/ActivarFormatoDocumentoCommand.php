<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TbDoc;
use App\Models\ConfiguracionSistema;
use App\Models\Sucursal;
use Illuminate\Support\Facades\File;

class ActivarFormatoDocumentoCommand extends Command
{
    /**
     * Nombre y firma del comando en consola.
     *
     * @var string
     */
    protected $signature = 'doc:activar
                            {slug? : Identificador/slug de la variante a activar (ej: prendamas, estandar, prendafacil)}
                            {--tipo= : Tipo de documento específico a activar (opcional: contrato_credito, plan_pagos, recibo_pago)}
                            {--org= : Código de organización (ej: 01, 02). Por defecto usa la activa en BD}
                            {--sucursal= : ID de sucursal específica (opcional, null = nivel organización)}
                            {--listar : Solo listar todas las plantillas registradas y su estado actual}';

    /**
     * Descripción del comando.
     *
     * @var string
     */
    protected $description = 'Activa una variante de plantilla de documento en la base de datos (desactivando las demás) o lista el estado actual';

    /**
     * Ejecuta el comando.
     */
    public function handle(): int
    {
        $orgCode = $this->option('org') ?: (ConfiguracionSistema::obtener('organizacion_code') ?: env('ORGANIZATION_CODE', '01'));
        $sucursalId = $this->option('sucursal') ? (int) $this->option('sucursal') : null;

        // Modo listar
        if ($this->option('listar') || empty($this->argument('slug'))) {
            return $this->listarPlantillas($orgCode, $sucursalId);
        }

        $slug = trim((string) $this->argument('slug'));
        $tipo = $this->option('tipo');

        $this->newLine();
        $this->info("═══════════════════════════════════════════════════════════════════════");
        $this->info("             ACTIVADOR DE VARIANTES DE DOCUMENTOS PDF                  ");
        $this->info("═══════════════════════════════════════════════════════════════════════");
        $this->line("Variante solicitada: <fg=bright-cyan;options=bold>{$slug}</>");
        $this->line("Organización:        <fg=yellow>{$orgCode}</>");
        $this->line("Sucursal:            <fg=yellow>" . ($sucursalId ? "ID {$sucursalId}" : 'Global (todas)') . "</>");
        if ($tipo) {
            $this->line("Tipo de documento:   <fg=bright-green>{$tipo}</>");
        }
        $this->newLine();

        // 1. Verificar si existen registros con ese slug
        $queryVerificar = TbDoc::where('plantilla_variante', $slug);
        if ($tipo) {
            $queryVerificar->where('tipo_documento', $tipo);
        }
        $totalVariantes = $queryVerificar->count();

        if ($totalVariantes === 0) {
            $this->error("❌ No se encontraron plantillas registradas con la variante [{$slug}]" . ($tipo ? " para el tipo [{$tipo}]." : "."));
            $this->warn("💡 Puedes ver las variantes disponibles ejecutando: php artisan doc:activar --listar");
            return Command::FAILURE;
        }

        // 2. Obtener los tipos de documento que se van a modificar
        $tiposAfectados = $tipo
            ? [$tipo]
            : TbDoc::where('plantilla_variante', $slug)->distinct()->pluck('tipo_documento')->toArray();

        foreach ($tiposAfectados as $tipoItem) {
            // A. Desactivar las demás variantes para este tipo en el mismo ámbito
            $desactivarQuery = TbDoc::where('tipo_documento', $tipoItem)
                ->where('organizacion_code', $orgCode);

            if ($sucursalId !== null) {
                $desactivarQuery->where('sucursal_id', $sucursalId);
            } else {
                $desactivarQuery->whereNull('sucursal_id');
            }

            $desactivadas = $desactivarQuery->where('plantilla_variante', '!=', $slug)->update(['activo' => false]);

            // B. Activar la variante deseada
            $activarQuery = TbDoc::where('tipo_documento', $tipoItem)
                ->where('plantilla_variante', $slug);

            // Intentar primero con el ámbito exacto (org + sucursal)
            $activarExacto = (clone $activarQuery)
                ->where('organizacion_code', $orgCode);
            if ($sucursalId !== null) {
                $activarExacto->where('sucursal_id', $sucursalId);
            } else {
                $activarExacto->whereNull('sucursal_id');
            }

            if ($activarExacto->exists()) {
                $activarExacto->update(['activo' => true]);
            } else {
                // Si no existe con ese org exacto, activar a nivel general
                $activarQuery->update(['activo' => true]);
            }

            $this->info("✔ [{$tipoItem}] -> Activada variante [{$slug}] (desactivadas {$desactivadas} anteriores).");
        }

        // C. Opcional: Actualizar configuracion_sistema doc_variante_default
        ConfiguracionSistema::guardar('doc_variante_default', $slug);

        $this->newLine();
        $this->info("✨ ¡Rotación de formato completada exitosamente!");
        $this->line("Ahora el sistema generará los documentos utilizando la variante: <fg=bright-cyan;options=bold>{$slug}</>");
        $this->newLine();

        return $this->listarPlantillas($orgCode, $sucursalId);
    }

    /**
     * Muestra la tabla con el estado de todas las plantillas.
     */
    protected function listarPlantillas(string $orgCode, ?int $sucursalId = null): int
    {
        $this->newLine();
        $this->info("═══════════════════════════════════════════════════════════════════════");
        $this->info("          CATÁLOGO DE PLANTILLAS DE DOCUMENTOS REGISTRADAS (tb_docs)   ");
        $this->info("═══════════════════════════════════════════════════════════════════════");

        $docs = TbDoc::orderBy('tipo_documento')
            ->orderBy('plantilla_variante')
            ->orderBy('id')
            ->get();

        if ($docs->isEmpty()) {
            $this->warn("⚠️  No hay plantillas registradas en la tabla tb_docs.");
            return Command::SUCCESS;
        }

        $rows = [];
        foreach ($docs as $doc) {
            $vistaExiste = !empty($doc->vista_pdf) && view()->exists($doc->vista_pdf);
            $estadoStr = $doc->activo ? '<fg=bright-green;options=bold>🟢 ACTIVO</>' : '<fg=gray>⚪ INACTIVO</>';
            $vistaStr = $doc->vista_pdf ?: '(por defecto)';
            $archivoExisteStr = $vistaExiste ? '✅' : '❌ Falta Blade';

            $rows[] = [
                $doc->id,
                $doc->tipo_documento,
                $doc->plantilla_variante,
                $doc->nombre_documento,
                "{$vistaStr} {$archivoExisteStr}",
                $doc->organizacion_code ?: '*',
                $doc->sucursal_id ?: 'Global',
                $estadoStr,
            ];
        }

        $this->table(
            ['ID', 'Tipo', 'Variante', 'Nombre', 'Vista Blade', 'Org', 'Sucursal', 'Estado'],
            $rows
        );

        $envVariante = env('DOC_VARIANTE') ?: env('DOC_VARIANTE_DEFAULT');
        $dbVariante = ConfiguracionSistema::obtener('doc_variante_default');

        $this->newLine();
        $this->line("<fg=yellow;options=bold>CONFIGURACIÓN DE PRIORIDAD ACTUAL:</>");
        $this->line("• Variable .env (DOC_VARIANTE):          " . ($envVariante ? "<fg=bright-cyan>{$envVariante}</> (MÁXIMA PRIORIDAD)" : "<fg=gray>(no definida)</>"));
        $this->line("• Sistema BD (doc_variante_default):      " . ($dbVariante ? "<fg=bright-cyan>{$dbVariante}</>" : "<fg=gray>(no configurada)</>"));
        $this->newLine();
        $this->line("<fg=gray>Comandos útiles:</>");
        $this->line("  php artisan doc:activar prendamas           -> Activa formato Notarial Prendamas");
        $this->line("  php artisan doc:activar estandar            -> Activa formato Estándar");
        $this->line("  php artisan doc:activar prendafacil         -> Activa nueva variante creada");
        $this->line("  php artisan doc:crear-formato               -> Crear nueva variante interactiva");
        $this->newLine();

        return Command::SUCCESS;
    }
}
