<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TbDoc;

class TbDocSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $documentosBase = [
            [
                'organizacion_code' => '01',
                'sucursal_id' => null,
                'tipo_documento' => 'recibo_pago',
                'nombre_documento' => 'Recibo de Pago de Crédito',
                'plantilla_variante' => 'estandar',
                'vista_pdf' => 'pdf.recibo',
                'titulo_personalizado' => 'COMPROBANTE DE PAGO',
                'subtitulo_personalizado' => 'Sistema de Gestión de Empeños',
                'encabezado_texto' => 'Comprobante oficial de abono / cancelacion de credito prendario.',
                'pie_pagina_texto' => 'Gracias por su pago. Conserve este comprobante para cualquier reclamo.',
                'mostrar_logo' => true,
                'mostrar_firmas' => true,
                'activo' => true,
            ],
            [
                'organizacion_code' => '01',
                'sucursal_id' => null,
                'tipo_documento' => 'contrato_credito',
                'nombre_documento' => 'Contrato de Crédito Prendario',
                'plantilla_variante' => 'estandar',
                'vista_pdf' => 'pdf.contrato',
                'titulo_personalizado' => 'CONTRATO DE PRÉSTAMO PRENDARIO CON GARANTÍA',
                'subtitulo_personalizado' => 'Condiciones Generales del Crédito',
                'encabezado_texto' => 'Documento de valor legal y compromiso de empeño.',
                'pie_pagina_texto' => 'El deudor acepta todos los términos y condiciones estipulados en el presente contrato.',
                'mostrar_logo' => true,
                'mostrar_firmas' => true,
                'activo' => true,
            ],
            [
                'organizacion_code' => '01',
                'sucursal_id' => null,
                'tipo_documento' => 'plan_pagos',
                'nombre_documento' => 'Tabla / Plan de Pagos',
                'plantilla_variante' => 'estandar',
                'vista_pdf' => 'pdf.plan-pagos',
                'titulo_personalizado' => 'PLAN DE PAGOS DE CRÉDITO',
                'subtitulo_personalizado' => 'Calendario Proyectado de Cuotas',
                'encabezado_texto' => 'Detalle de cuotas, capital, intereses y fechas de vencimiento.',
                'pie_pagina_texto' => 'Las fechas de pago son estrictas para evitar recargos por mora.',
                'mostrar_logo' => true,
                'mostrar_firmas' => true,
                'activo' => true,
            ],
            [
                'organizacion_code' => '01',
                'sucursal_id' => null,
                'tipo_documento' => 'recibo_compra',
                'nombre_documento' => 'Recibo de Compra Directa',
                'plantilla_variante' => 'estandar',
                'vista_pdf' => 'pdf.recibo-compra',
                'titulo_personalizado' => 'COMPROBANTE DE COMPRA DIRECTA',
                'subtitulo_personalizado' => 'Adquisición de Artículo / Prenda',
                'encabezado_texto' => 'Comprobante de compra directa a cliente.',
                'pie_pagina_texto' => 'El vendedor declara que el artículo es de su legítima propiedad.',
                'mostrar_logo' => true,
                'mostrar_firmas' => true,
                'activo' => true,
            ],
            [
                'organizacion_code' => '01',
                'sucursal_id' => null,
                'tipo_documento' => 'recibo_venta',
                'nombre_documento' => 'Comprobante de Venta',
                'plantilla_variante' => 'estandar',
                'vista_pdf' => 'pdf.recibo-venta',
                'titulo_personalizado' => 'COMPROBANTE DE VENTA DE PRENDA',
                'subtitulo_personalizado' => 'Venta al Contado / Financiada',
                'encabezado_texto' => 'Comprobante de venta de artículos en liquidación/venta.',
                'pie_pagina_texto' => 'Gracias por su compra.',
                'mostrar_logo' => true,
                'mostrar_firmas' => true,
                'activo' => true,
            ],
            [
                'organizacion_code' => '01',
                'sucursal_id' => null,
                'tipo_documento' => 'balance_general',
                'nombre_documento' => 'Estado de Situación Financiera (Balance General)',
                'plantilla_variante' => 'estandar', // o cemadec
                'vista_pdf' => 'reportes.contabilidad.balance-general',
                'titulo_personalizado' => 'ESTADO DE SITUACIÓN FINANCIERA (BALANCE GENERAL)',
                'subtitulo_personalizado' => 'Cifras Expresadas en Quetzales',
                'firmante_1_nombre' => 'JUAN EDUARDO VASQUEZ CHOLOTIO',
                'firmante_1_titulo' => 'PERITO CONTADOR',
                'firmante_2_nombre' => 'FIDEL ANTONIO QUIACAIN COTUC',
                'firmante_2_titulo' => 'REPRESENTANTE LEGAL',
                'perito_contador_nombre' => 'JUAN EDUARDO VASQUEZ CHOLOTIO',
                'perito_contador_registro' => '28400631',
                'mostrar_logo' => true,
                'mostrar_firmas' => true,
                'activo' => true,
            ],
            [
                'organizacion_code' => '01',
                'sucursal_id' => null,
                'tipo_documento' => 'estado_resultados',
                'nombre_documento' => 'Estado de Resultados (Estado de Pérdidas y Ganancias)',
                'plantilla_variante' => 'estandar', // o cemadec
                'vista_pdf' => 'reportes.contabilidad.estado-resultados',
                'titulo_personalizado' => 'ESTADO DE RESULTADOS INTEGRAL',
                'subtitulo_personalizado' => 'Cifras Expresadas en Quetzales',
                'firmante_1_nombre' => 'JUAN EDUARDO VASQUEZ CHOLOTIO',
                'firmante_1_titulo' => 'PERITO CONTADOR',
                'firmante_2_nombre' => 'FIDEL ANTONIO QUIACAIN COTUC',
                'firmante_2_titulo' => 'REPRESENTANTE LEGAL',
                'perito_contador_nombre' => 'JUAN EDUARDO VASQUEZ CHOLOTIO',
                'perito_contador_registro' => '28400631',
                'mostrar_logo' => true,
                'mostrar_firmas' => true,
                'activo' => true,
            ],
            [
                'organizacion_code' => '01',
                'sucursal_id' => null,
                'tipo_documento' => 'partida_contable',
                'nombre_documento' => 'Póliza / Partida Contable',
                'plantilla_variante' => 'estandar',
                'vista_pdf' => 'reportes.contabilidad.partida-contable',
                'titulo_personalizado' => 'PÓLIZA DE DIARIO / COMPROBANTE DE PARTIDA',
                'subtitulo_personalizado' => 'Comprobante de Registro en Partida Doble',
                'encabezado_texto' => 'Registro oficial de operaciones contables del sistema.',
                'pie_pagina_texto' => 'Documento contable interno con firma y valor probatorio institucional.',
                'firmante_1_nombre' => 'JUAN EDUARDO VASQUEZ CHOLOTIO',
                'firmante_1_titulo' => 'CONTADOR GENERAL / ELABORÓ',
                'firmante_2_nombre' => 'FIDEL ANTONIO QUIACAIN COTUC',
                'firmante_2_titulo' => 'REPRESENTANTE LEGAL / AUTORIZÓ',
                'perito_contador_nombre' => 'JUAN EDUARDO VASQUEZ CHOLOTIO',
                'perito_contador_registro' => '28400631',
                'mostrar_logo' => true,
                'mostrar_firmas' => true,
                'activo' => true,
            ],
        ];

        foreach ($documentosBase as $doc) {
            TbDoc::updateOrCreate(
                [
                    'organizacion_code' => $doc['organizacion_code'],
                    'tipo_documento' => $doc['tipo_documento'],
                    'sucursal_id' => $doc['sucursal_id'],
                ],
                $doc
            );
        }
    }
}
