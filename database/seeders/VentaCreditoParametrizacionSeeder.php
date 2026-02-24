<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Contabilidad\CtbParametrizacionCuenta;
use App\Models\Contabilidad\CtbTipoPoliza;
use Illuminate\Support\Facades\DB;

/**
 * Seeder para parametrización contable de ventas a crédito con plan de pagos
 *
 * INSTRUCCIONES DE USO:
 * 1. Ejecutar: php artisan db:seed --class=VentaCreditoParametrizacionSeeder
 * 2. Asegurarse de tener cuentas contables creadas en el sistema
 * 3. Ajustar las cuentas según el plan de cuentas de la empresa
 *
 * TIPOS DE OPERACIÓN AGREGADOS:
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

        // Obtener tipo de póliza para ventas (debe existir)
        $tipoPolizaVenta = CtbTipoPoliza::where('codigo', 'VENTA')->first();

        if (!$tipoPolizaVenta) {
            $this->command->warn('No existe el tipo de póliza VENTA. Creándolo...');
            $tipoPolizaVenta = CtbTipoPoliza::create([
                'codigo' => 'VENTA',
                'nombre' => 'Ventas',
                'descripcion' => 'Pólizas de ventas de prendas',
                'activo' => true,
            ]);
        }

        // ============================================================================
        // PARAMETRIZACIÓN: VENTA A CRÉDITO - ENGANCHE
        // ============================================================================
        // Asiento cuando se genera plan de pagos con enganche inicial
        //
        // DEBE:
        //   Caja (efectivo del enganche)
        //   Cuentas por Cobrar (saldo financiado + intereses)
        // HABER:
        //   Ingresos por Venta (monto total de la venta)
        //   Intereses por Cobrar Ventas (intereses flat del plan)
        // ============================================================================

        $this->crearParametrizacion('venta_credito_enganche', [
            [
                'cuenta_codigo' => '1.1.1.01', // Caja - Efectivo
                'tipo_movimiento' => 'debe',
                'descripcion' => 'Ingreso de efectivo por enganche',
                'formula' => 'monto_efectivo', // Monto del enganche pagado
                'orden' => 1,
            ],
            [
                'cuenta_codigo' => '1.2.1.01', // Cuentas por Cobrar - Ventas a Crédito
                'tipo_movimiento' => 'debe',
                'descripcion' => 'Cuentas por cobrar de venta a crédito',
                'formula' => 'monto_credito', // Saldo financiado + intereses
                'orden' => 2,
            ],
            [
                'cuenta_codigo' => '4.1.1.01', // Ingresos por Venta de Prendas
                'tipo_movimiento' => 'haber',
                'descripcion' => 'Ingreso por venta de prenda',
                'formula' => 'monto_total', // Monto total de la venta (sin intereses)
                'orden' => 3,
            ],
            [
                'cuenta_codigo' => '4.2.1.01', // Intereses por Cobrar - Ventas
                'tipo_movimiento' => 'haber',
                'descripcion' => 'Intereses devengados por venta a crédito',
                'formula' => 'monto_interes', // Intereses calculados (flat)
                'orden' => 4,
            ],
        ], $tipoPolizaVenta->id);

        // ============================================================================
        // PARAMETRIZACIÓN: ABONO A CUOTA DE VENTA A CRÉDITO
        // ============================================================================
        // Asiento cuando el cliente paga una cuota (mora + interés + capital)
        //
        // DEBE:
        //   Caja (efectivo recibido)
        // HABER:
        //   Cuentas por Cobrar - Ventas (capital)
        //   Intereses por Cobrar - Ventas (intereses)
        //   Ingresos por Mora - Ventas (mora si aplica)
        // ============================================================================

        $this->crearParametrizacion('venta_credito_abono', [
            [
                'cuenta_codigo' => '1.1.1.01', // Caja - Efectivo
                'tipo_movimiento' => 'debe',
                'descripcion' => 'Ingreso por pago de cuota',
                'formula' => 'monto', // Monto total del pago
                'orden' => 1,
            ],
            [
                'cuenta_codigo' => '1.2.1.01', // Cuentas por Cobrar - Ventas a Crédito
                'tipo_movimiento' => 'haber',
                'descripcion' => 'Disminución de cuentas por cobrar (capital)',
                'formula' => 'monto_capital', // Capital pagado
                'orden' => 2,
            ],
            [
                'cuenta_codigo' => '4.2.1.01', // Intereses por Cobrar - Ventas
                'tipo_movimiento' => 'haber',
                'descripcion' => 'Disminución de intereses por cobrar',
                'formula' => 'monto_interes', // Intereses pagados
                'orden' => 3,
            ],
            [
                'cuenta_codigo' => '4.2.2.01', // Ingresos por Mora - Ventas
                'tipo_movimiento' => 'haber',
                'descripcion' => 'Ingreso por mora cobrada',
                'formula' => 'monto_mora', // Mora pagada (si > 0)
                'orden' => 4,
                'solo_si_mayor_cero' => true,
            ],
        ], $tipoPolizaVenta->id);

        $this->command->info('✓ Parametrización contable de Ventas a Crédito completada');
        $this->command->line('');
        $this->command->warn('IMPORTANTE: Revisar y ajustar las cuentas contables según el plan de cuentas de la empresa');
        $this->command->warn('Las cuentas usadas son ejemplos. Editar en: Contabilidad → Parametrización de Cuentas');
    }

    /**
     * Crear registros de parametrización para un tipo de operación
     */
    private function crearParametrizacion(string $tipoOperacion, array $cuentas, int $tipoPolizaId): void
    {
        foreach ($cuentas as $config) {
            // Buscar cuenta contable
            $cuenta = \App\Models\Contabilidad\CtbCuentaContable::where('codigo_cuenta', $config['cuenta_codigo'])->first();

            if (!$cuenta) {
                $this->command->warn("⚠ Cuenta {$config['cuenta_codigo']} no encontrada. Saltando...");
                continue;
            }

            // Verificar si ya existe
            $existe = CtbParametrizacionCuenta::where('tipo_operacion', $tipoOperacion)
                ->where('cuenta_contable_id', $cuenta->id)
                ->where('tipo_movimiento', $config['tipo_movimiento'])
                ->exists();

            if ($existe) {
                $this->command->line("  - Ya existe parametrización: {$tipoOperacion} → {$config['cuenta_codigo']}");
                continue;
            }

            // Crear parametrización
            CtbParametrizacionCuenta::create([
                'tipo_operacion' => $tipoOperacion,
                'tipo_poliza_id' => $tipoPolizaId,
                'cuenta_contable_id' => $cuenta->id,
                'tipo_movimiento' => $config['tipo_movimiento'],
                'descripcion' => $config['descripcion'],
                'formula_monto' => $config['formula'],
                'orden' => $config['orden'],
                'activo' => true,
                'sucursal_id' => null, // Aplica a todas las sucursales
            ]);

            $this->command->info("  ✓ Creada: {$config['tipo_movimiento']} → {$config['cuenta_codigo']} ({$config['descripcion']})");
        }
    }
}
