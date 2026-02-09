<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class CreateSuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Verificar si ya existe un superadmin
        $superadminExists = User::where('rol', 'superadmin')->exists();

        if ($superadminExists) {
            $this->command->info('Ya existe un usuario SuperAdmin en el sistema.');
            return;
        }

        // Crear usuario SuperAdmin
        $superadmin = User::create([
            'name' => 'Super Administrador',
            'email' => 'superadmin@empenios.com',
            'password' => Hash::make('SuperAdmin2024!'),
            'rol' => 'superadmin',
            'activo' => true,
            'sucursal_id' => null, // Sin sucursal - puede ver TODAS las sucursales
            'password_changed_at' => now(),
        ]);

        $this->command->info('Usuario SuperAdmin creado exitosamente:');
        $this->command->info('Email: superadmin@empenios.com');
        $this->command->info('Password: SuperAdmin2024!');
        $this->command->info('Sucursal: Sin asignar (puede ver TODAS las sucursales)');
        $this->command->warn('¡IMPORTANTE! Cambia la contraseña después del primer login.');
    }
}
