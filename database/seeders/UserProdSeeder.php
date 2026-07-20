<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\Sucursal;
use Illuminate\Support\Facades\Hash;

/**
 * Seeder de Usuarios para Producción
 *
 * Crea los usuarios reales para la sucursal de Esquipulas.
 * Todos los usuarios obtienen permisos automáticamente según su rol.
 *
 * Ejecutar: php artisan db:seed --class=UserProdSeeder
 */
class UserProdSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('👥 Creando usuarios de producción...');
        $this->command->newLine();

        // Obtener la sucursal de Esquipulas
        $sucursalEsquipulas = Sucursal::where('codigo', 'ESQ-001')->first();

        if (!$sucursalEsquipulas) {
            $this->command->error('❌ Error: No se encontró la sucursal de Esquipulas (ESQ-001)');
            $this->command->error('   Ejecuta primero: php artisan db:seed --class=SucursalProdSeeder');
            return;
        }

        $this->command->info("✓ Sucursal encontrada: {$sucursalEsquipulas->nombre} (ID: {$sucursalEsquipulas->id})");
        $this->command->newLine();

        // Usuarios de producción
        $usuarios = [
            [
                'name' => 'César Vinicio Ortiz de León',
                'username' => 'cvinicio1983',
                'email' => 'cvinicio1983@gmail.com',
                'password' => Hash::make('ME#a$uTrinBg3G@s9R'),
                'rol' => 'administrador',
                'activo' => true,
                'sucursal_id' => $sucursalEsquipulas->id,
            ],
            [
                'name' => 'Angel Wilfredo Antonio Mejía Moreira',
                'username' => 'Angelmoreira9210',
                'email' => 'Angelmoreira9210@gmail.com',
                'password' => Hash::make('SJaS^#qc8^UKb$q%%2'),
                'rol' => 'vendedor',
                'activo' => true,
                'sucursal_id' => $sucursalEsquipulas->id,
            ],
            [
                'name' => 'Grisell Paola López',
                'username' => 'Asesordeventasgrisslopez',
                'email' => 'Asesordeventasgrisslopez@gmail.com',
                'password' => Hash::make('KP^gt#7wUMmRs!BwW6'),
                'rol' => 'vendedor',
                'activo' => true,
                'sucursal_id' => $sucursalEsquipulas->id,
            ],
            [
                'name' => 'Tiffany Rivera',
                'username' => 'tiffany07442',
                'email' => 'tiffany07442@gmail.com',
                'password' => Hash::make('^fgx7Hq5$g^oXoJccH'),
                'rol' => 'administrador',
                'activo' => true,
                'sucursal_id' => $sucursalEsquipulas->id,
            ],
            [
                'name' => 'Sergio Yovani Burgos López',
                'username' => 'sergioburgosgt',
                'email' => 'sergio.burgos.gt@gmail.com',
                'password' => Hash::make('qahGoHML#5Wf79vEGF'),
                'rol' => 'administrador',
                'activo' => true,
                'sucursal_id' => $sucursalEsquipulas->id,
            ],
            [
                'name' => 'Carlos Adrián Maderos',
                'username' => 'adrianmaderos27',
                'email' => 'adrianmaderos27@gmail.com',
                'password' => Hash::make('un9AAjaZJ&wuxpfEaW'),
                'rol' => 'administrador',
                'activo' => true,
                'sucursal_id' => $sucursalEsquipulas->id,
            ],
        ];

        $creados = 0;
        $existentes = 0;

        foreach ($usuarios as $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']], // Buscar por email
                $userData
            );

            if ($user->wasRecentlyCreated) {
                // Asignar permisos automáticamente según el rol
                $user->assignDefaultPermissions();

                $creados++;
                $this->command->info("✅ Usuario creado: {$user->name}");
                $this->command->line("   Username: {$user->username}");
                $this->command->line("   Email: {$user->email}");
                $this->command->line("   Rol: {$user->rol}");
                $this->command->line("   Sucursal: {$sucursalEsquipulas->nombre}");
                $this->command->line("   Permisos: {$user->permissions()->count()} asignados automáticamente");
                $this->command->newLine();
            } else {
                $existentes++;
                $this->command->comment("⏭️  Usuario ya existe: {$user->name} ({$user->email})");
            }
        }

        $this->command->newLine();
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('                     RESUMEN                           ');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info("📊 Total usuarios:      " . count($usuarios));
        $this->command->info("✅ Creados:             {$creados}");
        $this->command->info("⏭️  Ya existían:         {$existentes}");
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->newLine();

        if ($creados > 0) {
            $this->command->info('🎉 Usuarios de producción creados exitosamente');
            $this->command->warn('⚠️  IMPORTANTE: Las contraseñas fueron establecidas según los datos proporcionados.');
        }
    }
}
