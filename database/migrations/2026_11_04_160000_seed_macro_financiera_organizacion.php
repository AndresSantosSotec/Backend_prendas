<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Artisan;
use App\Models\ConfiguracionSistema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // 1. Siempre registrar las plantillas TbDoc de Macro Financiera (no afecta a otras organizaciones)
        if (class_exists(\Database\Seeders\MacroFinancieraOrganizacionSeeder::class)) {
            $appUrl = env('APP_URL', '');
            $orgEnv = env('ORGANIZATION_CODE', '');

            $esEntornoMacro = str_contains($appUrl, 'macrofinanciera') 
                || strtoupper(trim($orgEnv)) === 'MACROFINANCIERA';

            // Si es el backend de Macro Financiera, ejecutar el seeder completo
            if ($esEntornoMacro) {
                Artisan::call('db:seed', [
                    '--class' => 'Database\\Seeders\\MacroFinancieraOrganizacionSeeder',
                    '--force' => true,
                ]);
            } else {
                // En otros entornos, registrar únicamente las plantillas TbDoc con código 'MACROFINANCIERA'
                // para tenerlas disponibles sin sobreescribir la configuración activa del cliente actual.
                $this->registrarPlantillasTbDocAisladas();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (\Illuminate\Support\Facades\Schema::hasTable('tb_docs')) {
            \App\Models\TbDoc::where('organizacion_code', 'MACROFINANCIERA')->delete();
        }
    }

    private function registrarPlantillasTbDocAisladas(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('tb_docs')) {
            return;
        }

        $piePagina = 'Macro Financiera S.A. - El lugar donde dimensionamos tus sueños';
        $logoRuta = 'logos/macro_financiera_logo.png';
        $orgCode = 'MACROFINANCIERA';

        $documentos = [
            [
                'tipo_documento' => 'contrato_credito',
                'nombre_documento' => 'Contrato de Préstamo Prendario Macro Financiera',
                'plantilla_variante' => 'estandar',
                'vista_pdf' => 'creditos.contrato',
                'titulo_personalizado' => 'CONTRATO DE MUTUO CON GARANTÍA PRENDARIA',
                'subtitulo_personalizado' => 'MACRO FINANCIERA S.A.',
                'encabezado_texto' => 'Contrato formal de mutuo con garantía prendaria.',
                'pie_pagina_texto' => $piePagina,
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
                'pie_pagina_texto' => $piePagina,
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
        ];

        foreach ($documentos as $doc) {
            \App\Models\TbDoc::updateOrCreate(
                [
                    'organizacion_code' => $orgCode,
                    'tipo_documento' => $doc['tipo_documento'],
                ],
                $doc
            );
        }
    }
};

