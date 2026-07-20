<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Permission;

class AssignMissingPermissions extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'permissions:assign-missing
                            {--force : Reasignar permisos incluso si el usuario ya tiene algunos}
                            {--rol= : Solo asignar a usuarios con un rol específico}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Asignar permisos por defecto a usuarios que no tienen permisos o reasignar según su rol';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('🔐 Asignando permisos a usuarios...');
        $this->newLine();

        // Asegurar que todos los permisos estén en la base de datos
        Permission::ensureDefinitionsInDatabase();
        $this->info('✅ Permisos verificados en base de datos');
        $this->newLine();

        $query = User::with('permissions');

        // Filtrar por rol si se especifica
        if ($rol = $this->option('rol')) {
            $query->where('rol', $rol);
            $this->info("📌 Filtrando usuarios con rol: {$rol}");
        }

        $usuarios = $query->get();
        $totalUsuarios = $usuarios->count();

        if ($totalUsuarios === 0) {
            $this->warn('⚠️  No se encontraron usuarios');
            return 0;
        }

        $this->info("👥 {$totalUsuarios} usuarios encontrados");
        $this->newLine();

        $procesados = 0;
        $actualizados = 0;
        $omitidos = 0;

        foreach ($usuarios as $usuario) {
            $procesados++;
            $tienePermisos = $usuario->permissions->count() > 0;
            $force = $this->option('force');

            $prefix = "[{$procesados}/{$totalUsuarios}]";

            // Si tiene permisos y no se forzó, omitir
            if ($tienePermisos && !$force) {
                $this->line("{$prefix} ⏭️  {$usuario->name} ({$usuario->rol}) - Ya tiene permisos");
                $omitidos++;
                continue;
            }

            // Asignar permisos por defecto según el rol
            $usuario->assignDefaultPermissions();
            $actualizados++;

            $permisos = $usuario->permissions()->count();
            $this->info("{$prefix} ✅ {$usuario->name} ({$usuario->rol}) - {$permisos} permisos asignados");
        }

        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════');
        $this->info('                     RESUMEN                           ');
        $this->info('═══════════════════════════════════════════════════════');
        $this->info("📊 Total usuarios:      {$totalUsuarios}");
        $this->info("✅ Actualizados:        {$actualizados}");
        $this->info("⏭️  Omitidos:            {$omitidos}");
        $this->info('═══════════════════════════════════════════════════════');
        $this->newLine();

        if ($actualizados > 0) {
            $this->info('🎉 Permisos asignados exitosamente');
        } else {
            $this->warn('⚠️  No se asignaron permisos (todos los usuarios ya tenían permisos)');
            $this->info('💡 Usa --force para reasignar permisos según el rol actual');
        }

        return 0;
    }
}
