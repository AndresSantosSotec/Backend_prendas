<?php

namespace App\Documents;

use App\Documents\DTOs\DocumentResult;
use App\Models\ConfiguracionSistema;
use App\Models\TbDoc;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use InvalidArgumentException;

final class DocumentService
{
    public function __construct(
        private RendererRegistry $registry
    ) {}

    /**
     * Generar documento resolviendo automáticamente la configuración y renderizador correspondiente
     *
     * @param string $tipoDocumento Clave del documento (recibo_pago, contrato_credito, plan_pagos, etc.)
     * @param array $businessData Datos de la operación (crédito, recibo, cliente, etc.)
     * @param int|null $formatId ID específico de TbDoc si se solicita explícitamente
     * @param int|null $sucursalId Sucursal para resolución jerárquica
     * @param string|null $varianteOverride Forzar variante (ej: ticket_80mm)
     * @param string|null $orgCode Código de organización
     * @return DocumentResult
     */
    public function generate(
        string $tipoDocumento,
        array $businessData,
        ?int $formatId = null,
        ?int $sucursalId = null,
        ?string $varianteOverride = null,
        ?string $orgCode = null
    ): DocumentResult {
        $orgCode = $orgCode ?: ConfiguracionSistema::obtener('organizacion_code') ?: env('ORGANIZATION_CODE', '01');

        // 1. Resolver configuración de plantilla
        $config = $this->resolverConfiguracion($tipoDocumento, $formatId, $sucursalId, $orgCode);

        // 2. Preparar datos institucionales y de branding unificados
        $datosPreparados = $this->prepararDatosUnificados($config, $businessData);

        // 3. Determinar variante activa
        $variante = $varianteOverride ?: ($config->plantilla_variante ?: 'estandar');

        // 4. Obtener renderizador seguro de la lista blanca
        $renderer = $this->registry->getPorTipo($tipoDocumento, $variante);

        // 5. Renderizar y devolver resultado desacoplado
        return $renderer->render($datosPreparados, $config);
    }

    /**
     * Resolver la configuración en BD según jerarquía o ID explícito
     */
    public function resolverConfiguracion(
        string $tipoDocumento,
        ?int $formatId = null,
        ?int $sucursalId = null,
        ?string $orgCode = null
    ): TbDoc {
        if ($formatId !== null) {
            $format = TbDoc::query()
                ->where('tipo_documento', $tipoDocumento)
                ->where('activo', true)
                ->whereKey($formatId)
                ->first();

            if ($format === null) {
                throw (new ModelNotFoundException())->setModel(TbDoc::class, [(string) $formatId]);
            }

            return $format;
        }

        $orgCode = $orgCode ?: ConfiguracionSistema::obtener('organizacion_code') ?: env('ORGANIZATION_CODE', '01');
        return TbDoc::obtenerPlantilla($tipoDocumento, $sucursalId, $orgCode);
    }

    /**
     * Unificar datos de negocio con datos de la empresa, logo en Base64 y textos personalizados
     */
    public function prepararDatosUnificados(TbDoc $doc, array $businessData): array
    {
        $logoBase64 = $doc->obtenerLogoBase64();

        // PRIORIDAD ABSOLUTA: Lo que esté en la base de datos (configuraciones_sistema) manda sobre .env
        $empresaNombre = ConfiguracionSistema::obtener('empresa_nombre') ?: env('APP_NAME', 'PRENDAMÁS+');
        $razonSocial = ConfiguracionSistema::obtener('empresa_razon_social') ?: 'GRUPO JUMERC, SOCIEDAD ANÓNIMA';
        $direccion = ConfiguracionSistema::obtener('empresa_direccion') ?: '15 Avenida 4-60 zona 3 Quetzaltenango.';
        $telefono = ConfiguracionSistema::obtener('empresa_telefono') ?: '7934-0485';
        $whatsapp = ConfiguracionSistema::obtener('empresa_whatsapp') ?: '3996-6178';
        $nit = ConfiguracionSistema::obtener('empresa_nit') ?: 'C/F';
        $email = ConfiguracionSistema::obtener('empresa_email') ?: 'contacto@ejemplo.com';
        $repNombre = ConfiguracionSistema::obtener('representante_legal_nombre') ?: 'MANUEL FRANCISCO GARCÍA ROBLES';
        $repTitulo = ConfiguracionSistema::obtener('representante_legal_titulo') ?: 'ADMINISTRADOR ÚNICO Y REPRESENTANTE LEGAL';
        $repDpi = ConfiguracionSistema::obtener('representante_legal_dpi') ?: '2182 74416 0801';
        $horarioAtencion = ConfiguracionSistema::obtener('horario_atencion') ?: 'Lunes a viernes: 8:00 AM – 5:00 PM | SÁBADO: CERRADO | Domingo: 9:00 AM – 12:30 PM';

        $empresa = [
            'nombre' => $empresaNombre,
            'razon_social' => $razonSocial,
            'nit' => $nit,
            'direccion' => $direccion,
            'telefono' => $telefono,
            'whatsapp' => $whatsapp,
            'horario_atencion' => $horarioAtencion,
            'email' => $email,
            'representante_legal' => $repNombre,
            'representante_titulo' => $repTitulo,
            'representante_dpi' => $repDpi,
            'logo_base64' => $logoBase64,
        ];

        $institucionales = [
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
            'peritoContadorNombre' => $doc->perito_contador_nombre ?: (ConfiguracionSistema::obtener('perito_contador_nombre') ?: 'MANUEL FRANCISCO GARCÍA ROBLES'),
            'peritoContadorRegistro' => $doc->perito_contador_registro ?: (ConfiguracionSistema::obtener('perito_contador_registro') ?: ''),
            'firmante1Nombre' => $doc->firmante_1_nombre ?: 'CLIENTE',
            'firmante1Titulo' => $doc->firmante_1_titulo ?: 'FIRMA DE CONFORMIDAD',
            'firmante2Nombre' => $doc->firmante_2_nombre ?: $repNombre,
            'firmante2Titulo' => $doc->firmante_2_titulo ?: $repTitulo,
            'plantillaVariante' => $doc->plantilla_variante ?: 'estandar',
        ];

        return array_merge($institucionales, $businessData);
    }

    /**
     * Generar vista previa con datos ficticios/muestra para testing o previsualización en UI
     */
    public function generarPreview(int $documentoId, ?string $variante = null): DocumentResult
    {
        $doc = TbDoc::findOrFail($documentoId);
        $tipo = $doc->tipo_documento;

        $mockData = $this->obtenerDatosMuestra($tipo);
        $varianteActiva = $variante ?: ($doc->plantilla_variante ?: 'estandar');

        $datosPreparados = $this->prepararDatosUnificados($doc, $mockData);
        $renderer = $this->registry->getPorTipo($tipo, $varianteActiva);

        return $renderer->render($datosPreparados, $doc);
    }

    /**
     * Datos mock consistentes para generar previsualizaciones realistas en pantalla
     */
    private function obtenerDatosMuestra(string $tipoDocumento): array
    {
        $clienteMock = (object) [
            'id' => 1,
            'codigo_cliente' => 'CLI-001',
            'nombre' => 'JUAN PÉREZ LÓPEZ',
            'nombres' => 'JUAN',
            'apellidos' => 'PÉREZ LÓPEZ',
            'dpi' => '2541 89745 0101',
            'nit' => '456789-1',
            'telefono' => '5555-1234',
            'direccion' => 'Calle Real 2-15 Zona 1',
        ];

        $sucursalMock = (object) [
            'id' => 1,
            'nombre' => 'Sucursal Central',
            'codigo' => 'SUC-01',
            'direccion' => 'Avenida Principal 10-20, Zona 1',
            'telefono' => '2233-4455',
        ];

        $creditoMock = (object) [
            'id' => 1,
            'codigo_credito' => 'EMP-2026-0089',
            'numero_credito' => 'EMP-2026-0089',
            'monto_prestamo' => 1500.00,
            'tasa_interes' => 5.0,
            'plazo' => 3,
            'tipo_plazo' => 'meses',
            'fecha_desembolso' => date('Y-m-d'),
            'fecha_vencimiento' => date('Y-m-d', strtotime('+3 months')),
            'cliente' => $clienteMock,
            'sucursal' => $sucursalMock,
            'prendas' => collect([
                (object) [
                    'id' => 1,
                    'descripcion' => 'Anillo de oro 14K con piedra circonia',
                    'categoria' => (object) ['nombre' => 'Joyería Oro'],
                    'avaluo' => 2000.00,
                    'prestamo' => 1500.00,
                    'estado' => 'empeñado',
                ]
            ]),
            'planPagos' => collect([
                (object) ['numero_cuota' => 1, 'fecha_vencimiento' => date('d/m/Y', strtotime('+1 month')), 'monto_cuota' => 575.00, 'capital' => 500.00, 'interes' => 75.00, 'saldo_restante' => 1000.00],
                (object) ['numero_cuota' => 2, 'fecha_vencimiento' => date('d/m/Y', strtotime('+2 months')), 'monto_cuota' => 575.00, 'capital' => 500.00, 'interes' => 75.00, 'saldo_restante' => 500.00],
                (object) ['numero_cuota' => 3, 'fecha_vencimiento' => date('d/m/Y', strtotime('+3 months')), 'monto_cuota' => 575.00, 'capital' => 500.00, 'interes' => 75.00, 'saldo_restante' => 0.00],
            ]),
        ];

        $reciboMock = (object) [
            'id' => 1,
            'numero_recibo' => 'REC-2026-0154',
            'fecha_emision' => date('d/m/Y H:i:s'),
            'monto' => 575.00,
            'monto_letras' => 'QUINIENTOS SETENTA Y CINCO QUETZALES CON 00/100',
            'concepto' => 'Pago de Cuota #1 - Crédito EMP-2026-0089',
            'cliente' => $clienteMock,
            'sucursal' => $sucursalMock,
            'usuario' => (object) ['name' => 'Cajero de Turno'],
            'credito' => $creditoMock,
        ];

        return [
            'credito' => $creditoMock,
            'recibo' => $reciboMock,
            'cliente' => $clienteMock,
            'sucursal' => $sucursalMock,
            'prendas' => $creditoMock->prendas,
            'planPagos' => $creditoMock->planPagos,
            'fechaGeneracion' => date('d/m/Y H:i:s'),
            'fechaContrato' => date('d') . ' de ' . date('F') . ' de ' . date('Y'),
        ];
    }
}
