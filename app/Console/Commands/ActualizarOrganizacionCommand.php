<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Database\Seeders\AvanzaOrganizacionSeeder;
use Database\Seeders\PrendamasOrganizacionSeeder;
use Illuminate\Support\Facades\Artisan;

class ActualizarOrganizacionCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'organizacion:actualizar 
                            {tenant=avanza : Nombre del tenant u organización (avanza | prendamas)}
                            {--migrate : Ejecutar también migraciones pendientes (--force)}';

    /**
     * The console command description.
     */
    protected $description = 'Actualizar de forma segura parámetros, permisos y configuraciones para Avanza o Prendamás sin pérdida de datos';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $tenant = strtolower(trim($this->argument('tenant')));

        $this->info("========================================================");
        $this->info("  ACTUALIZACIÓN SEGURA DE PRODUCCIÓN: " . strtoupper($tenant));
        $this->info("========================================================");

        // 1. Migraciones si se solicita
        if ($this->option('migrate')) {
            $this->info('Ejecutando migraciones pendientes (sin pérdida de datos)...');
            $exitCode = Artisan::call('migrate', ['--force' => true], $this->output);
            if ($exitCode !== 0) {
                $this->error('Error ejecutando migraciones.');
                return Command::FAILURE;
            }
            $this->info('✓ Migraciones completadas.');
        }

        // 2. Ejecutar el seeder idempotente correspondiente
        if ($tenant === 'avanza') {
            $this->info('Cargando configuraciones y permisos para AVANZA...');
            $seeder = new AvanzaOrganizacionSeeder();
            $seeder->setCommand($this);
            $seeder->run();
        } elseif ($tenant === 'prendamas' || $tenant === 'prendamás') {
            $this->info('Cargando configuraciones y permisos para PRENDAMÁS...');
            $seeder = new PrendamasOrganizacionSeeder();
            $seeder->setCommand($this);
            $seeder->run();
        } else {
            $this->error("Organización '{$tenant}' no reconocida. Opciones disponibles: 'avanza' o 'prendamas'.");
            return Command::FAILURE;
        }

        // 3. Limpiar caché de configuración y rutas para que surta efecto inmediato
        $this->info('Limpiando cachés de Laravel...');
        Artisan::call('optimize:clear', [], $this->output);
        $this->info('✓ Caché optimizado limpiado.');

        $this->info("========================================================");
        $this->info("✓ ACTUALIZACIÓN DE " . strtoupper($tenant) . " COMPLETADA CON ÉXITO.");
        $this->info("========================================================");

        return Command::SUCCESS;
    }
}
