<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Contabilidad\CtbParametrizacionCuenta;
use App\Models\Contabilidad\CtbTipoPoliza;
use App\Models\Contabilidad\CtbNomenclatura;
use Illuminate\Support\Facades\DB;

/**
 * Seeder para parametrización contable de ventas a crédito con plan de pagos
 *
 * INSTRUCCIONES DE USO:
 * 1. Ejecutar: ea-php83 artisan db:seed --class=VentaCreditoParametrizacionSeeder
 * 2. Asegurarse de haber ejecutado PlanCuentasSeeder y TipoPolizaSeeder antes
 *
 * TIPOS DE OPERACIÓN:
 * - venta_credito_enganche: Asiento al generar plan de pagos con enganche
 * - venta_credito_abono: Asiento al registrar abono a cuota
 */
class VentaCreditoParametrizacionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Configurando parametrización contable para Ventas a Crédito...');

        // Usar tipo de póliza PI (Póliza de Ingresos) que ya existe del TipoPolizaSeeder
        $tipoPolizaVenta = CtbTipoPoliza::where('codigo', 'PI')->first();

        if (!$tipoPolizaVenta) {
            $this->command->warn('No existe el tipo de póliza PI. Asegúrate de ejecutar TipoPolizaSeeder primero.');
            return;
        }

        // ============================================================================
        // PARAMETRIZACIÓN: VENTA A CRÉDITO - ENGANCHE
        // DEBE:  Caja General + Créditos Prendarios por Cobrar
        // HABER: Venta de Prendas + Intereses sobre Créditos
        // ============================================================================
        $this->crearParametrizacion('venta_credito_enganche', [
            [
                'cuenta_codigo' => '1101.01.001', // Caja General
                'tipo_movimiento' => 'debe',
                'descripcion' => 'Ingreso de efectivo por enganche',
                'orden' => 1,
            ],
            [
                'cuenta_codigo' => '1101.02.001', // Créditos Prendarios por Cobrar
                'tipo_movimiento' => 'debe',
                'descripcion' => 'Cuentas por cobrar de venta a crédito',
                'orden' => 2,
            ],
            [
                'cuenta_codigo' => '4101.04', // Venta de Prendas
                'tipo_movimiento' => 'haber',
                'descripcion' => 'Ingreso por venta de prenda',
                'orden' => 3,
            ],
            [
                'cuenta_codigo' => '4101.01', // Intereses sobre Créditos Prendarios
                'tipo_movimiento' => 'haber',
                'descripcion' => 'Intereses devengados por venta a crédito',
                'orden' => 4,
            ],
        ], $tipoPolizaVenta->id);

        // ============================================================================
        // PARAMETRIZACIÓN: ABONO A CUOTA DE VENTA A CRÉDITO
        // DEBE:  Caja General
        // HABER: Créditos Prendarios por Cobrar + Intereses + Mora
        // ============================================================================
        $this->crearParametrizacion('venta_credito_abono', [
            [
                'cuenta_codigo' => '1101.01.001', // Caja General
                'tipo_movimiento' => 'debe',
                'descripcion' => 'Ingreso por pago de cuota',
                'orden' => 1,
            ],
            [
                'cuenta_codigo' => '1101.02.001', // Créditos Prendarios por Cobrar
                'tipo_movimiento' => 'haber',
                'descripcion' => 'Disminución de cuentas por cobrar (capital)',
                'orden' => 2,
            ],
            [
                'cuenta_codigo' => '1101.02.002', // Intereses por Cobrar
                'tipo_movimiento' => 'haber',
                'descripcion' => 'Disminución de intereses por cobrar',
                'orden' => 3,
            ],
            [
                'cuenta_codigo' => '4101.02', // Mora sobre Créditos
                'tipo_movimiento' => 'haber',
                'descripcion' => 'Ingreso por mora cobrada',
                'orden' => 4,
            ],
        ], $tipoPolizaVenta->id);

        $this->command->info('✓ Parametrización contable de Ventas a Crédito completada');
    }

    /**
     * Crear registros de parametrización para un tipo de operación
     */
    private function crearParametrizacion(string $tipoOperacion, array $cuentas, int $tipoPolizaId): void
    {
        foreach ($cuentas as $config) {
            // Buscar cuenta contable con el modelo correcto
            $cuenta = CtbNomenclatura::where('codigo_cuenta', $config['cuenta_codigo'])->first();

            if (!$cuenta) {
                $this->command->warn("⚠ Cuenta {$config['cuenta_codigo']} no encontrada. Saltando...");
                continue;
            }

            // Crear o actualizar parametrización
            CtbParametrizacionCuenta::updateOrCreate(
                [
                    'tipo_operacion'   => $tipoOperacion,
                    'cuenta_contable_id' => $cuenta->id,
                    'tipo_movimiento'  => $config['tipo_movimiento'],
                ],
                [
                    'tipo_poliza_id' => $tipoPolizaId,
                    'descripcion'    => $config['descripcion'],
                    'orden'          => $config['orden'],
                    'activo'         => true,
                    'sucursal_id'    => null,
                ]
            );

            $this->command->info("  ✓ {$config['tipo_movimiento']} → {$config['cuenta_codigo']} ({$config['descripcion']})");
        }
    }
}
