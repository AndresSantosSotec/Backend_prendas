<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ConfiguracionSistema;
use App\Models\TbDoc;
use App\Models\Sucursal;
use App\Models\Cliente;
use App\Models\CreditoPrendario;
use App\Models\CreditoPlanPago;
use App\Models\Prenda;
use App\Models\CategoriaProducto;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PrendamasOrganizacionSeeder extends Seeder
{
    /**
     * Run the database seeds para inicializar la empresa PRENDAMÁS+ (Grupo Jumerc, S.A.).
     * Lo que se configura aquí en BD manda sobre las variables del .env.
     */
    public function run(): void
    {
        $orgCode = '01'; // Organización principal

        // 1. CONFIGURACIÓN DEL SISTEMA (BD tiene prioridad sobre .env)
        $configuraciones = [
            'organizacion_code'                   => ['valor' => $orgCode, 'tipo' => 'string', 'desc' => 'Código de la organización activa'],
            'empresa_nombre'                      => ['valor' => 'PRENDAMÁS+', 'tipo' => 'string', 'desc' => 'Nombre comercial de la empresa'],
            'empresa_razon_social'                => ['valor' => 'GRUPO JUMERC, SOCIEDAD ANÓNIMA', 'tipo' => 'string', 'desc' => 'Razón social legal'],
            'empresa_nit'                         => ['valor' => '858769-1', 'tipo' => 'string', 'desc' => 'NIT de la empresa'],
            'empresa_direccion'                   => ['valor' => '15 Avenida 4-60 zona 3 Quetzaltenango.', 'tipo' => 'string', 'desc' => 'Dirección de oficinas centrales'],
            'empresa_telefono'                    => ['valor' => '7934-0485', 'tipo' => 'string', 'desc' => 'Teléfono fijo de atención'],
            'empresa_whatsapp'                    => ['valor' => '3996-6178', 'tipo' => 'string', 'desc' => 'Número de WhatsApp de atención'],
            'empresa_email'                       => ['valor' => 'contacto@prendamas.gt', 'tipo' => 'string', 'desc' => 'Correo de contacto'],
            'horario_atencion'                    => ['valor' => 'Lunes a viernes: 8:00 AM – 5:00 PM | SÁBADO: CERRADO | Domingo: 9:00 AM – 12:30 PM', 'tipo' => 'string', 'desc' => 'Horario de atención'],
            'municipio_contrato'                  => ['valor' => 'Quetzaltenango', 'tipo' => 'string', 'desc' => 'Municipio legal para contratos'],
            'departamento_contrato'               => ['valor' => 'Quetzaltenango', 'tipo' => 'string', 'desc' => 'Departamento legal para contratos'],
            'logo_activo'                         => ['valor' => 'logos/prendamas_logo.png', 'tipo' => 'string', 'desc' => 'Logo activo de la empresa'],
            'representante_legal_nombre'          => ['valor' => 'MANUEL FRANCISCO GARCÍA ROBLES', 'tipo' => 'string', 'desc' => 'Nombre del Administrador Único'],
            'representante_legal_titulo'          => ['valor' => 'ADMINISTRADOR ÚNICO Y REPRESENTANTE LEGAL', 'tipo' => 'string', 'desc' => 'Cargo legal'],
            'representante_legal_dpi'             => ['valor' => '2182 74416 0801', 'tipo' => 'string', 'desc' => 'DPI del Representante'],
            'representante_legal_dpi_letras'      => ['valor' => 'Dos mil ciento ochenta y dos, setenta y cuatro mil cuatrocientos dieciséis, cero ochocientos uno (2182 74416 0801)', 'tipo' => 'string', 'desc' => 'DPI en letras'],
            'representante_legal_edad'            => ['valor' => 'treinta y seis años', 'tipo' => 'string', 'desc' => 'Edad del representante'],
            'representante_legal_estado_civil'    => ['valor' => 'casado', 'tipo' => 'string', 'desc' => 'Estado civil representante'],
            'representante_legal_nacionalidad'    => ['valor' => 'guatemalteco', 'tipo' => 'string', 'desc' => 'Nacionalidad representante'],
            'representante_legal_profesion'       => ['valor' => 'Perito Contador', 'tipo' => 'string', 'desc' => 'Profesión representante'],
            'representante_legal_domicilio'       => ['valor' => 'departamento Totonicapán', 'tipo' => 'string', 'desc' => 'Domicilio representante'],
            'representante_legal_acta_notario'    => ['valor' => 'Herson José Argueta Ola', 'tipo' => 'string', 'desc' => 'Notario que autorizó acta'],
            'representante_legal_acta_fecha'      => ['valor' => 'treinta y uno de agosto del año dos mil veintiséis', 'tipo' => 'string', 'desc' => 'Fecha del acta'],
            'representante_legal_reg_mercantil'   => ['valor' => '858,769', 'tipo' => 'string', 'desc' => 'Número registro mercantil'],
            'representante_legal_folio'           => ['valor' => '798', 'tipo' => 'string', 'desc' => 'Folio registro mercantil'],
            'representante_legal_libro'           => ['valor' => '866', 'tipo' => 'string', 'desc' => 'Libro auxiliares de comercio'],
            'perito_contador_nombre'              => ['valor' => 'MANUEL FRANCISCO GARCÍA ROBLES', 'tipo' => 'string', 'desc' => 'Perito contador de la empresa'],
        ];

        foreach ($configuraciones as $clave => $item) {
            ConfiguracionSistema::updateOrCreate(
                ['clave' => $clave],
                [
                    'valor' => $item['valor'],
                    'tipo' => $item['tipo'],
                    'grupo' => 'organizacion',
                    'descripcion' => $item['desc'],
                    'editable_por_usuario' => true,
                ]
            );
        }

        // 2. SUCURSAL CENTRAL (Quetzaltenango)
        $sucursal = Sucursal::first();
        if ($sucursal) {
            $sucursal->update([
                'nombre' => 'Agencia Central Quetzaltenango',
                'direccion' => '15 Avenida 4-60 zona 3 Quetzaltenango.',
                'telefono' => '7934-0485',
                'municipio' => 'Quetzaltenango',
                'departamento' => 'Quetzaltenango',
            ]);
        } else {
            $sucursal = Sucursal::create([
                'nombre' => 'Agencia Central Quetzaltenango',
                'codigo' => 'AG-01',
                'direccion' => '15 Avenida 4-60 zona 3 Quetzaltenango.',
                'telefono' => '7934-0485',
                'municipio' => 'Quetzaltenango',
                'departamento' => 'Quetzaltenango',
                'activa' => true,
            ]);
        }

        // 3. PLANTILLAS DE DOCUMENTOS PRENDAMÁS EN TB_DOCS
        // a) Contrato notarial
        TbDoc::updateOrCreate(
            [
                'organizacion_code' => $orgCode,
                'tipo_documento'    => 'contrato_credito',
                'plantilla_variante'=> 'prendamas',
            ],
            [
                'nombre_documento'        => 'Contrato Notarial Prendamas (Grupo Jumerc)',
                'vista_pdf'               => 'pdf.custom.prendamas.contrato',
                'titulo_personalizado'    => 'CONTRATO DE MUTUO CON GARANTÍA PRENDARIA',
                'subtitulo_personalizado' => 'PRENDAMÁS+ - GRUPO JUMERC, S.A.',
                'encabezado_texto'        => 'Contrato notarial con legalización de firmas y prenda con desplazamiento.',
                'pie_pagina_texto'        => 'Prendamas+ - Tu patrimonio en buenas manos',
                'firmante_1_nombre'       => 'DEUDORA (CLIENTE)',
                'firmante_1_titulo'       => 'FIRMA DE LA CLIENTA',
                'firmante_2_nombre'       => 'MANUEL FRANCISCO GARCÍA ROBLES',
                'firmante_2_titulo'       => 'ADMINISTRADOR ÚNICO Y REPRESENTANTE LEGAL',
                'mostrar_logo'            => true,
                'mostrar_firmas'          => true,
                'activo'                  => true,
            ]
        );

        // b) Estado de cuenta y Plan de pagos
        TbDoc::updateOrCreate(
            [
                'organizacion_code' => $orgCode,
                'tipo_documento'    => 'plan_pagos',
                'plantilla_variante'=> 'prendamas',
            ],
            [
                'nombre_documento'        => 'Estado de Cuenta y Plan de Pagos Prendamas',
                'vista_pdf'               => 'pdf.custom.prendamas.plan-pagos',
                'titulo_personalizado'    => 'ESTADO DE CUENTA',
                'subtitulo_personalizado' => 'RESUMEN DEL DIA',
                'encabezado_texto'        => 'Estado de cuenta prendario con detalle de avalúo, prenda y calendario de cuotas.',
                'pie_pagina_texto'        => 'Prendamas+ - Tu patrimonio en buenas manos',
                'firmante_1_nombre'       => 'CLIENTE',
                'firmante_1_titulo'       => 'RECIBO MI PRENDA CONFORME',
                'mostrar_logo'            => true,
                'mostrar_firmas'          => true,
                'activo'                  => true,
            ]
        );

        // c) Recibo de pago
        TbDoc::updateOrCreate(
            [
                'organizacion_code' => $orgCode,
                'tipo_documento'    => 'recibo_pago',
                'plantilla_variante'=> 'estandar',
            ],
            [
                'nombre_documento'        => 'Recibo de Pago de Crédito',
                'vista_pdf'               => 'creditos.recibo',
                'titulo_personalizado'    => 'COMPROBANTE DE PAGO',
                'subtitulo_personalizado' => 'PRENDAMÁS+',
                'encabezado_texto'        => 'Comprobante oficial de abono / cancelación de crédito prendario.',
                'pie_pagina_texto'        => 'Gracias por su pago. Conserve este comprobante.',
                'mostrar_logo'            => true,
                'mostrar_firmas'          => true,
                'activo'                  => true,
            ]
        );

        // 4. CLIENTES DE DEMOSTRACIÓN PRENDAMÁS
        $cliente1 = Cliente::updateOrCreate(
            ['dpi' => '2601 35151 0801'],
            [
                'codigo_cliente'   => 'CLI-PREN-001',
                'nombres'          => 'FELISA JOSEFINA',
                'apellidos'        => 'GUTIERREZ CHUC DE PONCIO',
                'fecha_nacimiento' => '1962-09-23',
                'genero'           => 'femenino',
                'estado_civil'     => 'casada',
                'profesion'        => 'comerciante',
                'telefono'         => '5544-3322',
                'direccion'        => 'Cantón Cojxac, Paraje Paracansigua',
                'municipio'        => 'Totonicapán',
                'tipo_cliente'     => 'regular',
                'estado'           => 'activo',
            ]
        );

        $cliente2 = Cliente::updateOrCreate(
            ['dpi' => '2182 74416 0801'],
            [
                'codigo_cliente'   => 'CLI-PREN-002',
                'nombres'          => 'MANUEL FRANCISCO',
                'apellidos'        => 'GARCÍA ROBLES',
                'fecha_nacimiento' => '1990-05-15',
                'genero'           => 'masculino',
                'estado_civil'     => 'casado',
                'profesion'        => 'Perito Contador',
                'telefono'         => '3996-6178',
                'direccion'        => '15 Avenida 4-60 zona 3',
                'municipio'        => 'Quetzaltenango',
                'tipo_cliente'     => 'regular',
                'estado'           => 'activo',
            ]
        );

        // 5. CATEGORÍAS DE PRENDAS
        $catJoyeria = CategoriaProducto::where('nombre', 'like', '%Joyer%')->first() 
            ?? CategoriaProducto::first() 
            ?? CategoriaProducto::create(['codigo' => 'JOY', 'nombre' => 'Joyería', 'activa' => true]);

        $catRelojes = CategoriaProducto::where('nombre', 'like', '%Reloj%')->orWhere('nombre', 'like', '%Electr%')->first() 
            ?? $catJoyeria;

        $usuarioAdmin = User::first();
        $adminId = $usuarioAdmin?->id ?? 1;

        // 6. CRÉDITO 0001 (Estado de Cuenta - Reloj Omega)
        $credito1 = CreditoPrendario::updateOrCreate(
            ['numero_credito' => '0001'],
            [
                'cliente_id'        => $cliente2->id,
                'sucursal_id'       => $sucursal->id,
                'analista_id'       => $adminId,
                'cajero_id'         => $adminId,
                'tasador_id'        => $adminId,
                'estado'            => 'vigente',
                'monto_solicitado'  => 1750.00,
                'monto_aprobado'    => 1750.00,
                'monto_desembolsado'=> 1750.00,
                'capital_pendiente' => 1750.00,
                'valor_tasacion'    => 3500.00,
                'tasa_interes'      => 15.14,
                'tipo_interes'      => 'mensual',
                'plazo_dias'        => 30,
                'dias_gracia'       => 4,
                'numero_cuotas'     => 1,
                'fecha_solicitud'   => '2026-10-01',
                'fecha_aprobacion'  => '2026-10-01',
                'fecha_desembolso'  => '2026-10-01',
                'fecha_primer_pago' => '2026-11-01',
                'fecha_vencimiento' => '2026-11-01',
                'observaciones'     => 'Crédito prendario Prendamás - Reloj Omega Sea Master',
            ]
        );

        Prenda::updateOrCreate(
            [
                'credito_prendario_id' => $credito1->id,
                'codigo_prenda'        => 'PRN-0001-01',
            ],
            [
                'categoria_producto_id' => $catRelojes->id,
                'tasador_id'            => $adminId,
                'sucursal_id'           => $sucursal->id,
                'descripcion'           => 'Reloj de pulsera para hombre',
                'marca'                 => 'Omega',
                'modelo'                => 'Sea master',
                'serie'                 => '76543210',
                'condicion'             => 'Excelente',
                'observaciones'         => 'Material: Acero inoxidable• Accesorios: Incluye estuche y certificado de autenticidad',
                'valor_tasacion'        => 3500.00,
                'valor_prestamo'        => 1750.00,
                'estado'                => 'en_custodia',
                'fecha_ingreso'         => '2026-10-01',
            ]
        );

        // Cuota plan de pagos Crédito 1
        CreditoPlanPago::updateOrCreate(
            [
                'credito_prendario_id' => $credito1->id,
                'numero_cuota'         => 1,
            ],
            [
                'fecha_vencimiento'      => '2026-11-01',
                'estado'                 => 'pendiente',
                'capital_proyectado'     => 1750.00,
                'interes_proyectado'     => 265.00,
                'mora_proyectada'        => 0.00,
                'otros_cargos_proyectados'=> 0.00,
                'monto_cuota_proyectado' => 2015.00,
                'capital_pendiente'      => 1750.00,
                'interes_pendiente'      => 265.00,
                'monto_pendiente'        => 2015.00,
                'saldo_capital_credito'  => 0.00,
            ]
        );

        // 7. CRÉDITO 0002 (Contrato Notarial - Aretes Flor de Oro)
        $credito2 = CreditoPrendario::updateOrCreate(
            ['numero_credito' => '0002'],
            [
                'cliente_id'        => $cliente1->id,
                'sucursal_id'       => $sucursal->id,
                'analista_id'       => $adminId,
                'cajero_id'         => $adminId,
                'tasador_id'        => $adminId,
                'estado'            => 'vigente',
                'monto_solicitado'  => 700.00,
                'monto_aprobado'    => 700.00,
                'monto_desembolsado'=> 700.00,
                'capital_pendiente' => 700.00,
                'valor_tasacion'    => 1400.00,
                'tasa_interes'      => 13.44,
                'tipo_interes'      => 'mensual',
                'plazo_dias'        => 30,
                'dias_gracia'       => 4,
                'numero_cuotas'     => 1,
                'fecha_solicitud'   => '2026-09-23',
                'fecha_aprobacion'  => '2026-09-23',
                'fecha_desembolso'  => '2026-09-23',
                'fecha_primer_pago' => '2026-10-23',
                'fecha_vencimiento' => '2026-10-23',
                'observaciones'     => 'Contrato Notarial de Mutuo - Aretes con piedras rojas',
            ]
        );

        Prenda::updateOrCreate(
            [
                'credito_prendario_id' => $credito2->id,
                'codigo_prenda'        => 'PRN-0002-01',
            ],
            [
                'categoria_producto_id' => $catJoyeria->id,
                'tasador_id'            => $adminId,
                'sucursal_id'           => $sucursal->id,
                'descripcion'           => 'Par de Aretes con forma de Flor con piedras rojas',
                'marca'                 => 'Artesanal',
                'color'                 => 'Dorado',
                'condicion'             => 'Excelente',
                'observaciones'         => 'CON PESO DE tres punto nueve (3.9) gramos de diez kilates de oro cada uno y peso de la piedra: Cero punto diez gramos (0.10 Grms)',
                'valor_tasacion'        => 1400.00,
                'valor_prestamo'        => 700.00,
                'estado'                => 'en_custodia',
                'fecha_ingreso'         => '2026-09-23',
            ]
        );

        CreditoPlanPago::updateOrCreate(
            [
                'credito_prendario_id' => $credito2->id,
                'numero_cuota'         => 1,
            ],
            [
                'fecha_vencimiento'      => '2026-10-23',
                'estado'                 => 'pendiente',
                'capital_proyectado'     => 700.00,
                'interes_proyectado'     => 94.08,
                'mora_proyectada'        => 0.00,
                'otros_cargos_proyectados'=> 0.00,
                'monto_cuota_proyectado' => 794.08,
                'capital_pendiente'      => 700.00,
                'interes_pendiente'      => 94.08,
                'monto_pendiente'        => 794.08,
                'saldo_capital_credito'  => 0.00,
            ]
        );

        // 6. PRODUCTOS DE CRÉDITO Y CATEGORÍAS PRENDAMÁS+
        $this->call(PrendamasProductosCreditoSeeder::class);

        $this->command->info('✅ Seeder de PRENDAMÁS+ ejecutado correctamente con datos empresariales, plantillas, productos de crédito y créditos de muestra.');
    }
}
