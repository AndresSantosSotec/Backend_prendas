<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoPolizaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Tipos de pólizas contables estándar
     */
    public function run(): void
    {
        $tiposPoliza = [
            [
                'codigo' => 'PI',
                'nombre' => 'Póliza de Ingresos',
                'descripcion' => 'Registra ingresos por pagos de créditos, intereses, ventas y otros ingresos operacionales',
                'requiere_aprobacion' => false,
                'usuario_aprobador_rol' => null,
                'activo' => true,
            ],
            [
                'codigo' => 'PE',
                'nombre' => 'Póliza de Egresos',
                'descripcion' => 'Registra egresos por desembolsos de créditos, pagos a proveedores y gastos operacionales',
                'requiere_aprobacion' => false,
                'usuario_aprobador_rol' => null,
                'activo' => true,
            ],
            [
                'codigo' => 'PD',
                'nombre' => 'Póliza de Diario',
                'descripcion' => 'Registra ajustes, reclasificaciones, provisiones y asientos de cierre',
                'requiere_aprobacion' => true,
                'usuario_aprobador_rol' => 'contador',
                'activo' => true,
            ],
            [
                'codigo' => 'PC',
                'nombre' => 'Póliza de Cheques',
                'descripcion' => 'Registra pagos realizados mediante cheque',
                'requiere_aprobacion' => false,
                'usuario_aprobador_rol' => null,
                'activo' => true,
            ],
            [
                'codigo' => 'PT',
                'nombre' => 'Póliza de Transferencias',
                'descripcion' => 'Registra transferencias bancarias y movimientos entre cuentas',
                'requiere_aprobacion' => false,
                'usuario_aprobador_rol' => null,
                'activo' => true,
            ],
        ];

        foreach ($tiposPoliza as $tipo) {
            DB::table('ctb_tipo_poliza')->updateOrInsert(
                ['codigo' => $tipo['codigo']],
                array_merge($tipo, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('✓ Tipos de póliza creados exitosamente');
    }
}
