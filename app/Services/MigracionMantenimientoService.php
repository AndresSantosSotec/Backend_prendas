<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Respaldo completo de BD + purga transaccional (conserva users y catálogos).
 * Solo para uso desde módulo Migración (SuperAdmin).
 */
class MigracionMantenimientoService
{
    /** Tablas operativas que se vacían en limpia. */
    protected array $tablasTransaccionales = [
        'credito_movimientos',
        'credito_plan_pagos',
        'credito_gasto',
        'refrendos',
        'tasaciones',
        'prenda_imagenes',
        'prenda_datos_adicionales',
        'transferencias_prendas',
        'remates',
        'prendas',
        'cotizaciones',
        'codigos_prereservados',
        'auditoria_creditos',
        'creditos_prendarios',
        'venta_pagos',
        'venta_detalles',
        'apartado_pagos',
        'apartados',
        'venta_credito_movimientos',
        'venta_credito_plan_pagos',
        'venta_credito_gastos',
        'venta_creditos',
        'ventas',
        'movimiento_cajas',
        'caja_apertura_cierres',
        'boveda_detalles',
        'boveda_movimientos',
        'ctb_movimientos',
        'ctb_diario',
        'compra_campos_dinamicos',
        'compras',
        'otro_gasto_movimientos',
        'gastos',
        'recibos',
        'idempotency_keys',
        'auditoria_logs',
        'system_error_logs',
        'migracion_datos_logs',
        'migracion_referencias',
        // No vaciar personal_access_tokens ni sessions: el superadmin seguiría autenticado.
        'jobs',
        'job_batches',
        'failed_jobs',
    ];

    protected array $tablasClientes = [
        'cliente_referencias',
        'cliente_borradores',
        'clientes',
    ];

    /**
     * Crea un dump SQL de toda la BD y lo guarda en storage/app/backups.
     *
     * @return array{path:string,filename:string,size:int,created_at:string}
     */
    public function crearRespaldoSeguridad(): array
    {
        $dir = storage_path('app/backups');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $dbName = (string) config('database.connections.mysql.database');
        $filename = 'respaldo_' . $dbName . '_' . date('Ymd_His') . '.sql';
        $path = $dir . DIRECTORY_SEPARATOR . $filename;

        $fh = fopen($path, 'w');
        if (!$fh) {
            throw new \RuntimeException('No se pudo crear el archivo de respaldo.');
        }

        fwrite($fh, "-- DigiPrenda respaldo de seguridad\n");
        fwrite($fh, '-- DB: ' . $dbName . "\n");
        fwrite($fh, '-- Fecha: ' . now()->toDateTimeString() . "\n");
        fwrite($fh, "SET NAMES utf8mb4;\n");
        fwrite($fh, "SET FOREIGN_KEY_CHECKS=0;\n\n");

        $tables = DB::select('SHOW TABLES');
        $key = 'Tables_in_' . $dbName;

        foreach ($tables as $row) {
            $table = $row->$key ?? null;
            if (!$table) {
                continue;
            }

            // Estructura
            $create = DB::select("SHOW CREATE TABLE `{$table}`");
            $createSql = $create[0]->{'Create Table'} ?? null;
            if ($createSql) {
                fwrite($fh, "DROP TABLE IF EXISTS `{$table}`;\n");
                fwrite($fh, $createSql . ";\n\n");
            }

            // Datos
            $rows = DB::table($table)->get();
            if ($rows->isEmpty()) {
                fwrite($fh, "-- (sin datos) {$table}\n\n");
                continue;
            }

            foreach ($rows->chunk(100) as $chunk) {
                $cols = array_keys((array) $chunk->first());
                $colList = '`' . implode('`,`', $cols) . '`';
                $values = [];
                foreach ($chunk as $r) {
                    $vals = [];
                    foreach ($cols as $c) {
                        $v = $r->$c;
                        if ($v === null) {
                            $vals[] = 'NULL';
                        } elseif (is_numeric($v) && !is_string($v)) {
                            $vals[] = (string) $v;
                        } else {
                            $vals[] = "'" . str_replace(
                                ["\\", "'", "\n", "\r", "\x00"],
                                ["\\\\", "\\'", "\\n", "\\r", "\\0"],
                                (string) $v
                            ) . "'";
                        }
                    }
                    $values[] = '(' . implode(',', $vals) . ')';
                }
                fwrite($fh, "INSERT INTO `{$table}` ({$colList}) VALUES\n" . implode(",\n", $values) . ";\n\n");
            }
        }

        fwrite($fh, "SET FOREIGN_KEY_CHECKS=1;\n");
        fclose($fh);

        return [
            'path' => $path,
            'filename' => $filename,
            'size' => filesize($path) ?: 0,
            'created_at' => now()->toIso8601String(),
            'database' => $dbName,
        ];
    }

    /**
     * Descarga un respaldo existente por nombre de archivo (solo dentro de backups/).
     */
    public function descargarRespaldo(string $filename): StreamedResponse
    {
        $filename = basename($filename);
        $path = storage_path('app/backups/' . $filename);
        if (!is_file($path)) {
            throw new \RuntimeException('Respaldo no encontrado.');
        }

        return response()->streamDownload(function () use ($path) {
            $stream = fopen($path, 'rb');
            fpassthru($stream);
            fclose($stream);
        }, $filename, [
            'Content-Type' => 'application/sql',
        ]);
    }

    /**
     * Lista respaldos disponibles.
     */
    public function listarRespaldos(): array
    {
        $dir = storage_path('app/backups');
        if (!is_dir($dir)) {
            return [];
        }
        $files = File::files($dir);
        $out = [];
        foreach ($files as $f) {
            if (strtolower($f->getExtension()) !== 'sql') {
                continue;
            }
            $out[] = [
                'filename' => $f->getFilename(),
                'size' => $f->getSize(),
                'created_at' => date('c', $f->getMTime()),
            ];
        }
        usort($out, fn ($a, $b) => strcmp($b['created_at'], $a['created_at']));
        return $out;
    }

    /**
     * Limpia datos transaccionales. Conserva users, permisos, sucursales, catálogos.
     *
     * @return array{vaciadas:int,omitidas:int,tablas:string[]}
     */
    public function limpiarExceptoUsers(bool $mantenerClientes = false): array
    {
        $tablas = $this->tablasTransaccionales;
        if (!$mantenerClientes) {
            $tablas = array_merge($tablas, $this->tablasClientes);
        }

        DB::statement('SET FOREIGN_KEY_CHECKS = 0');
        $vaciadas = 0;
        $omitidas = 0;
        $hechas = [];

        try {
            foreach ($tablas as $tabla) {
                if (!Schema::hasTable($tabla)) {
                    $omitidas++;
                    continue;
                }
                DB::table($tabla)->truncate();
                $hechas[] = $tabla;
                $vaciadas++;
            }

            if (Schema::hasTable('bovedas')) {
                DB::table('bovedas')->update(['saldo_actual' => 0]);
            }
        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1');
        }

        return [
            'vaciadas' => $vaciadas,
            'omitidas' => $omitidas,
            'tablas' => $hechas,
            'mantener_clientes' => $mantenerClientes,
        ];
    }
}
