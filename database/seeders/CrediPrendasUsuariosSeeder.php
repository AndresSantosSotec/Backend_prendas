<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Sucursal;
use App\Models\Permission;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Seeder de Usuarios para CrediPrendas.
 *
 * 1. Karely Julissa Molina López  — Asistente Administrativa → cajero
 * 2. Melani Odalis López Rivas    — Asistente Administrativa → cajero
 * 3. Rony Estuardo Figueroa Morales — Usuario General / Supervisión → supervisor
 *
 * Ejecutar:
 *   php artisan db:seed --class=CrediPrendasUsuariosSeeder --force
 */
class CrediPrendasUsuariosSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('====================================================');
        $this->command?->info('👥 CREANDO USUARIOS PARA: CREDIPRENDAS');
        $this->command?->info('====================================================');

        if (Schema::hasTable('permissions')) {
            Permission::ensureDefinitionsInDatabase();
            $this->command?->info('✓ Catálogo de permisos sincronizado.');
        }

        $sucursal = Sucursal::where('codigo', 'DEMO-001')->first()
            ?? Sucursal::where('codigo', 'like', '%CREDI%')->first()
            ?? Sucursal::where('nombre', 'like', '%Credi%')->first()
            ?? Sucursal::orderBy('id')->first();

        if (!$sucursal) {
            $sucursal = Sucursal::create([
                'nombre' => 'Sucursal CrediPrendas',
                'codigo' => 'DEMO-001',
                'direccion' => 'Ciudad de Guatemala',
                'ciudad' => 'Guatemala',
                'departamento' => 'Guatemala',
                'descripcion' => 'Sucursal principal CrediPrendas',
                'activa' => true,
            ]);
            $this->command?->info("✓ Sucursal creada: {$sucursal->nombre} ({$sucursal->codigo})");
        } else {
            $this->command?->info("✓ Sucursal asignada: {$sucursal->nombre} ({$sucursal->codigo})");
        }

        // Contraseñas únicas (mostrarlas al final; el usuario debe cambiarlas al primer login)
        $usuariosData = [
            [
                'name' => 'Karely Julissa Molina López',
                'username' => 'karelym',
                'email' => 'Karelym@crediprendas.com',
                'password_plain' => 'KarelyCp2026@',
                'cargo' => 'Asistente Administrativa',
                'rol' => 'cajero',
                'activo' => true,
                'sucursal_id' => $sucursal->id,
                'force_password_change' => true,
                'password_changed_at' => now(),
            ],
            [
                'name' => 'Melani Odalis López Rivas',
                'username' => 'melanil',
                'email' => 'melanil@crediprendas.com',
                'password_plain' => 'MelaniCp2026@',
                'cargo' => 'Asistente Administrativa',
                'rol' => 'cajero',
                'activo' => true,
                'sucursal_id' => $sucursal->id,
                'force_password_change' => true,
                'password_changed_at' => now(),
            ],
            [
                'name' => 'Rony Estuardo Figueroa Morales',
                'username' => 'supervisionadmin',
                'email' => 'supervisionadmin@crediprendas.com',
                'password_plain' => 'RonyCp2026@',
                'cargo' => 'Usuario General',
                'rol' => 'supervisor',
                'activo' => true,
                'sucursal_id' => $sucursal->id,
                'force_password_change' => true,
                'password_changed_at' => now(),
            ],
        ];

        $resumen = [];

        foreach ($usuariosData as $data) {
            $cargo = $data['cargo'];
            $plain = $data['password_plain'];
            unset($data['cargo'], $data['password_plain']);

            $data['password'] = Hash::make($plain);
            // Email case-insensitive: guardar en minúsculas para login consistente
            $emailKey = strtolower(trim($data['email']));
            $data['email'] = $emailKey;

            $user = User::updateOrCreate(
                ['email' => $emailKey],
                $data
            );

            $user->assignDefaultPermissions();
            $totalPermisos = $user->permissions()->count();

            $this->command?->info("✓ [{$user->rol}] {$user->name}");
            $this->command?->line("   Email:    {$user->email}");
            $this->command?->line("   Username: {$user->username}");
            $this->command?->line("   Cargo:    {$cargo}");
            $this->command?->line("   Password: {$plain}");
            $this->command?->line("   Permisos: {$totalPermisos}");
            $this->command?->newLine();

            $resumen[] = [
                'Nombre' => $user->name,
                'Email' => $user->email,
                'Username' => $user->username,
                'Cargo' => $cargo,
                'Rol' => $user->rol,
                'Password' => $plain,
            ];
        }

        $this->command?->table(
            ['Nombre', 'Email', 'Username', 'Cargo', 'Rol', 'Password'],
            $resumen
        );

        $this->command?->info('====================================================');
        $this->command?->info('✅ USUARIOS CREDIPRENDAS REGISTRADOS');
        $this->command?->warn('⚠️  Pídeles cambiar la contraseña en el primer acceso.');
        $this->command?->info('====================================================');
    }
}
