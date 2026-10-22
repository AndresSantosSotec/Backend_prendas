<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PurgarTransaccionesCommand extends Command
{
    /**
     * El nombre y firma del comando en consola.
     *
     * @var string
     */
    protected $signature = 'db:purgar-transacciones
                            {--force : Ejecutar sin solicitar confirmación interactiva}
                            {--mantener-clientes : Conservar los clientes registrados en la base de datos}';

    /**
     * Descripción del comando.
     *
     * @var string
     */
    protected $description = 'Vacía todas las operaciones y datos transaccionales (créditos, pagos, ventas, cajas, contabilidad), conservando usuarios y configuraciones de productos crediticios';

    /**
     * Tablas operativas que se vaciarán.
     */
    protected array $tablasTransaccionales = [
        // 1. Créditos prendarios, garantías y tasaciones
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

        // 2. Ventas y apartados
        'venta_pagos',
        'venta_detalles',
        'apartado_pagos',
        'apartados',
        'venta_credito_movimientos',
        'venta_credito_plan_pagos',
        'venta_credito_gastos',
        'venta_creditos',
        'ventas',

        // 3. Operaciones de caja y bóveda
        'movimiento_cajas',
        'caja_apertura_cierres',
        'boveda_detalles',
        'boveda_movimientos',

        // 4. Contabilidad transaccional
        'ctb_movimientos',
        'ctb_diario',

        // 5. Compras y gastos
        'compra_campos_dinamicos',
        'compras',
        'otro_gasto_movimientos',
        'gastos',

        // 6. Recibos, idempotencia y logs de operación
        'recibos',
        'idempotency_keys',
        'auditoria_logs',
        'system_error_logs',
        'migracion_datos_logs',
    ];

    /**
     * Tablas de clientes.
     */
    protected array $tablasClientes = [
        'cliente_referencias',
        'cliente_borradores',
        'clientes',
    ];

    /**
     * Tablas protegidas que NUNCA se tocarán.
     */
    protected array $tablasProtegidas = [
        'users'                         => 'Usuarios del sistema y contraseñas',
        'permissions'                   => 'Catálogo de permisos por módulo',
        'user_permissions'              => 'Permisos asignados a cada usuario',
        'sucursales'                    => 'Sucursales / Agencias',
        'categoria_productos'           => 'Categorías de prendas y parámetros base',
        'campos_dinamicos_categoria'    => 'Campos dinámicos de categorías',
        'planes_interes_categoria'      => 'Productos de crédito (tasas, plazos, moras)',
        'plan_interes_categorias'       => 'Asociación de planes por categoría',
        'parametrizacion_mora'          => 'Políticas de mora y días de gracia',
        'configuraciones_sistema'       => 'Configuraciones de empresa, límites y montos',
        'tb_docs'                       => 'Plantillas de contratos, pagarés y recibos',
        'ctb_nomenclatura'              => 'Nomenclatura y Plan de Cuentas Contables',
        'ctb_tipo_poliza'               => 'Tipos de póliza contable',
        'tb_bancos'                     => 'Catálogo de bancos',
        'ctb_bancos'                    => 'Cuentas bancarias contables',
        'ctb_parametrizacion_cuentas'   => 'Parametrización de cuentas contables operativas',
        'metodos_pago'                  => 'Métodos de pago habilitados',
        'monedas'                       => 'Monedas del sistema',
        'denominaciones'                => 'Denominaciones de efectivo',
        'otro_gasto_tipos'              => 'Tipos de otros gastos',
        'bovedas'                       => 'Definición de bóvedas físicas (saldo se resetea a Q0.00)',
    ];

    /**
     * Ejecuta el comando.
     */
    public function handle(): int
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════════════════');
        $this->info('     PURGA DE DATOS TRANSACCIONALES - SISTEMA DE GESTIÓN DE EMPEÑOS     ');
        $this->info('═══════════════════════════════════════════════════════════════════════');
        $this->newLine();

        $mantenerClientes = (bool) $this->option('mantener-clientes');

        $this->line('🛡️  <fg=green>TABLAS QUE SE CONSERVAN INTACTAS:</>');
        foreach ($this->tablasProtegidas as $tabla => $descripcion) {
            $this->line("   ✓ <fg=cyan>{$tabla}</>: {$descripcion}");
        }
        $this->newLine();

        $this->line('⚠️  <fg=yellow>TABLAS TRANSACCIONALES QUE SE VACIARÁN (TRUNCATE):</>');
        $tablasParaVaciar = $this->tablasTransaccionales;
        if (!$mantenerClientes) {
            $tablasParaVaciar = array_merge($tablasParaVaciar, $this->tablasClientes);
        } else {
            $this->info('   ℹ️  Modo activo: Conservando clientes.');
        }

        foreach ($tablasParaVaciar as $tabla) {
            $this->line("   ✗ <fg=red>{$tabla}</>");
        }
        $this->newLine();

        if (!$this->option('force')) {
            $confirmado = $this->confirm(
                '¿Confirmas que deseas proceder con el vaciado de los datos transaccionales?',
                false
            );

            if (!$confirmado) {
                $this->warn('Operación cancelada por el usuario. No se modificó ningún dato.');
                return Command::SUCCESS;
            }
        }

        $this->info('⏳ Ejecutando vaciado seguro con llaves foráneas desactivadas...');
        $this->newLine();

        try {
            DB::statement('SET FOREIGN_KEY_CHECKS = 0;');

            $vaciadas = 0;
            $omitidas = 0;

            foreach ($tablasParaVaciar as $tabla) {
                if (Schema::hasTable($tabla)) {
                    DB::table($tabla)->truncate();
                    $this->line("   ✓ Tabla <fg=cyan>{$tabla}</> vaciada.");
                    $vaciadas++;
                } else {
                    $omitidas++;
                }
            }

            // Resetear el saldo de las bóvedas a 0 si existe la tabla
            if (Schema::hasTable('bovedas')) {
                DB::table('bovedas')->update(['saldo_actual' => 0]);
                $this->line('   ✓ Saldos de <fg=cyan>bovedas</> reseteados a 0.00.');
            }

            DB::statement('SET FOREIGN_KEY_CHECKS = 1;');

            $this->newLine();
            $this->info('═══════════════════════════════════════════════════════════════════════');
            $this->info("✅ PURGA COMPLETADA: {$vaciadas} tablas transaccionales vaciadas.");
            $this->info('   Los usuarios, sucursales y configuraciones de productos de crédito');
            $this->info('   están 100% intactos.');
            $this->info('═══════════════════════════════════════════════════════════════════════');
            $this->newLine();

            return Command::SUCCESS;

        } catch (\Throwable $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS = 1;');
            $this->error('❌ Ocurrió un error durante la purga: ' . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
