<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Permission;
use Illuminate\Support\Facades\Hash;

class CreateSuperAdminSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear o actualizar usuario SuperAdmin
        $superadmin = User::updateOrCreate(
            ['email' => 'andres@empenios.com'],
            [
                'name' => 'Super Administrador',
                'username' => 'andres',
                'password' => Hash::make('2905Andres@'),
                'rol' => 'superadmin',
                'activo' => true,
                'sucursal_id' => null, // Sin sucursal - puede ver TODAS las sucursales
                'password_changed_at' => now(),
            ]
        );

        // Asignar todos los permisos disponibles
        $todosPermisos = Permission::pluck('id')->toArray();
        if (!empty($todosPermisos)) {
            $superadmin->permissions()->sync($todosPermisos);
        }

        $this->command->info('Usuario SuperAdmin configurado exitosamente:');
        $this->command->info('Email: andres@empenios.com');
        $this->command->info('Username: andres');
        $this->command->info('Password: 2905Andres@');
        $this->command->info('Rol: superadmin');
        $this->command->info('Permisos: Todos (' . count($todosPermisos) . ' permisos)');
    }
}
