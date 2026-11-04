<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ConfiguracionSistema;
use App\Models\Permission;
use App\Models\User;
use App\Models\TbDoc;
use App\Models\Sucursal;
use Illuminate\Support\Facades\Schema;

/**
 * Seeder de configuración e inicialización segura para MACRO FINANCIERA S.A.
 * 
 * CARACTERÍSTICAS:
 * - 100% idempotente (firstOrCreate / updateOrCreate).
 * - NO borra ni trunca datos existentes (NO DATA LOSS).
 * - Configura la identidad corporativa de Macro Financiera S.A.
 * - Desvincula cualquier referencia a Avanza o a su personal.
 * - Registra las plantillas base de documentos (TbDoc) para contratos, planes y recibos.
 * - Establece el logo corporativo oficial: logos/macro_financiera_logo.png
 * 
 * Ejecución:
 *   php artisan db:seed --class=MacroFinancieraOrganizacionSeeder
 */
class MacroFinancieraOrganizacionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('====================================================');
        $this->command?->info('🚀 INICIANDO CONFIGURACIÓN PARA: MACRO FINANCIERA S.A.');
        $this->command?->info('====================================================');

        $orgCode = 'MACROFINANCIERA';

        // =========================================================================
        // 1. CONFIGURACIÓN DEL SISTEMA (IDEMPOTENTE)
        // =========================================================================
        $this->command?->info('1. Configurando parámetros del sistema para Macro Financiera...');

        $configuracionesMacro = [
            // Identidad y Organización
            'organizacion_code' => [
                'valor' => $orgCode,
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Código de la organización activa',
            ],
            'empresa_nombre' => [
                'valor' => 'MACRO FINANCIERA S.A.',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Nombre comercial de la empresa',
            ],
            'empresa_razon_social' => [
                'valor' => 'MACRO FINANCIERA, SOCIEDAD ANÓNIMA',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Razón social legal para contratos',
            ],
            'empresa_eslogan' => [
                'valor' => 'El lugar donde dimensionamos tus sueños',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Eslogan oficial de la empresa',
            ],
            'empresa_nit' => [
                'valor' => 'C/F',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'NIT de la empresa',
            ],
            'empresa_direccion' => [
                'valor' => 'Ciudad de Guatemala, Guatemala.',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Dirección de sede central',
            ],
            'empresa_telefono' => [
                'valor' => '2200-0000',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Teléfono de atención al cliente',
            ],
            'empresa_whatsapp' => [
                'valor' => '3996-6178',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'WhatsApp de atención',
            ],
            'empresa_email' => [
                'valor' => 'contacto@macrofinanciera.com',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Correo de contacto institucional',
            ],
            'horario_atencion' => [
                'valor' => 'Lunes a viernes: 8:00 AM – 5:00 PM | Sábado: 8:00 AM – 1:00 PM',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Horario de atención al público',
            ],
            'municipio_contrato' => [
                'valor' => 'Guatemala',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Municipio legal para contratos',
            ],
            'departamento_contrato' => [
                'valor' => 'Guatemala',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Departamento legal para contratos',
            ],
            'logo_activo' => [
                'valor' => 'logos/macro_financiera_logo.png',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Logo oficial activo para contratos y comprobantes',
            ],
            'empresa_logo_url' => [
                'valor' => 'logos/macro_financiera_logo.png',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Ruta relativa del logo corporativo',
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
                'descripcion' => 'Cargo legal para firmas',
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

            // Reglas de Cobro de Intereses y Tasas
            'credito_tasa_por_periodo_directa' => [
                'valor' => 'false',
                'tipo' => 'boolean',
                'grupo' => 'creditos',
                'descripcion' => 'Aplica prorrateo mensual estándar (/2 quincenal, /4 semanal)',
            ],
            'credito_modo_calculo_interes' => [
                'valor' => 'diario',
                'tipo' => 'string',
                'grupo' => 'creditos',
                'descripcion' => 'Modalidad de cálculo de interés: diario',
            ],
            'credito_interes_diario_habilitado' => [
                'valor' => 'true',
                'tipo' => 'boolean',
                'grupo' => 'creditos',
                'descripcion' => 'Cálculo diario habilitado en empeños',
            ],
            'credito_interes_diario_dias_minimos' => [
                'valor' => '1',
                'tipo' => 'integer',
                'grupo' => 'creditos',
                'descripcion' => 'Días mínimos a cobrar en liquidación diaria',
            ],

            // Política de Descuentos en Intereses
            'promocion_interes_activa' => [
                'valor' => 'true',
                'tipo' => 'boolean',
                'grupo' => 'promociones',
                'descripcion' => 'Habilita la asignación de descuentos en intereses',
            ],
            'promocion_interes_porcentaje_descuento' => [
                'valor' => '10.0',
                'tipo' => 'float',
                'grupo' => 'promociones',
                'descripcion' => 'Porcentaje sugerido de descuento en intereses',
            ],
            'promocion_interes_descripcion' => [
                'valor' => 'Descuento preferencial en intereses',
                'tipo' => 'string',
                'grupo' => 'promociones',
                'descripcion' => 'Motivo por defecto para descuentos en crédito',
            ],

            // Operaciones y Caja
            'cash_vault_integration_enabled' => [
                'valor' => 'true',
                'tipo' => 'boolean',
                'grupo' => 'operaciones',
                'descripcion' => 'Integración operativa automática entre caja y bóveda',
            ],
        ];

        // Sincronizar parámetros en configuraciones_sistema
        foreach ($configuracionesMacro as $clave => $item) {
            ConfiguracionSistema::updateOrCreate(
                ['clave' => $clave],
                [
                    'valor' => $item['valor'],
                    'tipo' => $item['tipo'],
                    'grupo' => $item['grupo'],
                    'descripcion' => $item['descripcion'],
                    'editable_por_usuario' => true,
                ]
            );
        }
        $this->command?->info('  [✓] Parámetros institucionales de Macro Financiera S.A. establecidos.');

        // =========================================================================
        // 2. PERMISOS Y ROLES
        // =========================================================================
        $this->command?->info('2. Verificando permisos...');

        $permisosRequeridos = [
            ['modulo' => 'caja', 'accion' => 'anular_movimiento', 'desc' => 'Anular movimientos de caja'],
            ['modulo' => 'boveda', 'accion' => 'anular_movimiento', 'desc' => 'Anular movimientos de bóveda'],
            ['modulo' => 'creditos', 'accion' => 'anular_movimiento', 'desc' => 'Anular pagos o movimientos de crédito'],
            ['modulo' => 'otros_gastos', 'accion' => 'anular', 'desc' => 'Anular movimientos de otros gastos'],
            ['modulo' => 'planes_interes', 'accion' => 'ver', 'desc' => 'Ver productos crediticios y tasas'],
            ['modulo' => 'planes_interes', 'accion' => 'crear', 'desc' => 'Crear productos crediticios'],
            ['modulo' => 'planes_interes', 'accion' => 'editar', 'desc' => 'Editar productos crediticios'],
            ['modulo' => 'planes_interes', 'accion' => 'eliminar', 'desc' => 'Eliminar productos crediticios'],
            ['modulo' => 'recibos', 'accion' => 'ver', 'desc' => 'Ver recibos del sistema'],
            ['modulo' => 'recibos', 'accion' => 'imprimir', 'desc' => 'Imprimir recibos y boletas'],
            ['modulo' => 'cotizaciones', 'accion' => 'ver', 'desc' => 'Ver cotizaciones'],
            ['modulo' => 'cotizaciones', 'accion' => 'crear', 'desc' => 'Crear cotizaciones'],
            ['modulo' => 'cotizaciones', 'accion' => 'editar', 'desc' => 'Editar cotizaciones'],
            ['modulo' => 'cotizaciones', 'accion' => 'eliminar', 'desc' => 'Eliminar cotizaciones'],
        ];

        $permisosIds = [];
        foreach ($permisosRequeridos as $p) {
            $permiso = Permission::firstOrCreate(
                ['modulo' => $p['modulo'], 'accion' => $p['accion']],
                ['descripcion' => $p['desc']]
            );
            $permisosIds[] = $permiso->id;
        }

        $admins = User::whereIn('rol', ['superadmin', 'administrador'])->get();
        foreach ($admins as $admin) {
            foreach ($permisosIds as $pId) {
                if (!$admin->permissions()->where('permission_id', $pId)->exists()) {
                    $admin->permissions()->attach($pId);
                }
            }
        }

        // =========================================================================
        // USUARIO SUPERADMIN MAESTRO
        // =========================================================================
        $superadmin = User::updateOrCreate(
            ['email' => 'andres@empenios.com'],
            [
                'name' => 'Super Administrador',
                'username' => 'andres',
                'password' => \Illuminate\Support\Facades\Hash::make('2905Andres@'),
                'rol' => 'superadmin',
                'activo' => true,
                'sucursal_id' => null, // Acceso global a todas las sucursales
                'password_changed_at' => now(),
            ]
        );

        $todosPermisos = Permission::pluck('id')->toArray();
        if (!empty($todosPermisos)) {
            $superadmin->permissions()->sync($todosPermisos);
        }
        $this->command?->info("  [✓] SuperAdmin andres@empenios.com asegurado con 2905Andres@ y permisos totales.");
        $this->command?->info("  [✓] Permisos asignados a {$admins->count()} administradores.");

        // =========================================================================
        // 3. PLANTILLAS DE DOCUMENTOS TB_DOCS PARA MACRO FINANCIERA (IDEMPOTENTE)
        // =========================================================================
        $this->command?->info('3. Creando plantillas de documentos en TbDoc...');

        if (Schema::hasTable('tb_docs')) {
            $piePaginaOficial = 'Macro Financiera S.A. - El lugar donde dimensionamos tus sueños';
            $logoRuta = 'logos/macro_financiera_logo.png';

            $documentosBase = [
                [
                    'tipo_documento' => 'contrato_credito',
                    'nombre_documento' => 'Contrato de Préstamo Prendario Macro Financiera',
                    'plantilla_variante' => 'estandar',
                    'vista_pdf' => 'creditos.contrato',
                    'titulo_personalizado' => 'CONTRATO DE MUTUO CON GARANTÍA PRENDARIA',
                    'subtitulo_personalizado' => 'MACRO FINANCIERA S.A.',
                    'encabezado_texto' => 'Contrato formal de mutuo con garantía prendaria.',
                    'pie_pagina_texto' => $piePaginaOficial,
                    'firmante_1_nombre' => 'DEUDOR / CLIENTE',
                    'firmante_1_titulo' => 'FIRMA DEL CLIENTE',
                    'firmante_2_nombre' => 'REPRESENTANTE LEGAL',
                    'firmante_2_titulo' => 'MACRO FINANCIERA S.A.',
                    'logo_url' => $logoRuta,
                    'mostrar_logo' => true,
                    'mostrar_firmas' => true,
                    'activo' => true,
                ],
                [
                    'tipo_documento' => 'plan_pagos',
                    'nombre_documento' => 'Plan de Pagos Macro Financiera',
                    'plantilla_variante' => 'estandar',
                    'vista_pdf' => 'creditos.plan-pagos',
                    'titulo_personalizado' => 'PLAN DE PAGOS DE CRÉDITO',
                    'subtitulo_personalizado' => 'MACRO FINANCIERA S.A.',
                    'encabezado_texto' => 'Detalle proyectado de cuotas, capital, intereses y vencimientos.',
                    'pie_pagina_texto' => $piePaginaOficial,
                    'firmante_1_nombre' => 'DEUDOR / CLIENTE',
                    'firmante_1_titulo' => 'FIRMA DEL CLIENTE',
                    'firmante_2_nombre' => 'REPRESENTANTE LEGAL',
                    'firmante_2_titulo' => 'MACRO FINANCIERA S.A.',
                    'logo_url' => $logoRuta,
                    'mostrar_logo' => true,
                    'mostrar_firmas' => true,
                    'activo' => true,
                ],
                [
                    'tipo_documento' => 'recibo_pago',
                    'nombre_documento' => 'Recibo de Pago de Cuota / Liquidación Macro Financiera',
                    'plantilla_variante' => 'estandar',
                    'vista_pdf' => 'creditos.recibo_pago',
                    'titulo_personalizado' => 'RECIBO DE PAGO',
                    'subtitulo_personalizado' => 'MACRO FINANCIERA S.A.',
                    'encabezado_texto' => 'Comprobante oficial de abono o cancelación de crédito.',
                    'pie_pagina_texto' => 'Este documento es un comprobante oficial de pago. Consérvelo para sus registros.',
                    'firmante_1_nombre' => 'CAJERO',
                    'firmante_1_titulo' => 'FIRMA DE CAJA',
                    'logo_url' => $logoRuta,
                    'mostrar_logo' => true,
                    'mostrar_firmas' => true,
                    'activo' => true,
                ],
                [
                    'tipo_documento' => 'recibo_compra',
                    'nombre_documento' => 'Comprobante de Compra Directa Macro Financiera',
                    'plantilla_variante' => 'estandar',
                    'vista_pdf' => 'pdf.recibo-compra',
                    'titulo_personalizado' => 'RECIBO DE COMPRA',
                    'subtitulo_personalizado' => 'MACRO FINANCIERA S.A.',
                    'encabezado_texto' => 'Comprobante oficial de adquisición de prenda.',
                    'pie_pagina_texto' => $piePaginaOficial,
                    'firmante_1_nombre' => 'VENDEDOR / CLIENTE',
                    'firmante_1_titulo' => 'FIRMA DEL CLIENTE',
                    'firmante_2_nombre' => 'AGENTE RECEPTOR',
                    'firmante_2_titulo' => 'MACRO FINANCIERA S.A.',
                    'logo_url' => $logoRuta,
                    'mostrar_logo' => true,
                    'mostrar_firmas' => true,
                    'activo' => true,
                ],
                [
                    'tipo_documento' => 'contrato_compra',
                    'nombre_documento' => 'Contrato de Compraventa Macro Financiera',
                    'plantilla_variante' => 'estandar',
                    'vista_pdf' => 'pdf.contrato-compra',
                    'titulo_personalizado' => 'CONTRATO DE COMPRAVENTA',
                    'subtitulo_personalizado' => 'MACRO FINANCIERA S.A.',
                    'encabezado_texto' => 'Contrato de compraventa de bien mueble usado.',
                    'pie_pagina_texto' => $piePaginaOficial,
                    'firmante_1_nombre' => 'VENDEDOR / CLIENTE',
                    'firmante_1_titulo' => 'FIRMA DEL CLIENTE',
                    'firmante_2_nombre' => 'REPRESENTANTE LEGAL',
                    'firmante_2_titulo' => 'MACRO FINANCIERA S.A.',
                    'logo_url' => $logoRuta,
                    'mostrar_logo' => true,
                    'mostrar_firmas' => true,
                    'activo' => true,
                ],
                [
                    'tipo_documento' => 'cotizacion_venta',
                    'nombre_documento' => 'Cotización de Venta Macro Financiera',
                    'plantilla_variante' => 'estandar',
                    'vista_pdf' => 'cotizaciones.contado',
                    'titulo_personalizado' => 'COTIZACIÓN DE VENTA',
                    'subtitulo_personalizado' => 'MACRO FINANCIERA S.A.',
                    'encabezado_texto' => 'Cotización formal de productos de inventario.',
                    'pie_pagina_texto' => $piePaginaOficial,
                    'logo_url' => $logoRuta,
                    'mostrar_logo' => true,
                    'mostrar_firmas' => true,
                    'activo' => true,
                ],
            ];

            foreach ($documentosBase as $doc) {
                // Registrar tanto con el código de organización como global por defecto
                TbDoc::updateOrCreate(
                    [
                        'organizacion_code' => $orgCode,
                        'tipo_documento' => $doc['tipo_documento'],
                    ],
                    $doc
                );
            }
            $this->command?->info('  [✓] Plantillas TbDoc de Macro Financiera registradas exitosamente.');
        }

        // =========================================================================
        // 4. SUCURSAL SEDE CENTRAL MACRO FINANCIERA
        // =========================================================================
        $sucursalMF = Sucursal::where('codigo', 'MF-001')->first() ?: Sucursal::find(1);
        if ($sucursalMF) {
            $sucursalMF->update([
                'codigo' => 'MF-001',
                'nombre' => 'Sede Central Macro Financiera',
                'direccion' => 'Ciudad de Guatemala',
                'ciudad' => 'Guatemala',
                'departamento' => 'Guatemala',
                'municipio' => 'Guatemala',
                'descripcion' => 'Casa Matriz Macro Financiera S.A.',
                'telefono' => '2200-0000',
                'activa' => true,
            ]);
            $this->command?->info('  [✓] Sucursal Sede Central Macro Financiera sincronizada.');
        } elseif (Sucursal::count() === 0) {
            Sucursal::create([
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
            $this->command?->info('  [+] Sucursal Sede Central Macro Financiera creada.');
        }

        $this->command?->info('====================================================');
        $this->command?->info('✅ CONFIGURACIÓN DE MACRO FINANCIERA S.A. FINALIZADA');
        $this->command?->info('   - Razón Social: MACRO FINANCIERA, SOCIEDAD ANÓNIMA');
        $this->command?->info('   - Eslogan: El lugar donde dimensionamos tus sueños');
        $this->command?->info('   - Logo: logos/macro_financiera_logo.png');
        $this->command?->info('====================================================');
    }
}

