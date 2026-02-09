<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use App\Models\Permission;
use App\Models\User;

return new class extends Migration
{
    /**
     * Permisos del módulo gastos
     */
    private array $permisosGastos = [
        'gastos' => ['ver', 'crear', 'editar', 'eliminar', 'asignar_credito'],
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Crear permisos del módulo gastos
        foreach ($this->permisosGastos as $modulo => $acciones) {
            foreach ($acciones as $accion) {
                Permission::firstOrCreate(
                    ['modulo' => $modulo, 'accion' => $accion],
                    ['descripcion' => ucfirst($accion) . ' en ' . ucfirst($modulo)]
                );
            }
        }

        // Asignar permisos de gastos a usuarios existentes según su rol
        $users = User::all();
        foreach ($users as $user) {
            $permisosRol = $this->getPermisosGastosPorRol($user->rol);

            foreach ($permisosRol as $accion) {
                $permission = Permission::where('modulo', 'gastos')
                    ->where('accion', $accion)
                    ->first();

                if ($permission && !$user->permissions()->where('permission_id', $permission->id)->exists()) {
                    $user->permissions()->attach($permission->id);
                }
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar permisos de gastos de todos los usuarios
        $permisosGastos = Permission::where('modulo', 'gastos')->get();

        foreach ($permisosGastos as $permiso) {
            DB::table('user_permissions')->where('permission_id', $permiso->id)->delete();
        }

        // Eliminar permisos del módulo gastos
        Permission::where('modulo', 'gastos')->delete();
    }

    /**
     * Obtener permisos de gastos según el rol
     */
    private function getPermisosGastosPorRol(string $rol): array
    {
        return match ($rol) {
            'administrador' => ['ver', 'crear', 'editar', 'eliminar', 'asignar_credito'],
            'supervisor' => ['ver', 'crear', 'editar', 'asignar_credito'],
            'cajero' => ['ver', 'asignar_credito'],
            'tasador' => ['ver'],
            'vendedor' => ['ver'],
            default => ['ver'],
        };
    }
};
