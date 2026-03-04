<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PlanesInteresPermisosSeeder extends Seeder
{
    /**
     * Crear permisos para el módulo de planes de interés
     */
    public function run(): void
    {
        DB::beginTransaction();

        try {
            $this->command->info('🔐 Creando permisos del módulo planes_interes...');

            // Definir permisos del módulo
            $permisos = [
                ['modulo' => 'planes_interes', 'accion' => 'ver', 'descripcion' => 'Ver planes de interés'],
                ['modulo' => 'planes_interes', 'accion' => 'crear', 'descripcion' => 'Crear nuevos planes de interés'],
                ['modulo' => 'planes_interes', 'accion' => 'editar', 'descripcion' => 'Editar planes de interés existentes'],
                ['modulo' => 'planes_interes', 'accion' => 'eliminar', 'descripcion' => 'Eliminar planes de interés'],
            ];

            // Crear o actualizar permisos
            foreach ($permisos as $permiso) {
                Permission::updateOrCreate(
                    ['modulo' => $permiso['modulo'], 'accion' => $permiso['accion']],
                    ['descripcion' => $permiso['descripcion']]
                );

                $this->command->line("  ✓ {$permiso['modulo']}.{$permiso['accion']}");
            }

            $this->command->info('✅ Permisos creados exitosamente');
            $this->command->newLine();

            // Asignar permisos a usuarios según rol
            $this->asignarPermisosPorRol();

            DB::commit();

            $this->mostrarResumen();

        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error('❌ Error al crear permisos: ' . $e->getMessage());
        }
    }

    /**
     * Asignar permisos según el rol del usuario
     */
    private function asignarPermisosPorRol(): void
    {
        $this->command->info('👥 Asignando permisos a usuarios...');

        $usuarios = User::all();
        $permisosModulo = Permission::where('modulo', 'planes_interes')->get();

        foreach ($usuarios as $usuario) {
            $permisosAsignar = $this->obtenerPermisosPorRol($usuario->rol, $permisosModulo);

            if (!empty($permisosAsignar)) {
                $usuario->assignDefaultPermissions($permisosAsignar);
                $this->command->line("  ✓ {$usuario->name} ({$usuario->rol})");
            }
        }
    }

    /**
     * Determinar qué permisos corresponden a cada rol
     */
    private function obtenerPermisosPorRol(string $rol, $permisosModulo): array
    {
        return match (strtolower($rol)) {
            'superadmin', 'administrador' => [
                // Todos los permisos
                'planes_interes.ver',
                'planes_interes.crear',
                'planes_interes.editar',
                'planes_interes.eliminar',
            ],
            'gerente' => [
                // Gerentes pueden ver, crear y editar
                'planes_interes.ver',
                'planes_interes.crear',
                'planes_interes.editar',
            ],
            'cajero', 'supervisor' => [
                // Cajeros y supervisores solo pueden ver
                'planes_interes.ver',
            ],
            default => [
                // Otros roles: sin permisos por defecto
            ],
        };
    }

    /**
     * Mostrar resumen de permisos asignados
     */
    private function mostrarResumen(): void
    {
        $this->command->newLine();
        $this->command->info('📊 RESUMEN DE ASIGNACIÓN:');
        $this->command->newLine();

        $usuarios = User::with(['permissions' => function ($query) {
            $query->where('modulo', 'planes_interes');
        }])->get();

        $resumenPorRol = [];

        foreach ($usuarios as $usuario) {
            $rol = $usuario->rol;
            $permisos = $usuario->permissions->where('modulo', 'planes_interes')->pluck('accion')->toArray();

            if (!isset($resumenPorRol[$rol])) {
                $resumenPorRol[$rol] = [
                    'count' => 0,
                    'permisos' => $permisos
                ];
            }

            $resumenPorRol[$rol]['count']++;
        }

        foreach ($resumenPorRol as $rol => $data) {
            $this->command->line("  👤 {$rol}: {$data['count']} usuario(s)");
            if (!empty($data['permisos'])) {
                $this->command->line("     Permisos: " . implode(', ', $data['permisos']));
            } else {
                $this->command->line("     Sin permisos asignados");
            }
            $this->command->newLine();
        }

        $totalUsuarios = $usuarios->count();
        $usuariosConPermisos = $usuarios->filter(function ($u) {
            return $u->permissions->where('modulo', 'planes_interes')->count() > 0;
        })->count();

        $this->command->info("✅ {$usuariosConPermisos} de {$totalUsuarios} usuarios actualizados");
    }
}
