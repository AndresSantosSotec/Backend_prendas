<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            UserSeeder::class,
            PermissionSeeder::class,
            VentasPermisosSeeder::class, // Permisos ventas (aplicar_descuento, etc.)
            RefrendosPermisosSeeder::class,
            PlanesInteresPermisosSeeder::class,
            SucursalSeeder::class,
            CategoriaProductoSeeder::class,
            CamposDinamicosCategoriaSeeder::class,
            BancosSeeder::class,
            TipoPolizaSeeder::class,
            PlanCuentasSeeder::class,
            PlanesInteresCategoriaSeeder::class,
            VentaCreditoParametrizacionSeeder::class,
            CreateSuperAdminSeeder::class,
            NuevosModulosPermisosSeeder::class,
        ]);
    }
}
