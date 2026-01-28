<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class BancosSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * Catálogo de bancos principales de Guatemala
     */
    public function run(): void
    {
        $bancos = [
            [
                'nombre' => 'Banco de Desarrollo Rural, S.A.',
                'abreviatura' => 'BANRURAL',
                'codigo_local' => '001',
                'codigo_swift' => 'RURDGTGC',
                'activo' => true,
            ],
            [
                'nombre' => 'Banco Industrial, S.A.',
                'abreviatura' => 'BI',
                'codigo_local' => '002',
                'codigo_swift' => 'INDLGTGC',
                'activo' => true,
            ],
            [
                'nombre' => 'Banco G&T Continental, S.A.',
                'abreviatura' => 'G&T',
                'codigo_local' => '003',
                'codigo_swift' => 'GTCNGTGC',
                'activo' => true,
            ],
            [
                'nombre' => 'Banco de los Trabajadores',
                'abreviatura' => 'BANTRAB',
                'codigo_local' => '004',
                'codigo_swift' => 'TRABGTGC',
                'activo' => true,
            ],
            [
                'nombre' => 'Banco Agromercantil de Guatemala, S.A.',
                'abreviatura' => 'BAM',
                'codigo_local' => '005',
                'codigo_swift' => 'AGRGGTGC',
                'activo' => true,
            ],
            [
                'nombre' => 'Banco Promerica, S.A.',
                'abreviatura' => 'PROMERICA',
                'codigo_local' => '006',
                'codigo_swift' => 'PROMGTGC',
                'activo' => true,
            ],
            [
                'nombre' => 'Banco de América Central, S.A.',
                'abreviatura' => 'BAC',
                'codigo_local' => '007',
                'codigo_swift' => 'BACCGTGC',
                'activo' => true,
            ],
            [
                'nombre' => 'Banco Inmobiliario, S.A.',
                'abreviatura' => 'BIMBO',
                'codigo_local' => '008',
                'codigo_swift' => 'BIMOGTGC',
                'activo' => true,
            ],
            [
                'nombre' => 'Citibank, N.A.',
                'abreviatura' => 'CITI',
                'codigo_local' => '009',
                'codigo_swift' => 'CITIGTGC',
                'activo' => true,
            ],
            [
                'nombre' => 'Vivibanco, S.A.',
                'abreviatura' => 'VIVIBANCO',
                'codigo_local' => '010',
                'codigo_swift' => 'VIVIGTGC',
                'activo' => true,
            ],
            [
                'nombre' => 'Banco Azteca de Guatemala, S.A.',
                'abreviatura' => 'AZTECA',
                'codigo_local' => '011',
                'codigo_swift' => 'AZTEGTGC',
                'activo' => true,
            ],
            [
                'nombre' => 'Banco Ficohsa Guatemala, S.A.',
                'abreviatura' => 'FICOHSA',
                'codigo_local' => '012',
                'codigo_swift' => 'FICGGTGC',
                'activo' => true,
            ],
        ];

        foreach ($bancos as $banco) {
            DB::table('tb_bancos')->updateOrInsert(
                ['nombre' => $banco['nombre']],
                array_merge($banco, [
                    'created_at' => now(),
                    'updated_at' => now(),
                ])
            );
        }

        $this->command->info('✓ Bancos de Guatemala cargados exitosamente');
    }
}
