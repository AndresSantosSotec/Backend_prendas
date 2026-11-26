<?php

namespace App\Console\Commands;

use App\Models\CategoriaProducto;
use App\Models\Cliente;
use App\Models\CreditoPrendario;
use App\Models\Prenda;
use App\Models\PrendaDatoAdicional;
use App\Models\Sucursal;
use App\Models\Tasacion;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Carga Migración #1 (ETL Excel legacy → CSV) a la BD DigiPrenda configurada en .env.
 *
 * Uso:
 *   php artisan migracion:cargar-1
 *   php artisan migracion:cargar-1 --force
 *   php artisan migracion:cargar-1 --path=D:/Dev_empenios/migration#1/output
 *   php artisan migracion:cargar-1 --sin-remate
 */
class CargarMigracion1Command extends Command
{
    protected $signature = 'migracion:cargar-1
                            {--path= : Carpeta con CSV de migration#1/output}
                            {--force : Ejecutar sin confirmación}
                            {--sin-remate : No cargar inventario remate (compra_directa)}
                            {--solo-remate : Solo cargar inventario remate}';

    protected $description = 'Carga Migración #1 (clientes, créditos, prendas, remate) a MySQL DigiPrenda';

    /** @var array<string,int> */
    private array $mapCli = [];

    /** @var array<string,int> */
    private array $mapCred = [];

    /** @var array<string,int> */
    private array $mapPrenda = [];

    /** @var array<string,int> */
    private array $mapCat = [];

    private int $sucursalId = 0;

    private ?int $userId = null;

    private array $stats = [
        'clientes' => 0,
        'creditos' => 0,
        'prendas_empeno' => 0,
        'prendas_remate' => 0,
        'adicionales' => 0,
        'tasaciones' => 0,
        'omitidos' => 0,
        'errores' => 0,
    ];

    public function handle(): int
    {
        $path = $this->option('path')
            ? rtrim((string) $this->option('path'), DIRECTORY_SEPARATOR)
            : dirname(base_path()) . DIRECTORY_SEPARATOR . 'migration#1' . DIRECTORY_SEPARATOR . 'output';

        $this->newLine();
        $this->info('=== DigiPrenda — Carga Migración #1 ===');
        $this->line('BD:   ' . config('database.connections.mysql.database'));
        $this->line('Path: ' . $path);
        $this->newLine();

        foreach (['clientes.csv', 'creditos.csv', 'prendas.csv'] as $req) {
            if (!is_file($path . DIRECTORY_SEPARATOR . $req)) {
                $this->error("Falta {$req} en {$path}. Ejecuta primero: python main.py en migration#1");
                return self::FAILURE;
            }
        }

        if (!$this->option('force') && !$this->confirm('¿Cargar datos de migración en DigiPrenda?', true)) {
            $this->warn('Cancelado.');
            return self::SUCCESS;
        }

        Cliente::$auditarDeshabilitado = true;
        Prenda::$auditarDeshabilitado = true;

        try {
            $this->prepararCatalogos();
            $this->asegurarTablaReferencias();

            DB::transaction(function () use ($path) {
                if (!$this->option('solo-remate')) {
                    $this->cargarClientes($path . DIRECTORY_SEPARATOR . 'clientes.csv');
                    $this->cargarCreditos($path . DIRECTORY_SEPARATOR . 'creditos.csv');
                    $this->cargarPrendas($path . DIRECTORY_SEPARATOR . 'prendas.csv', 'empeño');
                }
                if (!$this->option('sin-remate')) {
                    $remate = $path . DIRECTORY_SEPARATOR . 'inventario_remate.csv';
                    if (is_file($remate)) {
                        $this->cargarPrendas($remate, 'compra_directa');
                    }
                }
                // Adicionales/tasaciones después de TODAS las prendas (empeño + remate)
                $this->cargarAdicionales($path . DIRECTORY_SEPARATOR . 'prendas_adicionales.csv');
                if (!$this->option('solo-remate')) {
                    $this->cargarTasaciones($path . DIRECTORY_SEPARATOR . 'tasaciones.csv');
                }
            });
        } catch (\Throwable $e) {
            $this->error('Error: ' . $e->getMessage());
            $this->line($e->getFile() . ':' . $e->getLine());
            return self::FAILURE;
        } finally {
            Cliente::$auditarDeshabilitado = false;
            Prenda::$auditarDeshabilitado = false;
        }

        $this->newLine();
        $this->info('Insertados / actualizados:');
        foreach ($this->stats as $k => $v) {
            $this->line("  {$k}: {$v}");
        }

        $this->newLine();
        $this->info('Conteos BD:');
        $this->line('  clientes: ' . Cliente::count());
        $this->line('  creditos: ' . CreditoPrendario::count());
        $this->line('  prendas:  ' . Prenda::count());
        $this->line('  empeño:   ' . Prenda::where('tipo_ingreso', 'empeño')->count());
        $this->line('  remate:   ' . Prenda::where('tipo_ingreso', 'compra_directa')->count());
        $this->newLine();
        $this->info('Listo. Revisa en DigiPrenda Clientes / Créditos / Prendas.');

        return self::SUCCESS;
    }

    private function prepararCatalogos(): void
    {
        $suc = Sucursal::where('codigo', 'DEMO-001')->first()
            ?? Sucursal::where('activa', true)->orderBy('id')->first()
            ?? Sucursal::orderBy('id')->first();

        if (!$suc) {
            throw new \RuntimeException('No hay sucursales. Ejecuta DemoOrganizacionSeeder primero.');
        }
        $this->sucursalId = (int) $suc->id;
        $this->line("Sucursal: {$suc->codigo} (id={$suc->id})");

        $this->userId = User::where('rol', 'superadmin')->value('id')
            ?? User::orderBy('id')->value('id');

        // Mapear códigos ETL → IDs reales DigiPrenda
        $cats = CategoriaProducto::query()->get(['id', 'codigo']);
        foreach ($cats as $c) {
            $this->mapCat[$c->codigo] = (int) $c->id;
        }
        // Fallbacks por familia
        if (!isset($this->mapCat['CAT-001'])) {
            $joy = CategoriaProducto::where('nombre', 'like', '%Joy%')->value('id');
            if ($joy) {
                $this->mapCat['CAT-001'] = (int) $joy;
            }
        }
        if (!isset($this->mapCat['CAT-002'])) {
            $ele = CategoriaProducto::where('nombre', 'like', '%Electr%')->value('id')
                ?? CategoriaProducto::where('codigo', 'CAT-012')->value('id');
            if ($ele) {
                $this->mapCat['CAT-002'] = (int) $ele;
            }
        }
        if (!isset($this->mapCat['CAT-005'])) {
            $veh = CategoriaProducto::where('nombre', 'like', '%Veh%')->value('id');
            if ($veh) {
                $this->mapCat['CAT-005'] = (int) $veh;
            }
        }
        $this->line('Categorías mapeadas: ' . json_encode($this->mapCat));
    }

    private function asegurarTablaReferencias(): void
    {
        if (Schema::hasTable('migracion_referencias')) {
            return;
        }
        Schema::create('migracion_referencias', function ($table) {
            $table->id();
            $table->string('sistema_origen', 50);
            $table->string('entidad_origen', 50);
            $table->string('identificador_origen', 100);
            $table->string('entidad_destino', 50);
            $table->unsignedBigInteger('identificador_destino');
            $table->timestamp('created_at')->useCurrent();
            $table->unique(
                ['sistema_origen', 'entidad_origen', 'identificador_origen'],
                'uq_migracion_origen'
            );
        });
        $this->line('Tabla migracion_referencias creada.');
    }

    private function ref(string $entidadOrigen, string $idOrigen, string $entidadDestino, int $idDestino): void
    {
        DB::table('migracion_referencias')->updateOrInsert(
            [
                'sistema_origen' => 'legacy_xls',
                'entidad_origen' => $entidadOrigen,
                'identificador_origen' => $idOrigen,
            ],
            [
                'entidad_destino' => $entidadDestino,
                'identificador_destino' => $idDestino,
                'created_at' => now(),
            ]
        );
    }

    private function leerCsv(string $file): array
    {
        if (!is_file($file)) {
            return [];
        }
        $fh = fopen($file, 'r');
        if (!$fh) {
            return [];
        }
        $headers = fgetcsv($fh);
        if (!$headers) {
            fclose($fh);
            return [];
        }
        $headers = array_map(function ($h) {
            $h = (string) $h;
            // Quitar BOM UTF-8 del primer encabezado
            $h = preg_replace('/^\xEF\xBB\xBF/', '', $h) ?? $h;
            return trim($h);
        }, $headers);
        $rows = [];
        while (($data = fgetcsv($fh)) !== false) {
            if (count($data) === 1 && ($data[0] === null || trim((string) $data[0]) === '')) {
                continue;
            }
            if (count($data) < count($headers)) {
                $data = array_pad($data, count($headers), null);
            }
            $row = [];
            foreach ($headers as $i => $h) {
                $row[$h] = $data[$i] ?? null;
            }
            if (isset($row['info']) && count($row) === 1) {
                continue;
            }
            $rows[] = $row;
        }
        fclose($fh);
        return $rows;
    }

    private function num($v, $default = 0.0): float
    {
        if ($v === null || $v === '') {
            return (float) $default;
        }
        return (float) str_replace(',', '', (string) $v);
    }

    private function str($v, string $default = ''): string
    {
        if ($v === null) {
            return $default;
        }
        $s = trim((string) $v);
        if ($s === '' || strtolower($s) === 'nan') {
            return $default;
        }
        return $s;
    }

    private function condicion(?string $raw): string
    {
        $k = strtoupper($this->str($raw));
        return match (true) {
            str_contains($k, 'EXCELENTE') => 'excelente',
            str_contains($k, 'MUY') => 'muy_buena',
            str_contains($k, 'REGULAR') => 'regular',
            str_contains($k, 'MALA') => 'mala',
            default => 'buena',
        };
    }

    private function catId(?string $codigo): int
    {
        $c = $this->str($codigo, 'CAT-002');
        return $this->mapCat[$c] ?? $this->mapCat['CAT-002'] ?? $this->mapCat['CAT-012'] ?? 15;
    }

    private function cargarClientes(string $file): void
    {
        $this->info('→ Clientes');
        foreach ($this->leerCsv($file) as $i => $r) {
            $temp = $this->str($r['cliente_temp_id'] ?? null, sprintf('TMP-CLI-%05d', $i + 1));
            $codigo = 'MIG1-' . preg_replace('/^TMP-CLI-/', '', $temp);

            $cliente = Cliente::updateOrCreate(
                ['codigo_cliente' => $codigo],
                [
                    'nombres' => $this->str($r['nombres'] ?? null, 'PENDIENTE'),
                    'apellidos' => $this->str($r['apellidos'] ?? null, 'PENDIENTE'),
                    'dpi' => $this->str($r['dpi'] ?? null) ?: null,
                    'nit' => $this->str($r['nit'] ?? null) ?: null,
                    'telefono' => $this->str($r['telefono'] ?? null) ?: '00000000',
                    'email' => $this->str($r['email'] ?? null) ?: null,
                    'fecha_nacimiento' => $this->str($r['fecha_nacimiento'] ?? null, '1900-01-01'),
                    'genero' => $this->str($r['genero'] ?? null, 'otro'),
                    'direccion' => $this->str($r['direccion'] ?? null, 'PENDIENTE MIGRACIÓN'),
                    'estado' => $this->str($r['estado'] ?? null, 'activo'),
                    'tipo_cliente' => $this->str($r['tipo_cliente'] ?? null, 'regular'),
                    'sucursal' => 'DEMO-001',
                    'notas' => $this->str($r['notas'] ?? null) . " | temp={$temp}",
                    'eliminado' => false,
                ]
            );
            $this->mapCli[$temp] = (int) $cliente->id;
            $this->ref('cliente_temp_id', $temp, 'clientes', (int) $cliente->id);
            $this->stats['clientes']++;
        }
        $this->line('  ' . $this->stats['clientes'] . ' clientes');
    }

    private function cargarCreditos(string $file): void
    {
        $this->info('→ Créditos');
        foreach ($this->leerCsv($file) as $r) {
            $origen = $this->str($r['numero_credito_origen'] ?? null);
            if ($origen === '') {
                $this->stats['omitidos']++;
                continue;
            }
            $tempCli = $this->str($r['cliente_temp_id'] ?? null);
            $clienteId = $this->mapCli[$tempCli] ?? null;
            if (!$clienteId) {
                // crear cliente mínimo
                $nombre = $this->str($r['cliente_nombre'] ?? null, 'SIN NOMBRE');
                $parts = preg_split('/\s+/', $nombre, 2);
                $cli = Cliente::create([
                    'codigo_cliente' => 'MIG1-AUTO-' . $origen,
                    'nombres' => $parts[0] ?? 'SIN',
                    'apellidos' => $parts[1] ?? 'NOMBRE',
                    'fecha_nacimiento' => '1900-01-01',
                    'genero' => 'otro',
                    'direccion' => 'PENDIENTE',
                    'telefono' => '00000000',
                    'estado' => 'activo',
                    'tipo_cliente' => 'regular',
                    'notas' => 'Auto-creado por crédito migrado ' . $origen,
                    'eliminado' => false,
                ]);
                $clienteId = (int) $cli->id;
                if ($tempCli !== '') {
                    $this->mapCli[$tempCli] = $clienteId;
                }
                $this->stats['clientes']++;
            }

            $numero = 'MIG-' . $origen;
            $credito = CreditoPrendario::updateOrCreate(
                ['numero_credito' => $numero],
                [
                    'cliente_id' => $clienteId,
                    'sucursal_id' => $this->sucursalId,
                    'analista_id' => $this->userId,
                    'cajero_id' => $this->userId,
                    'estado' => $this->str($r['estado'] ?? null, 'vigente'),
                    'fecha_solicitud' => $this->str($r['fecha_solicitud'] ?? null, '2000-01-01'),
                    'fecha_desembolso' => $this->str($r['fecha_desembolso'] ?? null) ?: null,
                    'fecha_vencimiento' => $this->str($r['fecha_vencimiento'] ?? null) ?: null,
                    'monto_solicitado' => $this->num($r['monto_solicitado'] ?? 0),
                    'monto_aprobado' => $this->num($r['monto_aprobado'] ?? 0),
                    'monto_desembolsado' => $this->num($r['monto_desembolsado'] ?? 0),
                    'valor_tasacion' => $this->num($r['valor_tasacion'] ?? 0),
                    'capital_pendiente' => $this->num($r['capital_pendiente'] ?? 0),
                    'capital_pagado' => $this->num($r['capital_pagado'] ?? 0),
                    'tasa_interes' => $this->num($r['tasa_interes'] ?? 0),
                    'tasa_mora' => $this->num($r['tasa_mora'] ?? 0),
                    'tipo_interes' => $this->str($r['tipo_interes'] ?? null, 'mensual'),
                    'metodo_calculo' => $this->str($r['metodo_calculo'] ?? null, 'flat'),
                    'plazo_dias' => (int) $this->num($r['plazo_dias'] ?? 30, 30),
                    'numero_cuotas' => (int) $this->num($r['numero_cuotas'] ?? 1, 1),
                    'permite_refrendo' => true,
                    'numero_contrato' => $origen,
                    'observaciones' => $this->str($r['notas'] ?? null, 'Migración #1'),
                    'calificacion' => 'A',
                    'forma_desembolso' => 'efectivo',
                ]
            );
            $this->mapCred[$origen] = (int) $credito->id;
            $this->ref('contrato', $origen, 'creditos_prendarios', (int) $credito->id);
            $this->stats['creditos']++;
        }
        $this->line('  ' . $this->stats['creditos'] . ' créditos');
    }

    private function cargarPrendas(string $file, string $tipoDefault): void
    {
        $label = $tipoDefault === 'compra_directa' ? 'Inventario remate' : 'Prendas crédito';
        $this->info("→ {$label}");
        $n = 0;
        foreach ($this->leerCsv($file) as $r) {
            $temp = $this->str($r['prenda_temp_id'] ?? null);
            if ($temp === '') {
                $this->stats['omitidos']++;
                continue;
            }
            $origenCred = $this->str($r['numero_credito_origen'] ?? null);
            $creditoId = $origenCred !== '' ? ($this->mapCred[$origenCred] ?? null) : null;

            $tipo = $this->str($r['tipo_ingreso'] ?? null, $tipoDefault);
            if ($tipo === 'credito') {
                $tipo = 'empeño';
            }

            $codigoBase = $this->str($r['codigo_prenda_sugerido'] ?? null)
                ?: $this->str($r['sku_origen'] ?? null)
                ?: $temp;
            $codigo = 'MIG-' . $codigoBase;

            $existing = Prenda::where('observaciones', 'like', "%temp={$temp}%")->first()
                ?? Prenda::where('codigo_prenda', $codigo)->first();

            $payload = [
                'credito_prendario_id' => $creditoId,
                'categoria_producto_id' => $this->catId($r['categoria_codigo'] ?? null),
                'sucursal_id' => $this->sucursalId,
                'tipo_ingreso' => $tipo,
                'codigo_prenda' => $existing?->codigo_prenda ?? $codigo,
                'descripcion' => mb_substr($this->str($r['descripcion'] ?? null, 'PRENDA MIG'), 0, 500),
                'marca' => $this->str($r['marca'] ?? null) ?: null,
                'modelo' => $this->str($r['modelo'] ?? null) ?: null,
                'serie' => $this->str($r['serie'] ?? null) ?: null,
                'condicion' => $this->condicion($r['condicion'] ?? null),
                'valor_estimado_cliente' => $this->num($r['valor_tasacion'] ?? 0),
                'valor_tasacion' => $this->num($r['valor_tasacion'] ?? 0),
                'valor_prestamo' => $this->num($r['valor_prestamo'] ?? 0),
                'valor_venta' => isset($r['valor_venta']) && $r['valor_venta'] !== '' && $r['valor_venta'] !== null
                    ? $this->num($r['valor_venta'])
                    : null,
                'estado' => $this->str($r['estado'] ?? null, $creditoId ? 'en_custodia' : 'en_venta'),
                'fecha_ingreso' => now()->toDateString(),
                'observaciones' => 'Migración #1 temp=' . $temp . ' sku=' . $this->str($r['sku_origen'] ?? null),
            ];

            try {
                if ($existing) {
                    $existing->update($payload);
                    $prenda = $existing;
                } else {
                    // reintentar código si choca
                    $ok = false;
                    for ($s = 0; $s < 30; $s++) {
                        try {
                            $payload['codigo_prenda'] = $s === 0 ? $codigo : $codigo . '-' . $s;
                            $prenda = Prenda::create($payload);
                            $ok = true;
                            break;
                        } catch (\Throwable $ex) {
                            if (!str_contains($ex->getMessage(), 'Duplicate') && !str_contains($ex->getMessage(), 'UNIQUE')) {
                                throw $ex;
                            }
                        }
                    }
                    if (!$ok) {
                        $this->stats['errores']++;
                        continue;
                    }
                }
            } catch (\Throwable $e) {
                $this->stats['errores']++;
                $this->warn("  Prenda {$temp}: " . $e->getMessage());
                continue;
            }

            $this->mapPrenda[$temp] = (int) $prenda->id;
            $this->ref('prenda_temp_id', $temp, 'prendas', (int) $prenda->id);
            if ($tipo === 'compra_directa') {
                $this->stats['prendas_remate']++;
            } else {
                $this->stats['prendas_empeno']++;
            }
            $n++;
        }
        $this->line("  {$n} prendas");
    }

    private function cargarAdicionales(string $file): void
    {
        if (!is_file($file)) {
            return;
        }
        $this->info('→ Datos adicionales');
        foreach ($this->leerCsv($file) as $r) {
            $temp = $this->str($r['prenda_temp_id'] ?? null);
            $pid = $this->mapPrenda[$temp] ?? null;
            if (!$pid) {
                continue;
            }
            $campo = $this->str($r['campo'] ?? null);
            if ($campo === '') {
                continue;
            }
            PrendaDatoAdicional::updateOrCreate(
                [
                    'prenda_id' => $pid,
                    'campo_nombre' => $campo,
                ],
                [
                    'campo_valor' => $this->str($r['valor'] ?? null),
                    'campo_tipo' => 'text',
                    'campo_label' => ucfirst(str_replace('_', ' ', $campo)),
                    'orden' => 0,
                ]
            );
            $this->stats['adicionales']++;
        }
        $this->line('  ' . $this->stats['adicionales'] . ' adicionales');
    }

    private function cargarTasaciones(string $file): void
    {
        if (!is_file($file) || !Schema::hasTable('tasaciones')) {
            return;
        }
        $this->info('→ Tasaciones');
        foreach ($this->leerCsv($file) as $i => $r) {
            $temp = $this->str($r['prenda_temp_id'] ?? null);
            $pid = $this->mapPrenda[$temp] ?? null;
            if (!$pid) {
                continue;
            }
            $numero = 'TAS-MIG-' . str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT);
            $valor = $this->num($r['valor_estimado'] ?? 0);
            $prestamo = $this->num($r['valor_prestamo'] ?? 0);

            $existing = Tasacion::where('prenda_id', $pid)->where('observaciones', 'like', '%Migración #1%')->first();
            $payload = [
                'prenda_id' => $pid,
                'tasador_id' => $this->userId,
                'credito_prendario_id' => Prenda::where('id', $pid)->value('credito_prendario_id'),
                'numero_tasacion' => $existing?->numero_tasacion ?? $numero,
                'fecha_tasacion' => now()->toDateString(),
                'valor_mercado' => $valor,
                'valor_comercial' => $valor,
                'valor_liquidacion' => $valor,
                'valor_final_asignado' => $prestamo > 0 ? $prestamo : $valor,
                'condicion_fisica' => 'buena',
                'metodo_tasacion' => 'comparativo',
                'estado' => 'aprobada',
                'observaciones' => 'Migración #1 - ' . $this->str($r['origen'] ?? null, 'cartera_legacy'),
            ];

            // credito_prendario_id es NOT NULL en el esquema
            if (empty($payload['credito_prendario_id'])) {
                $this->stats['omitidos']++;
                continue;
            }

            try {
                if ($existing) {
                    $existing->update($payload);
                } else {
                    for ($s = 0; $s < 20; $s++) {
                        try {
                            $payload['numero_tasacion'] = $s === 0 ? $numero : $numero . '-' . $s;
                            Tasacion::create($payload);
                            break;
                        } catch (\Throwable $ex) {
                            if (!str_contains($ex->getMessage(), 'Duplicate') && !str_contains($ex->getMessage(), 'UNIQUE')) {
                                throw $ex;
                            }
                        }
                    }
                }
                $this->stats['tasaciones']++;
            } catch (\Throwable $e) {
                $this->stats['errores']++;
                if ($this->stats['errores'] <= 3) {
                    $this->warn('  Tasacion: ' . $e->getMessage());
                }
            }
        }
        $this->line('  ' . $this->stats['tasaciones'] . ' tasaciones');
    }
}
