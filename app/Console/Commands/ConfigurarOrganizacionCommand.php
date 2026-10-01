<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\ConfiguracionSistema;
use App\Models\TbDoc;
use Database\Seeders\ConfiguracionSistemaSeeder;
use Database\Seeders\TbDocSeeder;

class ConfigurarOrganizacionCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'organizacion:configurar 
                            {--nombre= : Nombre comercial de la empresa}
                            {--razon-social= : Razón social legal}
                            {--nit= : NIT de la empresa}
                            {--direccion= : Dirección principal}
                            {--telefono= : Teléfono / PBX}
                            {--email= : Correo de contacto}
                            {--representante= : Nombre del Representante Legal}
                            {--contador= : Nombre del Perito Contador}
                            {--registro-contador= : Registro profesional del contador}
                            {--reset : Restaurar configuraciones predeterminadas en limpio}';

    /**
     * The console command description.
     */
    protected $description = 'Configurar datos institucionales limpios de la organización en base de datos para nuevas implementaciones';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('========================================================');
        $this->info('  CONFIGURACIÓN INSTITUCIONAL DE ORGANIZACIÓN (WHITE-LABEL)');
        $this->info('========================================================');

        if ($this->option('reset')) {
            $this->warn('Restaurando configuraciones del sistema a valores genéricos iniciales...');
            (new ConfiguracionSistemaSeeder())->run();
            (new TbDocSeeder())->run();
            $this->info('✓ Configuraciones restauradas en limpio.');
            return Command::SUCCESS;
        }

        $nombre = $this->option('nombre') ?: $this->ask('Nombre de la Empresa / Organización', ConfiguracionSistema::obtener('empresa_nombre', 'Sistema de Gestión de Empeños'));
        $razonSocial = $this->option('razon-social') ?: $this->ask('Razón Social Legal', ConfiguracionSistema::obtener('empresa_razon_social', $nombre));
        $nit = $this->option('nit') ?: $this->ask('NIT', ConfiguracionSistema::obtener('empresa_nit', 'C/F'));
        $direccion = $this->option('direccion') ?: $this->ask('Dirección', ConfiguracionSistema::obtener('empresa_direccion', 'Guatemala'));
        $telefono = $this->option('telefono') ?: $this->ask('Teléfono / PBX', ConfiguracionSistema::obtener('empresa_telefono', 'PBX: 2200-0000'));
        $email = $this->option('email') ?: $this->ask('Correo Electrónico', ConfiguracionSistema::obtener('empresa_email', 'contacto@ejemplo.com'));
        $representante = $this->option('representante') ?: $this->ask('Representante Legal', ConfiguracionSistema::obtener('representante_legal_nombre', 'REPRESENTANTE LEGAL'));
        $contador = $this->option('contador') ?: $this->ask('Perito Contador General', ConfiguracionSistema::obtener('perito_contador_nombre', 'PERITO CONTADOR'));
        $registroContador = $this->option('registro-contador') ?: $this->ask('Registro Perito Contador', ConfiguracionSistema::obtener('perito_contador_registro', ''));

        // Guardar en configuraciones_sistema
        ConfiguracionSistema::establecer('empresa_nombre', $nombre);
        ConfiguracionSistema::establecer('empresa_razon_social', $razonSocial);
        ConfiguracionSistema::establecer('empresa_nit', $nit);
        ConfiguracionSistema::establecer('empresa_direccion', $direccion);
        ConfiguracionSistema::establecer('empresa_telefono', $telefono);
        ConfiguracionSistema::establecer('empresa_email', $email);
        ConfiguracionSistema::establecer('representante_legal_nombre', $representante);
        ConfiguracionSistema::establecer('perito_contador_nombre', $contador);
        ConfiguracionSistema::establecer('perito_contador_registro', $registroContador);

        // Actualizar tb_docs para reflejar los nuevos nombres de firmantes y subtítulos
        TbDoc::whereNull('sucursal_id')->update([
            'subtitulo_personalizado' => $nombre,
            'perito_contador_nombre' => $contador,
            'perito_contador_registro' => $registroContador,
            'firmante_1_nombre' => $contador,
            'firmante_2_nombre' => $representante,
        ]);

        $this->newLine();
        $this->info('✓ Datos institucionales guardados exitosamente:');
        $this->table(
            ['Campo', 'Valor Configurado'],
            [
                ['Nombre Comercial', $nombre],
                ['Razón Social', $razonSocial],
                ['NIT', $nit],
                ['Dirección', $direccion],
                ['Teléfono', $telefono],
                ['Email', $email],
                ['Representante Legal', $representante],
                ['Perito Contador', $contador],
                ['Registro Contador', $registroContador],
            ]
        );

        $this->info('Todos los reportes, contratos y recibos generarán automáticamente la identidad de esta organización.');
        return Command::SUCCESS;
    }
}
