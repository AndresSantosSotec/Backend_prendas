<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\User;

class PermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Crear todos los permisos disponibles
        foreach (Permission::$permisosPorModulo as $modulo => $acciones) {
            foreach ($acciones as $accion) {
                Permission::firstOrCreate(
                    ['modulo' => $modulo, 'accion' => $accion],
                    ['descripcion' => ucfirst($accion) . ' en ' . ucfirst($modulo)]
                );
            }
        }

        $this->command->info('Permisos base creados correctamente.');

        // Asignar permisos por defecto a todos los usuarios existentes
        $users = User::all();
        foreach ($users as $user) {
            $user->assignDefaultPermissions();
            $this->command->info("Permisos asignados a: {$user->name} ({$user->rol})");
        }
    }
}
