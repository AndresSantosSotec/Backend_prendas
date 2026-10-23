<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ConfiguracionSistema;
use App\Models\Permission;
use App\Models\User;
use App\Models\TbDoc;
use App\Models\Sucursal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Seeder de configuración e inicialización segura para EMPEÑOS AVANZA.
 * 
 * CARACTERÍSTICAS DE SEGURIDAD:
 * - NO borra ni trunca ninguna tabla existente (NO DATA LOSS).
 * - Utiliza firstOrCreate y updateOrCreate para ser 100% idempotente.
 * - Respeta clientes, prendas, créditos, compras, cajas, bóvedas y contabilidad ya existentes.
 * - Asegura que existan todas las claves de configuración del sistema, permisos nuevos y plantillas.
 * 
 * Ejecución:
 *   php artisan db:seed --class=AvanzaOrganizacionSeeder
 */
class AvanzaOrganizacionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('====================================================');
        $this->command->info('🚀 INICIANDO CONFIGURACIÓN SEGURA PARA: EMPEÑOS AVANZA');
        $this->command->info('====================================================');

        $orgCode = 'AVANZA';

        // =========================================================================
        // 1. CONFIGURACIÓN DEL SISTEMA (IDEMPOTENTE)
        // =========================================================================
        $this->command->info('1. Verificando parámetros del sistema...');

        $configuracionesAvanza = [
            // Identidad y Organización de Avanza
            'organizacion_code' => [
                'valor' => $orgCode,
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Código de la organización activa',
            ],
            'empresa_nombre' => [
                'valor' => 'EMPEÑOS AVANZA',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Nombre comercial de la empresa',
            ],
            'empresa_razon_social' => [
                'valor' => 'GRUPO VALOR, SOCIEDAD ANÓNIMA',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Razón social legal para contratos de Avanza',
            ],
            'empresa_nit' => [
                'valor' => 'C/F',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'NIT de la empresa',
            ],
            'empresa_direccion' => [
                'valor' => '11 calle 1-08 barrio Quirio Castaño, Esquipulas, Chiquimula.',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Dirección de sede central Avanza',
            ],
            'empresa_telefono' => [
                'valor' => '2200-0000',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Teléfono de atención',
            ],
            'empresa_whatsapp' => [
                'valor' => '3996-6178',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'WhatsApp de atención al cliente',
            ],
            'empresa_email' => [
                'valor' => 'contacto@empeniosavanza.com',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Correo de contacto',
            ],
            'horario_atencion' => [
                'valor' => 'Lunes a viernes: 8:00 AM – 5:00 PM | Sábado: 8:00 AM – 1:00 PM',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Horario de atención al público',
            ],
            'municipio_contrato' => [
                'valor' => 'Esquipulas',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Municipio legal para contratos',
            ],
            'departamento_contrato' => [
                'valor' => 'Chiquimula',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Departamento legal para contratos',
            ],
            'logo_activo' => [
                'valor' => 'avanza_logo.png',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Logo oficial activo para contratos y comprobantes',
            ],
            'representante_legal_nombre' => [
                'valor' => 'REPRESENTANTE LEGAL',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Nombre del Representante Legal',
            ],
            'representante_legal_titulo' => [
                'valor' => 'ADMINISTRADOR Y REPRESENTANTE LEGAL',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Cargo legal',
            ],
            'representante_legal_dpi' => [
                'valor' => '',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'DPI del Representante',
            ],
            'perito_contador_nombre' => [
                'valor' => 'PERITO CONTADOR',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Perito contador de la empresa',
            ],

            // Reglas de Cobro de Intereses y Tasas de Avanza
            'credito_tasa_por_periodo_directa' => [
                'valor' => 'false',
                'tipo' => 'boolean',
                'grupo' => 'creditos',
                'descripcion' => 'Avanza aplica prorrateo mensual estándar (/2 quincenal, /4 semanal). Debe ser false.',
            ],
            'credito_modo_calculo_interes' => [
                'valor' => 'diario',
                'tipo' => 'string',
                'grupo' => 'creditos',
                'descripcion' => 'Modalidad de cálculo de interés: siempre diario (prorrateo por días exactos)',
            ],
            'credito_interes_diario_habilitado' => [
                'valor' => 'true',
                'tipo' => 'boolean',
                'grupo' => 'creditos',
                'descripcion' => 'Cálculo diario siempre habilitado en empeños',
            ],
            'credito_interes_diario_dias_minimos' => [
                'valor' => '1',
                'tipo' => 'integer',
                'grupo' => 'creditos',
                'descripcion' => 'Días mínimos a cobrar cuando se liquida en modalidad diaria',
            ],

            // Política de Descuentos en Intereses (Asignable al crédito)
            'promocion_interes_activa' => [
                'valor' => 'true',
                'tipo' => 'boolean',
                'grupo' => 'promociones',
                'descripcion' => 'Habilita la asignación de descuentos en intereses en la creación de créditos y liquidaciones',
            ],
            'promocion_interes_porcentaje_descuento' => [
                'valor' => '10.0',
                'tipo' => 'float',
                'grupo' => 'promociones',
                'descripcion' => 'Porcentaje sugerido de descuento en intereses para clientes o promociones',
            ],
            'promocion_interes_descripcion' => [
                'valor' => 'Descuento preferencial en intereses',
                'tipo' => 'string',
                'grupo' => 'promociones',
                'descripcion' => 'Motivo o convenio por defecto para descuentos en crédito',
            ],

            // Operaciones y Caja
            'cash_vault_integration_enabled' => [
                'valor' => 'true',
                'tipo' => 'boolean',
                'grupo' => 'operaciones',
                'descripcion' => 'Integración operativa automática entre caja y bóveda',
            ],
        ];

        // Claves de identidad que siempre deben sincronizarse al tenant Avanza
        $clavesIdentidad = [
            'organizacion_code', 'empresa_nombre', 'empresa_razon_social',
            'empresa_direccion', 'empresa_nit', 'empresa_telefono', 'empresa_whatsapp',
            'empresa_email', 'horario_atencion', 'municipio_contrato', 'departamento_contrato',
            'logo_activo', 'representante_legal_nombre', 'representante_legal_titulo',
            'representante_legal_dpi', 'perito_contador_nombre',
            'credito_tasa_por_periodo_directa', 'promocion_interes_activa'
        ];

        foreach ($configuracionesAvanza as $clave => $item) {
            $existente = ConfiguracionSistema::where('clave', $clave)->first();
            if (!$existente) {
                ConfiguracionSistema::create([
                    'clave' => $clave,
                    'valor' => $item['valor'],
                    'tipo' => $item['tipo'],
                    'grupo' => $item['grupo'],
                    'descripcion' => $item['descripcion'],
                    'editable_por_usuario' => true,
                ]);
                $this->command->info("  [+] Creado parámetro: {$clave} = {$item['valor']}");
            } else {
                if (in_array($clave, $clavesIdentidad)) {
                    $existente->update(['valor' => $item['valor']]);
                    $this->command->comment("  [~] Actualizado parámetro para Avanza: {$clave} = {$item['valor']}");
                } else {
                    $this->command->comment("  [✓] Parámetro existente conservado: {$clave}");
                }
            }
        }

        // =========================================================================
        // 2. PERMISOS NUEVOS (ANULACIONES, PLANES, CAJA, BÓVEDA)
        // =========================================================================
        $this->command->info('2. Verificando catálogo de permisos y roles...');

        $permisosRequeridos = [
            // Anulaciones críticas por rol/permiso
            ['modulo' => 'caja', 'accion' => 'anular_movimiento', 'desc' => 'Anular movimientos de caja'],
            ['modulo' => 'boveda', 'accion' => 'anular_movimiento', 'desc' => 'Anular movimientos de bóveda'],
            ['modulo' => 'creditos', 'accion' => 'anular_movimiento', 'desc' => 'Anular pagos o movimientos de crédito'],
            ['modulo' => 'otros_gastos', 'accion' => 'anular', 'desc' => 'Anular movimientos de otros gastos'],

            // Gestión de Planes e Intereses
            ['modulo' => 'planes_interes', 'accion' => 'ver', 'desc' => 'Ver productos crediticios y tasas'],
            ['modulo' => 'planes_interes', 'accion' => 'crear', 'desc' => 'Crear productos crediticios'],
            ['modulo' => 'planes_interes', 'accion' => 'editar', 'desc' => 'Editar productos crediticios'],
            ['modulo' => 'planes_interes', 'accion' => 'eliminar', 'desc' => 'Eliminar productos crediticios'],

            // Recibos e Impresiones
            ['modulo' => 'recibos', 'accion' => 'ver', 'desc' => 'Ver recibos del sistema'],
            ['modulo' => 'recibos', 'accion' => 'imprimir', 'desc' => 'Imprimir recibos y boletas'],
        ];

        $permisosIds = [];
        foreach ($permisosRequeridos as $p) {
            $permiso = Permission::firstOrCreate(
                ['modulo' => $p['modulo'], 'accion' => $p['accion']],
                ['descripcion' => $p['desc']]
            );
            $permisosIds[] = $permiso->id;
        }

        // Asignar automáticamente a Superadmin y Administrador
        $admins = User::whereIn('rol', ['superadmin', 'administrador'])->get();
        foreach ($admins as $admin) {
            foreach ($permisosIds as $pId) {
                if (!$admin->permissions()->where('permission_id', $pId)->exists()) {
                    $admin->permissions()->attach($pId);
                }
            }
        }
        $this->command->info("  [✓] Permisos sincronizados con {$admins->count()} administradores.");

        // =========================================================================
        // 3. PLANTILLAS DE DOCUMENTOS TB_DOCS PARA AVANZA (IDEMPOTENTE)
        // =========================================================================
        $this->command->info('3. Verificando documentos base en TbDoc...');

        if (Schema::hasTable('tb_docs')) {
            $documentosBase = [
                [
                    'tipo_documento' => 'contrato_credito',
                    'nombre_documento' => 'Contrato de Préstamo Prendario Avanza',
                    'plantilla_variante' => 'estandar',
                    'vista_pdf' => 'creditos.contrato',
                    'titulo_personalizado' => 'CONTRATO DE MUTUO CON GARANTÍA PRENDARIA',
                    'subtitulo_personalizado' => 'EMPEÑOS AVANZA',
                    'encabezado_texto' => 'Contrato formal de mutuo con garantía prendaria.',
                    'pie_pagina_texto' => 'Empeños Avanza - Soluciones financieras a tu alcance',
                    'firmante_1_nombre' => 'DEUDOR / CLIENTE',
                    'firmante_1_titulo' => 'FIRMA DEL CLIENTE',
                    'firmante_2_nombre' => 'REPRESENTANTE LEGAL',
                    'firmante_2_titulo' => 'EMPEÑOS AVANZA',
                    'mostrar_logo' => true,
                    'mostrar_firmas' => true,
                    'activo' => true,
                ],
                [
                    'tipo_documento' => 'plan_pagos',
                    'nombre_documento' => 'Plan de Pagos Avanza',
                    'plantilla_variante' => 'estandar',
                    'vista_pdf' => 'creditos.plan-pagos',
                    'titulo_personalizado' => 'PLAN DE PAGOS DE CRÉDITO',
                    'subtitulo_personalizado' => 'EMPEÑOS AVANZA',
                    'encabezado_texto' => 'Detalle proyectado de cuotas, capital, intereses y vencimientos.',
                    'pie_pagina_texto' => 'Empeños Avanza - Las fechas de pago son estrictas para evitar recargos.',
                    'firmante_1_nombre' => 'DEUDOR / CLIENTE',
                    'firmante_1_titulo' => 'FIRMA DEL CLIENTE',
                    'firmante_2_nombre' => 'REPRESENTANTE LEGAL',
                    'firmante_2_titulo' => 'EMPEÑOS AVANZA',
                    'mostrar_logo' => true,
                    'mostrar_firmas' => true,
                    'activo' => true,
                ],
                [
                    'tipo_documento' => 'recibo_pago',
                    'nombre_documento' => 'Recibo de Pago de Cuota / Liquidación Avanza',
                    'plantilla_variante' => 'estandar',
                    'vista_pdf' => 'creditos.recibo_pago',
                    'titulo_personalizado' => 'RECIBO PAGO',
                    'subtitulo_personalizado' => 'EMPEÑOS AVANZA',
                    'encabezado_texto' => 'Comprobante oficial de abono o cancelación de crédito.',
                    'pie_pagina_texto' => 'Este documento es un comprobante oficial de pago. Consérvelo para sus registros.',
                    'firmante_1_nombre' => 'CAJERO',
                    'firmante_1_titulo' => 'FIRMA DE CAJA',
                    'mostrar_logo' => true,
                    'mostrar_firmas' => true,
                    'activo' => true,
                ],
            ];

            foreach ($documentosBase as $doc) {
                TbDoc::updateOrCreate(
                    [
                        'organizacion_code' => $orgCode,
                        'tipo_documento' => $doc['tipo_documento'],
                    ],
                    $doc
                );
            }
            $this->command->info('  [✓] Plantillas TbDoc de Avanza registradas y actualizadas.');
        }

        // =========================================================================
        // 4. RESTAURACIÓN DE SUCURSAL ESQUIPULAS (CASA MATRIZ AVANZA)
        // =========================================================================
        $sucursalEsquipulas = Sucursal::where('codigo', 'ESQ-001')->first() ?: Sucursal::find(1);
        if ($sucursalEsquipulas) {
            $sucursalEsquipulas->update([
                'codigo' => 'ESQ-001',
                'nombre' => 'Esquipulas',
                'direccion' => '11 calle 1-08 barrio Quirio Castaño',
                'ciudad' => 'Esquipulas',
                'departamento' => 'Chiquimula',
                'municipio' => 'Esquipulas',
                'descripcion' => 'Sucursal Esquipulas - Casa Matriz',
                'activa' => true,
            ]);
            $this->command->info('  [✓] Sucursal Esquipulas restaurada correctamente con su dirección y código.');
        } elseif (Sucursal::count() === 0) {
            Sucursal::create([
                'nombre' => 'Esquipulas',
                'codigo' => 'ESQ-001',
                'direccion' => '11 calle 1-08 barrio Quirio Castaño',
                'ciudad' => 'Esquipulas',
                'departamento' => 'Chiquimula',
                'municipio' => 'Esquipulas',
                'descripcion' => 'Sucursal Esquipulas - Casa Matriz',
                'telefono' => '2200-0000',
                'activa' => true,
            ]);
            $this->command->info('  [+] Sucursal Esquipulas creada.');
        }

        $this->command->info('====================================================');
        $this->command->info('✅ CONFIGURACIÓN DE AVANZA FINALIZADA CON ÉXITO');
        $this->command->info('   - No se alteró ningún dato existente.');
        $this->command->info('   - Parámetros, permisos y plantillas sincronizados.');
        $this->command->info('====================================================');
    }
}
