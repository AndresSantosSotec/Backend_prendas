<?php

namespace Database\Seeders;

use App\Models\Sucursal;
use Illuminate\Database\Seeder;

class SucursalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sucursales = [
            [
                'codigo' => 'SUC-001',
                'nombre' => 'Sucursal Principal',
                'direccion' => 'Zona 1, Ciudad de Guatemala',
                'telefono' => '2222-2222',
                'email' => 'principal@microsystemplus.com',
                'ciudad' => 'Guatemala',
                'departamento' => 'Guatemala',
                'descripcion' => 'Sucursal principal de operaciones',
                'activa' => true,
            ],
            [
                'codigo' => 'SUC-002',
                'nombre' => 'Sucursal Centro',
                'direccion' => 'Zona 4, Ciudad de Guatemala',
                'telefono' => '2222-2223',
                'email' => 'centro@microsystemplus.com',
                'ciudad' => 'Guatemala',
                'departamento' => 'Guatemala',
                'descripcion' => 'Sucursal en el centro de la ciudad',
                'activa' => true,
            ],
            [
                'codigo' => 'SUC-003',
                'nombre' => 'Sucursal Mixco',
                'direccion' => 'Zona 1, Mixco',
                'telefono' => '2222-2224',
                'email' => 'mixco@microsystemplus.com',
                'ciudad' => 'Mixco',
                'departamento' => 'Guatemala',
                'descripcion' => 'Sucursal en Mixco',
                'activa' => true,
            ],
        ];

        foreach ($sucursales as $sucursal) {
            Sucursal::create($sucursal);
        }
    }
}
