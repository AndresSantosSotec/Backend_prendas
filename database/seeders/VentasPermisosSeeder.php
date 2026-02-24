<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Permission;
use App\Models\User;

/**
 * Seeder de permisos del módulo Ventas.
 * Incluye el permiso "aplicar_descuento" (dar descuento) parametrizable por el admin.
 * Ejecutar por si acaso: php artisan db:seed --class=VentasPermisosSeeder
 */
class VentasPermisosSeeder extends Seeder
{
    public function run(): void
    {
        $modulo = 'ventas';
        $acciones = Permission::$permisosPorModulo[$modulo] ?? [
            'ver',
            'tasar',
            'vender',
            'apartar',
            'crear_plan_pago',
            'modificar_precio',
            'aplicar_descuento',
        ];

        foreach ($acciones as $accion) {
            Permission::firstOrCreate(
                ['modulo' => $modulo, 'accion' => $accion],
                ['descripcion' => ucfirst(str_replace('_', ' ', $accion)) . ' en Ventas']
            );
        }

        $this->command->info('Permisos del módulo Ventas creados/actualizados correctamente.');

        // Reasignar permisos por defecto a todos los usuarios (para que aplicar_descuento quede según rol)
        $users = User::all();
        foreach ($users as $user) {
            $user->assignDefaultPermissions();
            $this->command->info("Permisos actualizados para: {$user->name} ({$user->rol})");
        }
    }
}
