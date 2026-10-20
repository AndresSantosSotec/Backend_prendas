<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ConfiguracionSistema;

class ConfiguracionSistemaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * Siembra configuraciones institucionales limpias y agnósticas a cualquier marca.
     */
    public function run(): void
    {
        $configuraciones = [
            // Identidad y Datos Institucionales de la Empresa
            [
                'clave' => 'empresa_nombre',
                'valor' => env('EMPRESA_NOMBRE', env('APP_NAME', 'Sistema de Gestión de Empeños')),
                'tipo' => 'string',
                'grupo' => 'empresa',
                'descripcion' => 'Nombre comercial de la empresa u organización',
                'editable_por_usuario' => true,
            ],
            [
                'clave' => 'empresa_razon_social',
                'valor' => env('EMPRESA_RAZON_SOCIAL', 'Entidad de Servicios Financieros y Prendarios'),
                'tipo' => 'string',
                'grupo' => 'empresa',
                'descripcion' => 'Razón social legal para contratos y facturación',
                'editable_por_usuario' => true,
            ],
            [
                'clave' => 'empresa_nit',
                'valor' => env('EMPRESA_NIT', 'C/F'),
                'tipo' => 'string',
                'grupo' => 'empresa',
                'descripcion' => 'Número de Identificación Tributaria (NIT)',
                'editable_por_usuario' => true,
            ],
            [
                'clave' => 'empresa_direccion',
                'valor' => env('EMPRESA_DIRECCION', 'Ciudad de Guatemala, Guatemala'),
                'tipo' => 'string',
                'grupo' => 'empresa',
                'descripcion' => 'Dirección de la sede central',
                'editable_por_usuario' => true,
            ],
            [
                'clave' => 'empresa_telefono',
                'valor' => env('EMPRESA_TELEFONO', 'PBX: 2200-0000'),
                'tipo' => 'string',
                'grupo' => 'empresa',
                'descripcion' => 'Teléfono o PBX de atención al cliente',
                'editable_por_usuario' => true,
            ],
            [
                'clave' => 'empresa_email',
                'valor' => env('EMPRESA_EMAIL', 'contacto@ejemplo.com'),
                'tipo' => 'string',
                'grupo' => 'empresa',
                'descripcion' => 'Correo electrónico institucional',
                'editable_por_usuario' => true,
            ],
            [
                'clave' => 'empresa_logo_url',
                'valor' => env('EMPRESA_LOGO_URL', null),
                'tipo' => 'string',
                'grupo' => 'empresa',
                'descripcion' => 'Ruta o URL del logotipo oficial',
                'editable_por_usuario' => true,
            ],

            // Autoridades y Firmantes Contables / Legales
            [
                'clave' => 'representante_legal_nombre',
                'valor' => env('REPRESENTANTE_LEGAL_NOMBRE', 'REPRESENTANTE LEGAL'),
                'tipo' => 'string',
                'grupo' => 'firmantes',
                'descripcion' => 'Nombre del Representante Legal para contratos y balances',
                'editable_por_usuario' => true,
            ],
            [
                'clave' => 'perito_contador_nombre',
                'valor' => env('PERITO_CONTADOR_NOMBRE', 'PERITO CONTADOR'),
                'tipo' => 'string',
                'grupo' => 'firmantes',
                'descripcion' => 'Nombre del Contador General / Perito Contador',
                'editable_por_usuario' => true,
            ],
            [
                'clave' => 'perito_contador_registro',
                'valor' => env('PERITO_CONTADOR_REGISTRO', ''),
                'tipo' => 'string',
                'grupo' => 'firmantes',
                'descripcion' => 'Número de registro profesional o colegiado del contador',
                'editable_por_usuario' => true,
            ],

            // Integraciones operativas
            [
                'clave' => 'cash_vault_integration_enabled',
                'valor' => 'true',
                'tipo' => 'boolean',
                'grupo' => 'operaciones',
                'descripcion' => 'Habilitar integración automática entre cajas y bóvedas',
                'editable_por_usuario' => true,
            ],

            // Política de cálculo de tasas de interés
            [
                'clave' => 'credito_tasa_por_periodo_directa',
                'valor' => 'false',
                'tipo' => 'boolean',
                'grupo' => 'creditos',
                'descripcion' => 'Si es true, la tasa de interés se aplica directamente por periodo sin prorrateo. Si es false (default Avanza), la tasa se asume mensual y se prorratea (/2 quincenal, /4 semanal).',
                'editable_por_usuario' => true,
            ],

            // Interés Diario Proporcional (Crediprendas)
            [
                'clave' => 'credito_interes_diario_habilitado',
                'valor' => 'false',
                'tipo' => 'boolean',
                'grupo' => 'creditos',
                'descripcion' => 'Habilitar cobro proporcional de interés diario en lugar de períodos cerrados completos',
                'editable_por_usuario' => true,
            ],
            [
                'clave' => 'credito_interes_diario_dias_minimos',
                'valor' => '1',
                'tipo' => 'integer',
                'grupo' => 'creditos',
                'descripcion' => 'Días mínimos a cobrar cuando se aplica interés diario',
                'editable_por_usuario' => true,
            ],

            // Promociones y Descuentos en Intereses (Lunes y Martes Parametrizables)
            [
                'clave' => 'promocion_interes_activa',
                'valor' => 'false',
                'tipo' => 'boolean',
                'grupo' => 'promociones',
                'descripcion' => 'Activar promociones de descuento en intereses para días específicos de la semana (ej: Lunes o Martes)',
                'editable_por_usuario' => true,
            ],
            [
                'clave' => 'promocion_interes_dias',
                'valor' => '["monday", "tuesday"]',
                'tipo' => 'json',
                'grupo' => 'promociones',
                'descripcion' => 'Días de la semana en los que aplica el descuento de intereses (ej: monday, tuesday / lunes, martes)',
                'editable_por_usuario' => true,
            ],
            [
                'clave' => 'promocion_interes_porcentaje_descuento',
                'valor' => '10.0',
                'tipo' => 'float',
                'grupo' => 'promociones',
                'descripcion' => 'Porcentaje de descuento en intereses durante los días de promoción',
                'editable_por_usuario' => true,
            ],
            [
                'clave' => 'promocion_interes_descripcion',
                'valor' => 'Promoción Lunes y Martes de Descuento en Intereses',
                'tipo' => 'string',
                'grupo' => 'promociones',
                'descripcion' => 'Descripción de la promoción aplicada a los intereses',
                'editable_por_usuario' => true,
            ],
        ];

        foreach ($configuraciones as $config) {
            ConfiguracionSistema::updateOrCreate(
                ['clave' => $config['clave']],
                $config
            );
        }
    }
}
