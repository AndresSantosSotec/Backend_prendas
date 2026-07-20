<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;

/**
 * Seeder Principal para Producción
 * 
 * Este seeder configura el sistema completo para producción con datos reales.
 * 
 * ORDEN DE EJECUCIÓN:
 * 1. Permisos del sistema
 * 2. SuperAdmin (Andres Empenios)
 * 3. Sucursal de Esquipulas
 * 4. Usuarios reales de producción
 * 5. Configuraciones del sistema (Bancos, Tipos de Póliza, Plan de Cuentas, etc.)
 * 
 * EJECUCIÓN:
 * ============
 * Para hacer un reset completo y configurar producción:
 * 
 *   php artisan migrate:fresh --seed --seeder=DatabaseProdSeeder
 * 
 * ADVERTENCIA:
 * Este comando ELIMINARÁ TODOS LOS DATOS existentes y creará
 * una base de datos limpia con solo los datos de producción.
 * 
 * Para agregar solo los usuarios sin borrar nada:
 *   php artisan db:seed --class=UserProdSeeder
 */
class DatabaseProdSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database for production.
     */
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('         INICIALIZANDO SISTEMA DE PRODUCCIÓN          ');
        $this->command->info('                    DigiPrenda                         ');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('');

        // PASO 1: Permisos del sistema
        $this->command->info('🔐 [1/6] Creando permisos del sistema...');
        $this->call([
            PermissionSeeder::class,
            VentasPermisosSeeder::class,
            RefrendosPermisosSeeder::class,
            PlanesInteresPermisosSeeder::class,
            NuevosModulosPermisosSeeder::class,
        ]);
        $this->command->newLine();

        // PASO 2: SuperAdmin
        $this->command->info('👑 [2/6] Creando SuperAdmin...');
        $this->call(CreateSuperAdminSeeder::class);
        $this->command->newLine();

        // PASO 3: Sucursal de producción
        $this->command->info('🏢 [3/6] Creando sucursal de Esquipulas...');
        $this->call(SucursalProdSeeder::class);
        $this->command->newLine();

        // PASO 4: Usuarios de producción
        $this->command->info('👥 [4/6] Creando usuarios de producción...');
        $this->call(UserProdSeeder::class);
        $this->command->newLine();

        // PASO 5: Configuraciones del sistema
        $this->command->info('⚙️  [5/6] Configurando sistema...');
        $this->call([
            CategoriaProductoSeeder::class,
            CamposDinamicosCategoriaSeeder::class,
            BancosSeeder::class,
            TipoPolizaSeeder::class,
            PlanCuentasSeeder::class,
            ParametrizacionCuentasContablesSeeder::class,
            PlanesInteresCategoriaSeeder::class,
            VentaCreditoParametrizacionSeeder::class,
        ]);
        $this->command->newLine();

        // PASO 6: Resumen final
        $this->command->info('📊 [6/6] Generando resumen...');
        $this->mostrarResumen();

        $this->command->info('');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('✅         SISTEMA CONFIGURADO EXITOSAMENTE           ');
        $this->command->info('═══════════════════════════════════════════════════════');
        $this->command->info('');
        $this->command->warn('📝 CREDENCIALES DE ACCESO:');
        $this->command->info('');
        $this->command->line('   SuperAdmin:');
        $this->command->line('   • Email: andres@empenios.com');
        $this->command->line('   • Password: 2905Andres@');
        $this->command->info('');
        $this->command->line('   Administrador Esquipulas:');
        $this->command->line('   • Email: cvinicio1983@gmail.com');
        $this->command->line('   • Username: cvinicio1983');
        $this->command->info('');
        $this->command->warn('⚠️  IMPORTANTE: Los usuarios de producción fueron creados');
        $this->command->warn('   con las contraseñas proporcionadas.');
        $this->command->info('');
    }

    /**
     * Mostrar resumen de la configuración
     */
    private function mostrarResumen(): void
    {
        $users = \App\Models\User::count();
        $sucursales = \App\Models\Sucursal::count();
        $permisos = \App\Models\Permission::count();
        $categorias = \App\Models\CategoriaProducto::count();

        $this->command->newLine();
        $this->command->table(
            ['Concepto', 'Cantidad'],
            [
                ['Usuarios', $users],
                ['Sucursales', $sucursales],
                ['Permisos', $permisos],
                ['Categorías', $categorias],
            ]
        );
    }
}
