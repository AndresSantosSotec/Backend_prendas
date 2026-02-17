<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Tabla de parametrización de cuentas contables
     * Mapea operaciones del sistema a cuentas contables
     */
    public function up(): void
    {
        if (!Schema::hasTable('ctb_parametrizacion_cuentas')) {
            Schema::create('ctb_parametrizacion_cuentas', function (Blueprint $table) {
                $table->id();

                // Tipo de operación
                $table->enum('tipo_operacion', [
                    'credito_desembolso',
                    'credito_pago_capital',
                    'credito_pago_interes',
                    'credito_pago_mora',
                    'credito_gastos',
                    'credito_cancelacion',
                    'venta_contado',
                    'venta_credito',
                    'venta_apartado',
                    'venta_enganche',
                    'venta_abono',
                    'compra_directa',
                    'caja_apertura',
                    'caja_cierre',
                    'caja_ingreso',
                    'caja_egreso',
                    'boveda_deposito',
                    'boveda_retiro',
                    'ajuste_inventario',
                    'prenda_recuperada',
                    'prenda_extraviada'
                ])->comment('Tipo de operación del sistema');

                // Movimiento (debe/haber)
                $table->enum('tipo_movimiento', ['debe', 'haber'])->comment('Si es débito o crédito');

                // Cuenta contable
                $table->foreignId('cuenta_contable_id')
                    ->constrained('ctb_nomenclatura')
                    ->onDelete('restrict')
                    ->comment('Cuenta contable asignada');

                // Tipo de póliza recomendada
                $table->foreignId('tipo_poliza_id')
                    ->nullable()
                    ->constrained('ctb_tipo_poliza')
                    ->onDelete('set null')
                    ->comment('Tipo de póliza recomendada');

                // Configuración
                $table->string('descripcion', 255)->nullable()->comment('Descripción del mapeo');
                $table->boolean('activo')->default(true)->comment('Si está activo el mapeo');
                $table->integer('orden')->default(1)->comment('Orden de aplicación');

                // Sucursal específica (opcional)
                $table->foreignId('sucursal_id')
                    ->nullable()
                    ->constrained('sucursales')
                    ->onDelete('cascade')
                    ->comment('Si aplica solo a una sucursal específica');

                $table->timestamps();
                $table->softDeletes();

                // Índices
                $table->index(['tipo_operacion', 'tipo_movimiento', 'activo']);
                $table->index('cuenta_contable_id');
                $table->index(['sucursal_id', 'tipo_operacion']);
                $table->unique(['tipo_operacion', 'tipo_movimiento', 'sucursal_id', 'cuenta_contable_id', 'deleted_at'], 'unique_parametrizacion');
            });
        }

        // Insertar parametrizaciones por defecto
        $this->insertDefaultParametrizaciones();
    }

    /**
     * Insertar parametrizaciones por defecto
     */
    private function insertDefaultParametrizaciones()
    {
        // Obtener IDs de cuentas y tipos de póliza
        $cuentas = $this->getCuentasIds();
        $polizas = $this->getPolizasIds();

        $parametrizaciones = [
            // ===== CRÉDITOS PRENDARIOS =====
            // Desembolso de crédito
            ['credito_desembolso', 'debe', $cuentas['creditos_por_cobrar'], $polizas['egreso'], 'Cuenta por cobrar - Capital del crédito', 1],
            ['credito_desembolso', 'debe', $cuentas['intereses_por_cobrar'], $polizas['egreso'], 'Intereses por cobrar', 2],
            ['credito_desembolso', 'haber', $cuentas['caja'], $polizas['egreso'], 'Salida de efectivo', 3],

            // Pago de capital
            ['credito_pago_capital', 'debe', $cuentas['caja'], $polizas['ingreso'], 'Entrada de efectivo', 1],
            ['credito_pago_capital', 'haber', $cuentas['creditos_por_cobrar'], $polizas['ingreso'], 'Disminución de cuenta por cobrar', 2],

            // Pago de intereses
            ['credito_pago_interes', 'debe', $cuentas['caja'], $polizas['ingreso'], 'Entrada de efectivo', 1],
            ['credito_pago_interes', 'haber', $cuentas['ingresos_intereses'], $polizas['ingreso'], 'Ingreso por intereses', 2],

            // Pago de mora
            ['credito_pago_mora', 'debe', $cuentas['caja'], $polizas['ingreso'], 'Entrada de efectivo', 1],
            ['credito_pago_mora', 'haber', $cuentas['ingresos_mora'], $polizas['ingreso'], 'Ingreso por mora', 2],

            // Gastos de crédito
            ['credito_gastos', 'debe', $cuentas['caja'], $polizas['ingreso'], 'Entrada de efectivo por gastos', 1],
            ['credito_gastos', 'haber', $cuentas['ingresos_comisiones'], $polizas['ingreso'], 'Ingreso por comisiones/gastos', 2],

            // ===== VENTAS =====
            // Venta al contado
            ['venta_contado', 'debe', $cuentas['caja'], $polizas['ingreso'], 'Entrada de efectivo', 1],
            ['venta_contado', 'haber', $cuentas['inventario_prendas_venta'], $polizas['ingreso'], 'Salida de inventario', 2],
            ['venta_contado', 'haber', $cuentas['ventas'], $polizas['ingreso'], 'Ingreso por venta', 3],

            // Venta a crédito (enganche)
            ['venta_enganche', 'debe', $cuentas['caja'], $polizas['ingreso'], 'Entrada de efectivo (enganche)', 1],
            ['venta_enganche', 'debe', $cuentas['creditos_por_cobrar'], $polizas['ingreso'], 'Cuenta por cobrar (saldo)', 2],
            ['venta_enganche', 'haber', $cuentas['inventario_prendas_venta'], $polizas['ingreso'], 'Salida de inventario', 3],
            ['venta_enganche', 'haber', $cuentas['ventas'], $polizas['ingreso'], 'Ingreso por venta', 4],

            // Abono a venta a crédito
            ['venta_abono', 'debe', $cuentas['caja'], $polizas['ingreso'], 'Entrada de efectivo', 1],
            ['venta_abono', 'haber', $cuentas['creditos_por_cobrar'], $polizas['ingreso'], 'Disminución de cuenta por cobrar', 2],

            // ===== COMPRAS =====
            ['compra_directa', 'debe', $cuentas['inventario_prendas_venta'], $polizas['egreso'], 'Incremento de inventario', 1],
            ['compra_directa', 'haber', $cuentas['caja'], $polizas['egreso'], 'Salida de efectivo', 2],

            // ===== CAJA =====
            ['caja_apertura', 'debe', $cuentas['caja'], $polizas['diario'], 'Apertura de caja', 1],
            ['caja_apertura', 'haber', $cuentas['caja_general'], $polizas['diario'], 'Asignación desde caja general', 2],

            ['caja_ingreso', 'debe', $cuentas['caja'], $polizas['ingreso'], 'Ingreso a caja', 1],

            ['caja_egreso', 'haber', $cuentas['caja'], $polizas['egreso'], 'Egreso de caja', 1],

            // ===== BÓVEDA =====
            ['boveda_deposito', 'debe', $cuentas['bancos'], $polizas['diario'], 'Depósito en bóveda', 1],
            ['boveda_deposito', 'haber', $cuentas['caja'], $polizas['diario'], 'Salida de caja', 2],

            ['boveda_retiro', 'debe', $cuentas['caja'], $polizas['diario'], 'Entrada a caja', 1],
            ['boveda_retiro', 'haber', $cuentas['bancos'], $polizas['diario'], 'Retiro de bóveda', 2],
        ];

        foreach ($parametrizaciones as $param) {
            DB::table('ctb_parametrizacion_cuentas')->insert([
                'tipo_operacion' => $param[0],
                'tipo_movimiento' => $param[1],
                'cuenta_contable_id' => $param[2],
                'tipo_poliza_id' => $param[3],
                'descripcion' => $param[4],
                'orden' => $param[5],
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Obtener IDs de cuentas contables por código
     */
    private function getCuentasIds(): array
    {
        $cuentasCodigos = [
            'caja' => '1101.01.001',
            'caja_general' => '1101.01.001',
            'bancos' => '1101.01.003',
            'creditos_por_cobrar' => '1101.02.001',
            'intereses_por_cobrar' => '1101.02.002',
            'mora_por_cobrar' => '1101.02.003',
            'inventario_prendas_custodia' => '1101.03.001',
            'inventario_prendas_venta' => '1101.03.002',
            'ventas' => '4101.04',
            'ingresos_intereses' => '4101.01',
            'ingresos_mora' => '4101.02',
            'ingresos_comisiones' => '4101.03',
            'costo_prendas_vendidas' => '6101',
        ];

        $ids = [];
        foreach ($cuentasCodigos as $key => $codigo) {
            $cuenta = DB::table('ctb_nomenclatura')
                ->where('codigo_cuenta', $codigo)
                ->first();

            if ($cuenta) {
                $ids[$key] = $cuenta->id;
            }
        }

        return $ids;
    }

    /**
     * Obtener IDs de tipos de póliza por código
     */
    private function getPolizasIds(): array
    {
        $polizas = [];

        $polizaIngreso = DB::table('ctb_tipo_poliza')->where('codigo', 'PI')->first();
        $polizas['ingreso'] = $polizaIngreso ? $polizaIngreso->id : null;

        $polizaEgreso = DB::table('ctb_tipo_poliza')->where('codigo', 'PE')->first();
        $polizas['egreso'] = $polizaEgreso ? $polizaEgreso->id : null;

        $polizaDiario = DB::table('ctb_tipo_poliza')->where('codigo', 'PD')->first();
        $polizas['diario'] = $polizaDiario ? $polizaDiario->id : null;

        return $polizas;
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ctb_parametrizacion_cuentas');
    }
};
