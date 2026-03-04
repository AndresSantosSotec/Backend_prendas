<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\User;

/**
 * Seeder de permisos del módulo Refrendos.
 *
 * Crea los permisos necesarios para el Sistema de Refrendos Mejorado:
 * - ver: Ver historial de refrendos
 * - validar: Validar si un crédito puede refrendar
 * - calcular: Calcular montos de refrendo
 * - procesar: Procesar refrendos
 *
 * Ejecutar: php artisan db:seed --class=RefrendosPermisosSeeder
 */
class RefrendosPermisosSeeder extends Seeder
{
    public function run(): void
    {
        $modulo = 'refrendos';
        $acciones = Permission::$permisosPorModulo[$modulo] ?? [
            'ver',
            'validar',
            'calcular',
            'procesar',
        ];

        foreach ($acciones as $accion) {
            Permission::firstOrCreate(
                ['modulo' => $modulo, 'accion' => $accion],
                ['descripcion' => ucfirst(str_replace('_', ' ', $accion)) . ' en Refrendos']
            );
        }

        $this->command->info('✅ Permisos del módulo Refrendos creados/actualizados correctamente.');

        // Reasignar permisos por defecto a todos los usuarios
        $users = User::all();
        foreach ($users as $user) {
            $user->assignDefaultPermissions();
            $this->command->info("✅ Permisos actualizados para: {$user->name} ({$user->rol})");
        }

        $this->command->info('');
        $this->command->info('📋 Resumen de permisos de refrendos por rol:');
        $this->command->info('   - superadmin: Todos los permisos');
        $this->command->info('   - administrador: Todos los permisos');
        $this->command->info('   - cajero: ver, validar, calcular, procesar');
        $this->command->info('   - supervisor: ver, validar, calcular, procesar');
        $this->command->info('   - tasador: Sin permisos');
        $this->command->info('   - vendedor: Sin permisos');
    }
}
