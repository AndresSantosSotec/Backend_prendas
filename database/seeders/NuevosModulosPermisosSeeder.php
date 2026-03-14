<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\User;

/**
 * Seeder para crear los permisos de los módulos nuevos y faltantes.
 *
 * Módulos agregados:
 *  - cobros, recibos, historial, otros_gastos, cotizaciones,
 *    planes_interes, transferencias
 *
 * Ejecutar con:
 *   php artisan db:seed --class=NuevosModulosPermisosSeeder
 */
class NuevosModulosPermisosSeeder extends Seeder
{
    /**
     * Módulos nuevos que se deben agregar al sistema.
     * (Solo los que aún no existían en PermissionSeeder original.)
     */
    private array $nuevosModulos = [
        'cobros'        => ['realizar', 'ver', 'imprimir_recibo'],
        'recibos'       => ['ver', 'imprimir'],
        'historial'     => ['ver'],
        'otros_gastos'  => ['ver', 'crear', 'anular'],
        'cotizaciones'  => ['ver', 'crear', 'editar', 'eliminar', 'convertir'],
        'planes_interes' => ['ver', 'crear', 'editar', 'eliminar'],
        'transferencias' => ['ver', 'crear', 'aprobar', 'anular'],
    ];

    /**
     * Permisos adicionales por rol para los nuevos módulos.
     */
    private array $permisosPorRol = [
        'cajero' => [
            'cobros'        => ['realizar', 'ver', 'imprimir_recibo'],
            'recibos'       => ['ver', 'imprimir'],
            'historial'     => ['ver'],
            'otros_gastos'  => ['ver', 'crear'],
            'planes_interes' => ['ver'],
        ],
        'tasador' => [
            'planes_interes' => ['ver'],
        ],
        'vendedor' => [
            'cotizaciones'  => ['ver', 'crear', 'editar'],
        ],
        'supervisor' => [
            'cobros'        => ['realizar', 'ver', 'imprimir_recibo'],
            'recibos'       => ['ver', 'imprimir'],
            'historial'     => ['ver'],
            'otros_gastos'  => ['ver', 'crear', 'anular'],
            'cotizaciones'  => ['ver', 'crear', 'editar', 'eliminar', 'convertir'],
            'planes_interes' => ['ver'],
            'transferencias' => ['ver'],
        ],
    ];

    public function run(): void
    {
        $this->command->info('=== NuevosModulosPermisosSeeder ===');

        // 1. Crear los registros de permisos en la tabla permissions
        $creados = 0;
        foreach ($this->nuevosModulos as $modulo => $acciones) {
            foreach ($acciones as $accion) {
                $permiso = Permission::firstOrCreate(
                    ['modulo' => $modulo, 'accion' => $accion],
                    ['descripcion' => ucfirst(str_replace('_', ' ', $accion)) . ' en ' . ucfirst(str_replace('_', ' ', $modulo))]
                );
                if ($permiso->wasRecentlyCreated) {
                    $creados++;
                    $this->command->info("  [+] {$modulo}.{$accion}");
                } else {
                    $this->command->comment("  [~] {$modulo}.{$accion} (ya existía)");
                }
            }
        }
        $this->command->info("Permisos creados: {$creados}");

        // 2. Asignar los nuevos permisos a usuarios según su rol
        $usuarios = User::all();
        foreach ($usuarios as $usuario) {
            $rol = $usuario->rol;

            // Superadmin y administrador obtienen todos los permisos
            if (in_array($rol, ['superadmin', 'administrador'])) {
                foreach ($this->nuevosModulos as $modulo => $acciones) {
                    foreach ($acciones as $accion) {
                        $permiso = Permission::where('modulo', $modulo)->where('accion', $accion)->first();
                        if ($permiso && !$usuario->permissions()->where('permission_id', $permiso->id)->exists()) {
                            $usuario->permissions()->attach($permiso->id);
                        }
                    }
                }
                $this->command->info("  ✓ {$usuario->name} ({$rol}): todos los permisos nuevos asignados");
                continue;
            }

            // Roles específicos
            if (!isset($this->permisosPorRol[$rol])) {
                $this->command->comment("  - {$usuario->name} ({$rol}): sin cambios");
                continue;
            }

            $asignados = 0;
            foreach ($this->permisosPorRol[$rol] as $modulo => $acciones) {
                foreach ($acciones as $accion) {
                    $permiso = Permission::where('modulo', $modulo)->where('accion', $accion)->first();
                    if ($permiso && !$usuario->permissions()->where('permission_id', $permiso->id)->exists()) {
                        $usuario->permissions()->attach($permiso->id);
                        $asignados++;
                    }
                }
            }
            $this->command->info("  ✓ {$usuario->name} ({$rol}): {$asignados} permisos asignados");
        }

        $this->command->info('=== Seeder completado ===');
    }
}
