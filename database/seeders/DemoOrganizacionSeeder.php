<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ConfiguracionSistema;
use App\Models\Permission;
use App\Models\User;
use App\Models\TbDoc;
use App\Models\Sucursal;
use App\Models\Boveda;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

/**
 * Seeder Maestro de Configuración por Defecto para Entorno DEMO (DIGIPRENDA DEMO).
 *
 * CARACTERÍSTICAS:
 * - 100% idempotente (updateOrCreate / firstOrCreate).
 * - Configura toda la identidad institucional genérica para demostraciones a clientes.
 * - Sin referencias a Avanza, Prendamás ni Macro Financiera.
 * - Crea plantillas de documentos (TbDoc) limpias para Contratos, Recibos y Planes de Pago.
 * - Crea Sucursal Demo (DEMO-001) y Bóveda Principal Demo con saldo inicial operativo.
 * - Crea usuario SuperAdmin maestro (andres@empenios.com / 2905Andres@) y usuarios de prueba por rol.
 * - Ejecuta todos los catálogos base (13 categorías, campos dinámicos, plan de cuentas, bancos, etc.).
 *
 * Ejecución:
 *   php artisan db:seed --class=DemoOrganizacionSeeder --force
 */
class DemoOrganizacionSeeder extends Seeder
{
    public function run(): void
    {
        $this->command?->info('========================================================');
        $this->command?->info('🚀 INICIANDO CONFIGURACIÓN POR DEFECTO: DIGIPRENDA DEMO');
        $this->command?->info('========================================================');

        $orgCode = 'DEMO';

        // =========================================================================
        // 1. CONFIGURACIÓN DEL SISTEMA (IDEMPOTENTE)
        // =========================================================================
        $this->command?->info('1. Configurando parámetros institucionales de la Demo...');

        $configuracionesDemo = [
            'organizacion_code' => [
                'valor' => $orgCode,
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Código de la organización activa',
            ],
            'empresa_nombre' => [
                'valor' => 'DIGIPRENDA DEMO',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Nombre comercial de la empresa demostración',
            ],
            'empresa_razon_social' => [
                'valor' => 'CORPORACIÓN FINANCIERA DEMO, SOCIEDAD ANÓNIMA',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Razón social legal para contratos y documentos',
            ],
            'empresa_eslogan' => [
                'valor' => 'Gestión Inteligente de Créditos Prendarios',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Eslogan corporativo',
            ],
            'empresa_nit' => [
                'valor' => 'CF',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'NIT de la empresa',
            ],
            'empresa_direccion' => [
                'valor' => 'Avenida Reforma 10-00 Zona 9, Ciudad de Guatemala.',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Dirección fiscal y comercial',
            ],
            'empresa_municipio' => [
                'valor' => 'Guatemala',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Municipio sede para encabezados de contratos',
            ],
            'empresa_departamento' => [
                'valor' => 'Guatemala',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Departamento sede para encabezados de contratos',
            ],
            'empresa_telefono' => [
                'valor' => '2200-0000',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Teléfono principal',
            ],
            'empresa_whatsapp' => [
                'valor' => '5000-0000',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'WhatsApp de atención al cliente',
            ],
            'empresa_email' => [
                'valor' => 'info@digiprenda-demo.com',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Correo electrónico corporativo',
            ],
            'representante_legal_nombre' => [
                'valor' => 'REPRESENTANTE LEGAL DEMO',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Nombre del Representante Legal en contratos',
            ],
            'representante_legal_titulo' => [
                'valor' => 'ADMINISTRADOR ÚNICO Y REPRESENTANTE LEGAL',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Cargo legal del representante',
            ],
            'representante_legal_dpi' => [
                'valor' => '2500 00000 0101',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'DPI del representante legal',
            ],
            'representante_legal_edad' => [
                'valor' => 'treinta y cinco',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Edad en letras del representante legal',
            ],
            'representante_legal_estado_civil' => [
                'valor' => 'casado',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Estado civil del representante legal',
            ],
            'representante_legal_profesion' => [
                'valor' => 'Administrador de Empresas',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Profesión u oficio del representante legal',
            ],
            'representante_legal_nacionalidad' => [
                'valor' => 'guatemalteco',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Nacionalidad del representante legal',
            ],
            'perito_contador_nombre' => [
                'valor' => 'PERITO CONTADOR DEMO',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Nombre del Perito Contador para reportes financieros',
            ],
            'perito_contador_registro' => [
                'valor' => 'REG-00000',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Registro del Perito Contador',
            ],
            'horario_atencion' => [
                'valor' => 'Lunes a viernes: 8:00 AM – 5:00 PM | Sábado: 8:00 AM – 1:00 PM',
                'tipo' => 'string',
                'grupo' => 'organizacion',
                'descripcion' => 'Horario de atención impreso en recibos y contratos',
            ],
            'cash_vault_integration_enabled' => [
                'valor' => 'true',
                'tipo' => 'boolean',
                'grupo' => 'caja_boveda',
                'descripcion' => 'Activa la integración de saldos entre Caja y Bóveda',
            ],
            'credito_tasa_por_periodo_directa' => [
                'valor' => 'false',
                'tipo' => 'boolean',
                'grupo' => 'creditos',
                'descripcion' => 'Usa cálculo estándar de tasa mensual prorrateada por período',
            ],
        ];

        if (Schema::hasTable('configuraciones_sistema')) {
            foreach ($configuracionesDemo as $clave => $datos) {
                ConfiguracionSistema::updateOrCreate(
                    ['clave' => $clave],
                    [
                        'valor' => $datos['valor'],
                        'tipo' => $datos['tipo'],
                        'grupo' => $datos['grupo'],
                        'descripcion' => $datos['descripcion'],
                        'editable_por_usuario' => true,
                    ]
                );
            }
            $this->command?->info('  [✓] Parámetros de configuración Demo aplicados.');
        }

        // =========================================================================
        // 2. SUCURSAL DEMO Y BÓVEDA OPERATIVA
        // =========================================================================
        $this->command?->info('2. Configurando Sucursal y Bóveda Demo...');

        $sucursal = Sucursal::updateOrCreate(
            ['codigo' => 'DEMO-001'],
            [
                'nombre' => 'Agencia Central Demo',
                'direccion' => 'Avenida Reforma 10-00 Zona 9, Ciudad de Guatemala',
                'ciudad' => 'Guatemala',
                'departamento' => 'Guatemala',
                'municipio' => 'Guatemala',
                'descripcion' => 'Sucursal Principal de Demostración',
                'telefono' => '2200-0000',
                'email' => 'central@digiprenda-demo.com',
                'activa' => true,
            ]
        );
        $this->command?->info("  [✓] Sucursal asegurada: {$sucursal->nombre} ({$sucursal->codigo})");

        if (Schema::hasTable('bovedas')) {
            $boveda = Boveda::firstOrCreate(
                ['codigo' => 'BOV-DEMO-01'],
                [
                    'nombre' => 'Bóveda Principal Demo',
                    'descripcion' => 'Bóveda inicial para pruebas y demostraciones con Q100,000.00 de fondo.',
                    'sucursal_id' => $sucursal->id,
                    'tipo' => 'principal',
                    'saldo_actual' => 100000.00,
                    'saldo_minimo' => 5000.00,
                    'saldo_maximo' => 500000.00,
                    'activa' => true,
                ]
            );
            $this->command?->info("  [✓] Bóveda asegurada: {$boveda->nombre} (Saldo: Q" . number_format((float)$boveda->saldo_actual, 2) . ")");
        }

        // =========================================================================
        // 3. PERMISOS Y USUARIOS DE DEMOSTRACIÓN
        // =========================================================================
        $this->command?->info('3. Sincronizando permisos y usuarios de demostración...');

        if (Schema::hasTable('permissions')) {
            Permission::ensureDefinitionsInDatabase();
        }

        // 3.1 SuperAdmin Maestro
        $superadmin = User::updateOrCreate(
            ['email' => 'andres@empenios.com'],
            [
                'name' => 'Super Administrador',
                'username' => 'andres',
                'password' => Hash::make('2905Andres@'),
                'rol' => 'superadmin',
                'activo' => true,
                'sucursal_id' => null,
                'password_changed_at' => now(),
            ]
        );

        // 3.2 Usuarios Demo por Rol (Contraseña general: Demo2026@)
        $usuariosDemo = [
            [
                'name' => 'Administrador Demo',
                'username' => 'admin_demo',
                'email' => 'admin@demo.com',
                'password' => Hash::make('Demo2026@'),
                'rol' => 'administrador',
                'activo' => true,
                'sucursal_id' => $sucursal->id,
            ],
            [
                'name' => 'Contador General Demo',
                'username' => 'contador_demo',
                'email' => 'contador@demo.com',
                'password' => Hash::make('Demo2026@'),
                'rol' => 'contador',
                'activo' => true,
                'sucursal_id' => $sucursal->id,
            ],
            [
                'name' => 'Cajero Demo',
                'username' => 'cajero_demo',
                'email' => 'cajero@demo.com',
                'password' => Hash::make('Demo2026@'),
                'rol' => 'cajero',
                'activo' => true,
                'sucursal_id' => $sucursal->id,
            ],
            [
                'name' => 'Vendedor Demo',
                'username' => 'vendedor_demo',
                'email' => 'vendedor@demo.com',
                'password' => Hash::make('Demo2026@'),
                'rol' => 'vendedor',
                'activo' => true,
                'sucursal_id' => $sucursal->id,
            ],
        ];

        $todosPermisos = Permission::pluck('id')->toArray();
        if (!empty($todosPermisos)) {
            $superadmin->permissions()->sync($todosPermisos);
        }

        foreach ($usuariosDemo as $uData) {
            $u = User::updateOrCreate(
                ['email' => $uData['email']],
                $uData
            );
            $u->assignDefaultPermissions();
        }
        $this->command?->info('  [✓] SuperAdmin (andres@empenios.com / 2905Andres@) y usuarios Demo (Demo2026@) listos.');

        // =========================================================================
        // 4. PLANTILLAS DE DOCUMENTOS TB_DOCS PARA DEMO (IDEMPOTENTE)
        // =========================================================================
        if (Schema::hasTable('tb_docs')) {
            $this->command?->info('4. Configurando plantillas de documentos PDF para Demo...');

            $plantillasDemo = [
                [
                    'tipo_documento' => 'contrato_credito',
                    'nombre_documento' => 'Contrato de Mutuo con Garantía Prendaria - Demo',
                    'vista_pdf' => 'pdf.contrato-credito',
                    'plantilla_variante' => 'avanza',
                    'titulo_personalizado' => 'CONTRATO DE MUTUO CON GARANTÍA PRENDARIA',
                    'subtitulo_personalizado' => 'DIGIPRENDA DEMO — Gestión Inteligente de Créditos Prendarios',
                    'encabezado_texto' => 'CORPORACIÓN FINANCIERA DEMO, SOCIEDAD ANÓNIMA',
                    'pie_pagina_texto' => 'DIGIPRENDA DEMO | Avenida Reforma 10-00 Zona 9, Ciudad de Guatemala | Tel: 2200-0000',
                    'firmante_1_nombre' => 'EL DEUDOR PRENDARIO',
                    'firmante_1_titulo' => 'FIRMA Y HUELLA DIGITAL DEL CLIENTE',
                    'firmante_2_nombre' => 'REPRESENTANTE LEGAL DEMO',
                    'firmante_2_titulo' => 'POR DIGIPRENDA DEMO',
                ],
                [
                    'tipo_documento' => 'plan_pagos',
                    'nombre_documento' => 'Plan de Pagos - Demo',
                    'vista_pdf' => 'pdf.plan-pagos',
                    'plantilla_variante' => 'estandar',
                    'titulo_personalizado' => 'PLAN DE PAGOS Y AMORTIZACIÓN DE CRÉDITO',
                    'subtitulo_personalizado' => 'DIGIPRENDA DEMO',
                    'encabezado_texto' => 'CORPORACIÓN FINANCIERA DEMO, S.A.',
                    'pie_pagina_texto' => 'Conserve este calendario de pagos para el control de sus cuotas.',
                    'firmante_1_nombre' => 'CLIENTE',
                    'firmante_1_titulo' => 'ENTERADO Y CONFORME',
                    'firmante_2_nombre' => 'ASESOR DE CRÉDITOS',
                    'firmante_2_titulo' => 'DIGIPRENDA DEMO',
                ],
                [
                    'tipo_documento' => 'recibo_pago',
                    'nombre_documento' => 'Recibo de Caja - Demo',
                    'vista_pdf' => 'pdf.recibo-pago',
                    'plantilla_variante' => 'estandar',
                    'titulo_personalizado' => 'COMPROBANTE DE PAGO',
                    'subtitulo_personalizado' => 'DIGIPRENDA DEMO',
                    'encabezado_texto' => 'DIGIPRENDA DEMO — Servicios Prendarios',
                    'pie_pagina_texto' => 'Gracias por su pago puntual.',
                    'firmante_1_nombre' => 'CLIENTE',
                    'firmante_1_titulo' => 'CONFORME',
                    'firmante_2_nombre' => 'CAJERO RECEPTOR',
                    'firmante_2_titulo' => 'SELLO Y FIRMA',
                ],
                [
                    'tipo_documento' => 'estado_cuenta',
                    'nombre_documento' => 'Estado de Cuenta - Demo',
                    'vista_pdf' => 'pdf.estado-cuenta',
                    'plantilla_variante' => 'estandar',
                    'titulo_personalizado' => 'ESTADO DE CUENTA DE CRÉDITO PRENDARIO',
                    'subtitulo_personalizado' => 'DIGIPRENDA DEMO',
                    'encabezado_texto' => 'CORPORACIÓN FINANCIERA DEMO, S.A.',
                    'pie_pagina_texto' => 'Documento informativo emitido por el sistema DigiPrenda Demo.',
                    'firmante_1_nombre' => 'CLIENTE',
                    'firmante_1_titulo' => 'FIRMA',
                    'firmante_2_nombre' => 'DEPARTAMENTO DE CARTERA',
                    'firmante_2_titulo' => 'DIGIPRENDA DEMO',
                ],
                [
                    'tipo_documento' => 'ficha_cliente',
                    'nombre_documento' => 'Ficha de Registro de Cliente - Demo',
                    'vista_pdf' => 'pdf.ficha-cliente',
                    'plantilla_variante' => 'estandar',
                    'titulo_personalizado' => 'FICHA DE CONOCIMIENTO DEL CLIENTE (KYC)',
                    'subtitulo_personalizado' => 'DIGIPRENDA DEMO',
                    'encabezado_texto' => 'CORPORACIÓN FINANCIERA DEMO, S.A.',
                    'pie_pagina_texto' => 'Información confidencial protegida.',
                    'firmante_1_nombre' => 'TITULAR DE LOS DATOS',
                    'firmante_1_titulo' => 'FIRMA DEL CLIENTE',
                    'firmante_2_nombre' => 'EJECUTIVO DE ATENCIÓN',
                    'firmante_2_titulo' => 'VERIFICADO POR',
                ],
                [
                    'tipo_documento' => 'comprobante_desembolso',
                    'nombre_documento' => 'Comprobante de Desembolso - Demo',
                    'vista_pdf' => 'pdf.comprobante-desembolso',
                    'plantilla_variante' => 'estandar',
                    'titulo_personalizado' => 'COMPROBANTE DE ENTREGA DE DESEMBOLSO',
                    'subtitulo_personalizado' => 'DIGIPRENDA DEMO',
                    'encabezado_texto' => 'CORPORACIÓN FINANCIERA DEMO, S.A.',
                    'pie_pagina_texto' => 'El cliente declara haber recibido a su entera satisfacción el monto indicado.',
                    'firmante_1_nombre' => 'RECIBÍ CONFORME (CLIENTE)',
                    'firmante_1_titulo' => 'FIRMA Y DPI',
                    'firmante_2_nombre' => 'ENTREGADO POR (CAJA)',
                    'firmante_2_titulo' => 'DIGIPRENDA DEMO',
                ],
                [
                    'tipo_documento' => 'partida_contable',
                    'nombre_documento' => 'Póliza de Diario Contable - Demo',
                    'vista_pdf' => 'reportes.contabilidad.partida-contable',
                    'plantilla_variante' => 'estandar',
                    'titulo_personalizado' => 'PÓLIZA DE REGISTRO CONTABLE',
                    'subtitulo_personalizado' => 'DIGIPRENDA DEMO',
                    'encabezado_texto' => 'DEPARTAMENTO DE CONTABILIDAD — DIGIPRENDA DEMO',
                    'pie_pagina_texto' => 'Registro contable de partida doble.',
                    'firmante_1_nombre' => 'ELABORADO POR',
                    'firmante_1_titulo' => 'CONTADOR GENERAL',
                    'firmante_2_nombre' => 'AUTORIZADO POR',
                    'firmante_2_titulo' => 'GERENCIA GENERAL',
                ],
                [
                    'tipo_documento' => 'cotizacion',
                    'nombre_documento' => 'Cotización de Venta - Demo',
                    'vista_pdf' => 'pdf.cotizacion',
                    'plantilla_variante' => 'estandar',
                    'titulo_personalizado' => 'COTIZACIÓN COMERCIAL DE ARTÍCULOS',
                    'subtitulo_personalizado' => 'DIGIPRENDA DEMO',
                    'encabezado_texto' => 'CORPORACIÓN FINANCIERA DEMO, S.A.',
                    'pie_pagina_texto' => 'Precios sujetos a disponibilidad.',
                    'firmante_1_nombre' => 'CLIENTE INTERESADO',
                    'firmante_1_titulo' => 'FIRMA',
                    'firmante_2_nombre' => 'ASESOR DE VENTAS',
                    'firmante_2_titulo' => 'DIGIPRENDA DEMO',
                ],
            ];

            foreach ($plantillasDemo as $tpl) {
                TbDoc::updateOrCreate(
                    [
                        'tipo_documento' => $tpl['tipo_documento'],
                        'organizacion_code' => $orgCode,
                        'sucursal_id' => null,
                    ],
                    array_merge($tpl, [
                        'organizacion_code' => $orgCode,
                        'sucursal_id' => null,
                        'formato_papel' => 'carta',
                        'orientacion' => 'portrait',
                        'mostrar_logo' => true,
                        'mostrar_firmas' => true,
                        'perito_contador_nombre' => 'PERITO CONTADOR DEMO',
                        'perito_contador_registro' => 'REG-00000',
                        'activo' => true,
                    ])
                );
            }
            $this->command?->info('  [✓] 8 plantillas de documentos (TbDoc) registradas para DEMO.');
        }

        // =========================================================================
        // 5. CATÁLOGOS BASE (CATEGORÍAS, CONTABILIDAD, BANCOS, PRODUCTOS)
        // =========================================================================
        $this->command?->info('5. Sembrando catálogos base del sistema...');

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

        $this->command?->info('========================================================');
        $this->command?->info('✅ CONFIGURACIÓN DEMO POR DEFECTO COMPLETADA CON ÉXITO');
        $this->command?->info('   - Empresa:    DIGIPRENDA DEMO');
        $this->command?->info('   - Sucursal:   Agencia Central Demo (DEMO-001)');
        $this->command?->info('   - Bóveda:     Bóveda Principal Demo (Q100,000.00)');
        $this->command?->info('   - SuperAdmin: andres@empenios.com / 2905Andres@');
        $this->command?->info('   - Usuarios:   admin@demo.com, contador@demo.com, cajero@demo.com, vendedor@demo.com (Clave: Demo2026@)');
        $this->command?->info('========================================================');
    }
}
