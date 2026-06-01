<?php

namespace Database\Seeders;

use App\Models\Sucursal;
use Illuminate\Database\Seeder;

/**
 * Seeder de Sucursales para Producción
 *
 * Crea la sucursal de Esquipulas con datos reales.
 *
 * Ejecutar: php artisan db:seed --class=SucursalProdSeeder
 */
class SucursalProdSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📍 Creando sucursal de producción...');

        $sucursal = Sucursal::firstOrCreate(
            ['codigo' => 'ESQ-001'], // Buscar por código
            [
                'nombre' => 'Esquipulas',
                'direccion' => '11 calle 1-08 barrio Quirio Castaño',
                'telefono' => null, // Se puede agregar después
                'email' => null, // Se puede agregar después
                'ciudad' => 'Esquipulas',
                'departamento' => 'Chiquimula',
                'municipio' => 'Esquipulas',
                'pais' => 'Guatemala',
                'descripcion' => 'Sucursal Esquipulas - Casa Matriz',
                'activa' => true,
            ]
        );

        if ($sucursal->wasRecentlyCreated) {
            $this->command->info('✅ Sucursal creada exitosamente:');
        } else {
            $this->command->info('✅ Sucursal ya existe:');
        }

        $this->command->line("   ID: {$sucursal->id}");
        $this->command->line("   Código: {$sucursal->codigo}");
        $this->command->line("   Nombre: {$sucursal->nombre}");
        $this->command->line("   Dirección: {$sucursal->direccion}");
        $this->command->line("   Ubicación: {$sucursal->ciudad}, {$sucursal->departamento}");
        $this->command->newLine();
    }
}
