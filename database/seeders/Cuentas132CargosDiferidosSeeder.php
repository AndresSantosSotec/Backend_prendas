<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Agrega al plan de cuentas:
 *   132      AMORTIZACIONES ACUMULADAS
 *   13201    CARGOS DIFERIDOS
 *   13201001 Gtos Organiz e Instal
 *
 * Uso:
 *   php artisan db:seed --class=Cuentas132CargosDiferidosSeeder
 */
class Cuentas132CargosDiferidosSeeder extends Seeder
{
    public function run(): void
    {
        $padre132 = DB::table('ctb_nomenclatura')
            ->whereIn('codigo_cuenta', ['13', '1'])
            ->whereNull('deleted_at')
            ->orderByRaw('LENGTH(codigo_cuenta) DESC')
            ->value('id');

        $cuentas = [
            [
                'codigo_cuenta' => '132',
                'nombre_cuenta' => 'AMORTIZACIONES ACUMULADAS',
                'tipo' => 'activo',
                'naturaleza' => 'acreedora',
                'nivel' => 2,
                'codigo_padre' => null,
                'padre_id_fijo' => $padre132,
                'acepta_movimientos' => false,
                'requiere_auxiliar' => false,
                'categoria_flujo' => 'ninguno',
            ],
            [
                'codigo_cuenta' => '13201',
                'nombre_cuenta' => 'CARGOS DIFERIDOS',
                'tipo' => 'activo',
                'naturaleza' => 'deudora',
                'nivel' => 3,
                'codigo_padre' => '132',
                'acepta_movimientos' => false,
                'requiere_auxiliar' => false,
                'categoria_flujo' => 'ninguno',
            ],
            [
                'codigo_cuenta' => '13201001',
                'nombre_cuenta' => 'Gtos Organiz e Instal',
                'tipo' => 'activo',
                'naturaleza' => 'deudora',
                'nivel' => 4,
                'codigo_padre' => '13201',
                'acepta_movimientos' => true,
                'requiere_auxiliar' => false,
                'categoria_flujo' => 'operacion',
            ],
        ];

        foreach ($cuentas as $cuenta) {
            $padreId = $cuenta['padre_id_fijo'] ?? null;
            if (!empty($cuenta['codigo_padre'])) {
                $padreId = DB::table('ctb_nomenclatura')
                    ->where('codigo_cuenta', $cuenta['codigo_padre'])
                    ->whereNull('deleted_at')
                    ->value('id');
            }

            unset($cuenta['codigo_padre'], $cuenta['padre_id_fijo']);

            DB::table('ctb_nomenclatura')->updateOrInsert(
                ['codigo_cuenta' => $cuenta['codigo_cuenta']],
                array_merge($cuenta, [
                    'cuenta_padre_id' => $padreId,
                    'estado' => true,
                    'deleted_at' => null,
                    'updated_at' => now(),
                    'created_at' => now(),
                ])
            );

            $this->command?->info("✓ {$cuenta['codigo_cuenta']} — {$cuenta['nombre_cuenta']}");
        }

        $this->command?->info('Cuentas 132 / 13201 / 13201001 listas.');
    }
}
