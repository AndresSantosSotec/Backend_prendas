<?php

namespace App\Services;

use App\Models\TbDoc;
use App\Models\ConfiguracionSistema;

class PdfDocumentoService
{
    /**
     * Preparar array unificado de datos institucionales y plantilla para cualquier reporte PDF
     */
    public static function obtenerDatosPlantilla(string $tipoDocumento, ?int $sucursalId = null, array $datosAdicionales = [], ?string $orgCode = null): array
    {
        $doc = TbDoc::obtenerPlantilla($tipoDocumento, $sucursalId, $orgCode);
        
        $logoBase64 = $doc->obtenerLogoBase64();

        $empresa = [
            'nombre' => ConfiguracionSistema::obtener('empresa_nombre', env('APP_NAME', 'Microsystem Plus')),
            'nit' => ConfiguracionSistema::obtener('empresa_nit', 'C/F'),
            'direccion' => ConfiguracionSistema::obtener('empresa_direccion', 'Guatemala'),
            'telefono' => ConfiguracionSistema::obtener('empresa_telefono', 'PBX: 2200-0000'),
            'email' => ConfiguracionSistema::obtener('empresa_email', 'contacto@ejemplo.com'),
            'logo_base64' => $logoBase64,
        ];

        return array_merge([
            'docConfig' => $doc,
            'empresa' => $empresa,
            'logoBase64' => $logoBase64,
            'vistaPdf' => $doc->vista_pdf,
            'tituloDocumento' => $doc->titulo_personalizado ?: $doc->nombre_documento,
            'subtituloDocumento' => $doc->subtitulo_personalizado,
            'encabezadoTexto' => $doc->encabezado_texto,
            'piePaginaTexto' => $doc->pie_pagina_texto,
            'mostrarLogo' => $doc->mostrar_logo,
            'mostrarFirmas' => $doc->mostrar_firmas,
            'peritoContadorNombre' => $doc->perito_contador_nombre ?: 'ROSAURA MARISOL MENDOZA YOJCOM',
            'peritoContadorRegistro' => $doc->perito_contador_registro ?: '115855092',
            'firmante1Nombre' => $doc->firmante_1_nombre ?: ($doc->perito_contador_nombre ?: 'PERITO CONTADOR'),
            'firmante1Titulo' => $doc->firmante_1_titulo ?: 'CONTADOR AUTORIZADO',
            'firmante2Nombre' => $doc->firmante_2_nombre ?: 'REPRESENTANTE LEGAL',
            'firmante2Titulo' => $doc->firmante_2_titulo ?: 'REPRESENTANTE LEGAL',
            'plantillaVariante' => $doc->plantilla_variante ?: 'estandar',
        ], $datosAdicionales);
    }

    /**
     * Resolver la vista blade dinámica si existe una personalizada o usar la por defecto
     */
    public static function resolverVista(string $tipoDocumento, string $vistaPorDefecto, ?int $sucursalId = null, ?string $orgCode = null): string
    {
        $doc = TbDoc::obtenerPlantilla($tipoDocumento, $sucursalId, $orgCode);
        if (!empty($doc->vista_pdf) && view()->exists($doc->vista_pdf)) {
            return $doc->vista_pdf;
        }
        return $vistaPorDefecto;
    }
}
