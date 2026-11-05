<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Sucursal;
use App\Models\Permission;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Seeder de Usuarios para MACRO FINANCIERA S.A.
 *
 * Crea o actualiza los 5 usuarios del equipo de Macro Financiera:
 * 1. Brayan Antonio Ajpacajá Puác (Gerente General -> administrador)
 * 2. Adiel Josías Ajpacajá Puác (Contador General -> contador)
 * 3. Sara Victoria Vásquez Puac (Secretaria y Cajera -> cajero)
 * 4. Josué Miguel Vásquez Tax (Asesor de Créditos Prendarios -> cajero/tasador con créditos)
 * 5. Raúl Anderson Tzul Puac (Asesor de Venta de Productos Incobrables -> vendedor)
 *
 * Ejecutar:
 *   php artisan db:seed --class=MacroFinancieraUsuariosSeeder --force
 */
class MacroFinancieraUsuariosSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('====================================================');
        $this->command?->info('👥 CREANDO USUARIOS PARA: MACRO FINANCIERA S.A.');
        $this->command?->info('====================================================');

        // 1. Asegurar que las definiciones de permisos existan en la BD
        if (Schema::hasTable('permissions')) {
            Permission::ensureDefinitionsInDatabase();
            $this->command?->info('✓ Catálogo de permisos sincronizado en la base de datos.');
        }

        // 2. Obtener o crear la sucursal de Macro Financiera
        $sucursal = Sucursal::where('codigo', 'MF-001')->first()
            ?? Sucursal::where('nombre', 'like', '%Macro%')->first()
            ?? Sucursal::first();

        if (!$sucursal) {
            $sucursal = Sucursal::create([
                'nombre' => 'Sede Central Macro Financiera',
                'codigo' => 'MF-001',
                'direccion' => 'Ciudad de Guatemala',
                'ciudad' => 'Guatemala',
                'departamento' => 'Guatemala',
                'municipio' => 'Guatemala',
                'descripcion' => 'Casa Matriz Macro Financiera S.A.',
                'telefono' => '2200-0000',
                'activa' => true,
            ]);
            $this->command?->info("✓ Sucursal creada: {$sucursal->nombre} ({$sucursal->codigo})");
        } else {
            $this->command?->info("✓ Sucursal asignada: {$sucursal->nombre} ({$sucursal->codigo})");
        }

        // Contraseña segura predeterminada para todos los usuarios de Macro Financiera
        $defaultPassword = 'Macro2026@';

        // Definición de los 5 usuarios solicitados
        $usuariosData = [
            [
                'name' => 'Brayan Antonio Ajpacajá Puác',
                'username' => 'brayanajpacaja',
                'email' => 'brayanajpacaja547@gmail.com',
                'password' => Hash::make($defaultPassword),
                'cargo' => 'Gerente General',
                'rol' => 'administrador',
                'activo' => true,
                'sucursal_id' => $sucursal->id,
                'permisos_adicionales' => [],
            ],
            [
                'name' => 'Adiel Josías Ajpacajá Puác',
                'username' => 'adielajpacaja',
                'email' => 'adielajpacaja@gmail.com', // Correo predeterminado asignado
                'password' => Hash::make($defaultPassword),
                'cargo' => 'Contador General',
                'rol' => 'contador',
                'activo' => true,
                'sucursal_id' => $sucursal->id,
                'permisos_adicionales' => [
                    'contabilidad' => ['ver', 'configurar', 'asientos', 'reportes', 'crear_partida', 'editar_partida', 'eliminar_partida'],
                    'cumplimiento' => ['ver', 'exportar'],
                    'reportes' => ['generar', 'exportar'],
                ],
            ],
            [
                'name' => 'Sara Victoria Vásquez Puac',
                'username' => 'svictoriavp',
                'email' => 'svictoriavp@gmail.com',
                'password' => Hash::make($defaultPassword),
                'cargo' => 'Secretaria y Cajera',
                'rol' => 'cajero',
                'activo' => true,
                'sucursal_id' => $sucursal->id,
                'permisos_adicionales' => [
                    'caja' => ['abrir', 'cerrar', 'ver_movimientos'],
                    'cobros' => ['realizar', 'ver', 'imprimir_recibo'],
                    'recibos' => ['ver', 'crear', 'imprimir'],
                ],
            ],
            [
                'name' => 'Josué Miguel Vásquez Tax',
                'username' => 'Vasqueztoto2018',
                'email' => 'Vasqueztoto2018@gmail.com',
                'password' => Hash::make($defaultPassword),
                'cargo' => 'Asesor de Créditos Prendarios',
                'rol' => 'cajero', // Rol base con acceso a créditos
                'activo' => true,
                'sucursal_id' => $sucursal->id,
                'permisos_adicionales' => [
                    'creditos' => ['ver', 'crear'],
                    'simulador' => ['usar', 'imprimir', 'guardar'],
                    'prendas' => ['ver'],
                    'planes_interes' => ['ver'],
                    'clientes' => ['ver', 'crear'],
                    'refrendos' => ['ver', 'validar', 'calcular', 'procesar'],
                ],
            ],
            [
                'name' => 'Raúl Anderson Tzul Puac',
                'username' => 'visiondefuego',
                'email' => 'visiondefuego@hotmail.com',
                'password' => Hash::make($defaultPassword),
                'cargo' => 'Asesor de Venta de Productos de Cuenta Incobrable',
                'rol' => 'vendedor',
                'activo' => true,
                'sucursal_id' => $sucursal->id,
                'permisos_adicionales' => [
                    'ventas' => ['ver', 'vender', 'apartar', 'crear_plan_pago', 'aplicar_descuento'],
                    'cotizaciones' => ['ver', 'crear', 'editar'],
                    'prendas' => ['ver'],
                    'clientes' => ['ver', 'crear'],
                    'recibos' => ['ver', 'crear', 'imprimir'],
                ],
            ],
        ];

        $resumen = [];

        foreach ($usuariosData as $data) {
            $cargo = $data['cargo'];
            $permisosAdicionales = $data['permisos_adicionales'];
            unset($data['cargo'], $data['permisos_adicionales']);

            // Crear o actualizar usuario por su email
            $user = User::updateOrCreate(
                ['email' => $data['email']],
                $data
            );

            // 1. Asignar permisos por defecto de su rol
            $user->assignDefaultPermissions();

            // 2. Si tiene permisos adicionales específicos del cargo, vincularlos
            if (!empty($permisosAdicionales) && Schema::hasTable('permissions')) {
                foreach ($permisosAdicionales as $modulo => $acciones) {
                    foreach ($acciones as $accion) {
                        $p = Permission::where('modulo', $modulo)->where('accion', $accion)->first();
                        if ($p && !$user->permissions()->where('permission_id', $p->id)->exists()) {
                            $user->permissions()->attach($p->id);
                        }
                    }
                }
            }

            $totalPermisos = $user->permissions()->count();

            $this->command?->info("✓ [{$user->rol}] {$user->name}");
            $this->command?->line("   Email:    {$user->email}");
            $this->command?->line("   Username: {$user->username}");
            $this->command?->line("   Cargo:    {$cargo}");
            $this->command?->line("   Permisos: {$totalPermisos} asignados");
            $this->command?->newLine();

            $resumen[] = [
                'Nombre' => $user->name,
                'Email' => $user->email,
                'Cargo' => $cargo,
                'Rol' => $user->rol,
                'Permisos' => $totalPermisos,
            ];
        }

        $this->command?->table(['Nombre', 'Email', 'Cargo', 'Rol', 'Permisos Asignados'], $resumen);

        $this->command?->info('====================================================');
        $this->command?->info("✅ 5 USUARIOS DE MACRO FINANCIERA REGISTRADOS CON ÉXITO");
        $this->command?->info("🔑 Contraseña predeterminada para todos: {$defaultPassword}");
        $this->command?->info('====================================================');
    }
}

