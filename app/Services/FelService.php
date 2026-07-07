<?php

namespace App\Services;

use App\Models\Venta;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;

class FelService
{
    /**
     * Certificar una venta (FACT o FCAM) ante TEKRA/SAT
     *
     * @param Venta $venta
     * @return array
     * @throws \Exception
     */
    public function certificarFactura(Venta $venta): array
    {
        if (!config('fel.enabled')) {
            Log::info("Certificación FEL desactivada. Simulando para venta ID {$venta->id}.");
            return [
                'success' => true,
                'uuid' => strtoupper(bin2hex(random_bytes(16))),
                'serie' => 'TSIM',
                'numero' => (string)rand(100000, 999999),
                'fecha_certificacion' => now(),
                'pdf_path' => null
            ];
        }

        // 1. Generar XML del DTE según el tipo de venta
        $dteXml = $this->generarDteXml($venta);

        // 2. Construir sobre SOAP
        $soapBody = '<?xml version="1.0" encoding="utf-8"?>
<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/">
  <Body>
    <CertificacionDocumento xmlns="http://apicertificacion.desa.tekra.com.gt:8080/certificacion/wsdl/">
      <Autenticacion>
        <pn_usuario>' . htmlspecialchars(config('fel.user')) . '</pn_usuario>
        <pn_clave>' . htmlspecialchars(config('fel.password')) . '</pn_clave>
        <pn_cliente>' . htmlspecialchars(config('fel.client')) . '</pn_cliente>
        <pn_contrato>' . htmlspecialchars(config('fel.contract')) . '</pn_contrato>
        <pn_id_origen>Sistema Facturacion</pn_id_origen>
        <pn_ip_origen>' . htmlspecialchars(request()->ip() ?: '127.0.0.1') . '</pn_ip_origen>
        <pn_firmar_emisor>SI</pn_firmar_emisor>
        <pn_validar_identificador>SI</pn_validar_identificador>
      </Autenticacion>
      <Documento><![CDATA[' . $dteXml . ']]></Documento>
    </CertificacionDocumento>
  </Body>
</Envelope>';

        // 3. Enviar petición SOAP
        try {
            $response = Http::withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => '""',
            ])->send('POST', config('fel.soap_url'), [
                'body' => $soapBody
            ]);

            if (!$response->successful()) {
                throw new \Exception("Error de comunicación con el certificador (HTTP status " . $response->status() . ")");
            }

            // 4. Procesar respuesta SOAP
            $responseBody = $response->body();
            
            // Limpiar namespaces para parsear fácilmente
            $cleanXml = preg_replace('/(<\/?[a-zA-Z0-9\-]+):/', '<', $responseBody);
            $cleanXml = preg_replace('/ xmlns:[a-zA-Z0-9\-]+="[^"]+"/', '', $cleanXml);
            
            $xmlObj = simplexml_load_string($cleanXml);
            if (!$xmlObj) {
                throw new \Exception("No se pudo parsear el XML de respuesta del certificador.");
            }

            // Buscar nodo CertificacionDocumentoResponse
            $responseNode = null;
            if (isset($xmlObj->Body->CertificacionDocumentoResponse)) {
                $responseNode = $xmlObj->Body->CertificacionDocumentoResponse;
            } else {
                // Intento alternativo buscando en la estructura completa
                $json = json_encode($xmlObj);
                $array = json_decode($json, true);
                $responseNode = $this->buscarNodoRecursivo($array, 'CertificacionDocumentoResponse');
                if ($responseNode) {
                    $responseNode = json_decode(json_encode($responseNode));
                }
            }

            if (!$responseNode) {
                throw new \Exception("Respuesta inesperada del certificador: " . substr($responseBody, 0, 500));
            }

            // Parsear ResultadoCertificacion
            $resultadoJson = (string)$responseNode->ResultadoCertificacion;
            $resultado = json_decode($resultadoJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new \Exception("Resultado de certificación inválido: " . $resultadoJson);
            }

            if (isset($resultado['error']) && $resultado['error'] !== 0) {
                $msg = $resultado['mensaje'] ?? 'Error desconocido';
                if (!empty($resultado['errores_xsd'])) {
                    $msg .= ' (XSD: ' . json_encode($resultado['errores_xsd']) . ')';
                }
                throw new \Exception("Error en certificación SAT: " . $msg);
            }

            // Extraer datos certificados
            $uuid = (string)($responseNode->NumeroAutorizacion ?? '');
            $serie = (string)($responseNode->SerieDocumento ?? '');
            $numero = (string)($responseNode->NumeroDocumento ?? '');
            $fechaCert = (string)($responseNode->FechaHoraCertificacion ?? '');
            $pdfBase64 = (string)($responseNode->RepresentacionGrafica ?? '');

            // Fallback si no vinieron en elementos directos (leer de los atributos del NumeroAutorizacion)
            if (empty($uuid) && isset($responseNode->NumeroAutorizacion)) {
                $uuid = (string)$responseNode->NumeroAutorizacion;
            }
            if (empty($serie) && isset($responseNode->NumeroAutorizacion)) {
                $attrs = json_decode(json_encode($responseNode->NumeroAutorizacion), true);
                $serie = $attrs['@attributes']['Serie'] ?? '';
                $numero = $attrs['@attributes']['Numero'] ?? '';
            }

            if (empty($uuid)) {
                throw new \Exception("La certificación fue exitosa pero no se retornó un UUID válido.");
            }

            // Guardar representación gráfica (PDF)
            $pdfPath = null;
            if (!empty($pdfBase64)) {
                try {
                    $pdfData = base64_decode($pdfBase64);
                    $pdfName = 'fel_' . $uuid . '.pdf';
                    Storage::disk('public')->put('fel/' . $pdfName, $pdfData);
                    $pdfPath = 'fel/' . $pdfName;
                } catch (\Exception $pdfEx) {
                    Log::warning("No se pudo guardar el archivo PDF de la certificación: " . $pdfEx->getMessage());
                }
            }

            return [
                'success' => true,
                'uuid' => $uuid,
                'serie' => $serie,
                'numero' => $numero,
                'fecha_certificacion' => $fechaCert ? Carbon::parse($fechaCert) : now(),
                'pdf_path' => $pdfPath
            ];

        } catch (\Exception $e) {
            Log::error("Error de certificación FEL en venta ID {$venta->id}: " . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Anular una factura previamente certificada
     *
     * @param Venta $venta
     * @param string $motivo
     * @return array
     * @throws \Exception
     */
    public function anularFactura(Venta $venta, string $motivo): array
    {
        if (!config('fel.enabled')) {
            Log::info("Anulación FEL desactivada. Simulando para venta ID {$venta->id}.");
            return [
                'success' => true,
                'mensaje' => 'Anulación simulada exitosamente (FEL desactivado)'
            ];
        }

        $anulacionXml = $this->generarAnulacionXml($venta, $motivo);

        $soapBody = '<?xml version="1.0" encoding="utf-8"?>
<Envelope xmlns="http://schemas.xmlsoap.org/soap/envelope/">
  <Body>
    <AnulacionDocumento xmlns="http://apicertificacion.desa.tekra.com.gt:8080/certificacion/wsdl/">
      <Autenticacion>
        <pn_usuario>' . htmlspecialchars(config('fel.user')) . '</pn_usuario>
        <pn_clave>' . htmlspecialchars(config('fel.password')) . '</pn_clave>
        <pn_cliente>' . htmlspecialchars(config('fel.client')) . '</pn_cliente>
        <pn_contrato>' . htmlspecialchars(config('fel.contract')) . '</pn_contrato>
        <pn_id_origen>Sistema Facturacion</pn_id_origen>
        <pn_ip_origen>' . htmlspecialchars(request()->ip() ?: '127.0.0.1') . '</pn_ip_origen>
        <pn_firmar_emisor>SI</pn_firmar_emisor>
      </Autenticacion>
      <Documento><![CDATA[' . $anulacionXml . ']]></Documento>
    </AnulacionDocumento>
  </Body>
</Envelope>';

        try {
            $response = Http::withHeaders([
                'Content-Type' => 'text/xml; charset=utf-8',
                'SOAPAction' => '""',
            ])->send('POST', config('fel.soap_url'), [
                'body' => $soapBody
            ]);

            if (!$response->successful()) {
                throw new \Exception("Error de comunicación con el certificador al anular (HTTP status " . $response->status() . ")");
            }

            $responseBody = $response->body();
            
            // Limpiar namespaces
            $cleanXml = preg_replace('/(<\/?[a-zA-Z0-9\-]+):/', '<', $responseBody);
            $cleanXml = preg_replace('/ xmlns:[a-zA-Z0-9\-]+="[^"]+"/', '', $cleanXml);
            
            $xmlObj = simplexml_load_string($cleanXml);
            if (!$xmlObj) {
                throw new \Exception("No se pudo parsear el XML de respuesta de anulación.");
            }

            $responseNode = null;
            if (isset($xmlObj->Body->AnulacionDocumentoResponse)) {
                $responseNode = $xmlObj->Body->AnulacionDocumentoResponse;
            } else {
                $json = json_encode($xmlObj);
                $array = json_decode($json, true);
                $responseNode = $this->buscarNodoRecursivo($array, 'AnulacionDocumentoResponse');
                if ($responseNode) {
                    $responseNode = json_decode(json_encode($responseNode));
                }
            }

            if (!$responseNode) {
                throw new \Exception("Respuesta de anulación inesperada: " . substr($responseBody, 0, 500));
            }

            $resultado = json_decode((string)$responseNode->ResultadoAnulacion, true);
            if (isset($resultado['error']) && $resultado['error'] !== 0) {
                throw new \Exception("Error en anulación SAT: " . ($resultado['mensaje'] ?? 'Error desconocido'));
            }

            return [
                'success' => true,
                'mensaje' => $resultado['mensaje'] ?? 'Anulación exitosa'
            ];

        } catch (\Exception $e) {
            Log::error("Error de anulación FEL en venta ID {$venta->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Consultar un NIT mediante la REST API de TEKRA
     *
     * @param string $nit
     * @return array
     */
    public function consultarNit(string $nit): array
    {
        if (!config('fel.enabled')) {
            return [
                'success' => true,
                'nit' => $nit,
                'nombre' => 'CONSUMIDOR FINAL',
                'estado' => 'ACTIVO'
            ];
        }

        // En ambiente de desarrollo local o si el NIT es de pruebas, simularlo para evitar llamadas fallidas de producción
        if (app()->environment('local', 'testing') && in_array($nit, ['12345678', 'CF', 'C/F', '123456789'])) {
            return [
                'success' => true,
                'nit' => $nit,
                'nombre' => $nit === '12345678' ? 'JOHN DOE' : 'CONSUMIDOR FINAL',
                'estado' => 'ACTIVO'
            ];
        }

        try {
            $payload = [
                'autenticacion' => [
                    'pn_usuario' => config('fel.user'),
                    'pn_clave' => config('fel.password'),
                ],
                'parametros' => [
                    'pn_empresa' => 1,
                    'pn_cliente' => (int)config('fel.client'),
                    'pn_contrato' => (int)config('fel.contract'),
                    'pn_nit' => $nit
                ]
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post(config('fel.rest_url') . '/certificaciones/contribuyente/contribuyente_consulta', $payload);

            if (!$response->successful()) {
                throw new \Exception("Error de comunicación con REST API NIT (Status: " . $response->status() . ")");
            }

            $body = $response->json();
            $error = $body['resultado'][0]['error'] ?? -1;

            if ($error !== 0) {
                throw new \Exception($body['resultado'][0]['mensaje'] ?? 'NIT no encontrado');
            }

            $datos = $body['datos'][0] ?? null;
            if (!$datos) {
                throw new \Exception('No se retornaron datos para el NIT');
            }

            return [
                'success' => true,
                'nit' => $datos['nit'] ?? $nit,
                'nombre' => $datos['nombre'] ?? '',
                'estado' => $datos['estado'] ?? 'ACTIVO'
            ];

        } catch (\Exception $e) {
            // Mofa fallback en desarrollo para que no se bloquee el usuario
            if (app()->environment('local', 'development')) {
                return [
                    'success' => true,
                    'nit' => $nit,
                    'nombre' => 'CONTRIBUYENTE SIMULADO (' . $nit . ')',
                    'estado' => 'ACTIVO',
                    'nota' => 'Simulado en ambiente local por error: ' . $e->getMessage()
                ];
            }

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Consultar un CUI mediante la REST API de TEKRA
     *
     * @param string $cui
     * @return array
     */
    public function consultarCui(string $cui): array
    {
        if (!config('fel.enabled')) {
            return [
                'success' => true,
                'cui' => $cui,
                'nombre' => 'CIUDADANO SIMULADO',
                'fallecido' => false
            ];
        }

        if (app()->environment('local', 'testing') && in_array($cui, ['1234567890123', '12345678'])) {
            return [
                'success' => true,
                'cui' => $cui,
                'nombre' => 'JUAN PÉREZ LÓPEZ',
                'fallecido' => false
            ];
        }

        try {
            $payload = [
                'autenticacion' => [
                    'pn_usuario' => config('fel.user'),
                    'pn_clave' => config('fel.password'),
                ],
                'parametros' => [
                    'pn_cui' => $cui
                ]
            ];

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Accept' => 'application/json'
            ])->post(config('fel.rest_url') . '/certificaciones/contribuyente/contribuyente_consulta_cui', $payload);

            if (!$response->successful()) {
                throw new \Exception("Error de comunicación con REST API CUI (Status: " . $response->status() . ")");
            }

            $body = $response->json();
            $error = $body['resultado'][0]['error'] ?? -1;

            if ($error !== 0) {
                throw new \Exception($body['resultado'][0]['mensaje'] ?? 'CUI no encontrado');
            }

            $datos = $body['datos'][0] ?? null;
            if (!$datos) {
                throw new \Exception('No se retornaron datos para el CUI');
            }

            return [
                'success' => true,
                'cui' => $datos['CUI'] ?? $cui,
                'nombre' => $datos['nombre'] ?? '',
                'fallecido' => filter_var($datos['fallecido'] ?? false, FILTER_VALIDATE_BOOLEAN)
            ];

        } catch (\Exception $e) {
            if (app()->environment('local', 'development')) {
                return [
                    'success' => true,
                    'cui' => $cui,
                    'nombre' => 'CIUDADANO SIMULADO (' . $cui . ')',
                    'fallecido' => false,
                    'nota' => 'Simulado en ambiente local por error: ' . $e->getMessage()
                ];
            }

            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Generar el XML de certificación (FACT o FCAM)
     */
    private function generarDteXml(Venta $venta): string
    {
        $tipoDte = ($venta->tipo_venta === 'credito' && $venta->ventaCredito) ? 'FCAM' : 'FACT';
        
        $fechaHora = now()->format('Y-m-d\TH:i:sP'); // ISO 8601 con zona horaria
        $numeroAcceso = rand(100000000, 999999999);

        // Datos del emisor
        $emisorNit = config('fel.emisor.nit');
        $emisorNombre = htmlspecialchars(config('fel.emisor.nombre'));
        $emisorNombreComercial = htmlspecialchars(config('fel.emisor.nombre_comercial'));
        $emisorDireccion = htmlspecialchars(config('fel.emisor.direccion'));
        $emisorMunicipio = htmlspecialchars(config('fel.emisor.municipio'));
        $emisorDepartamento = htmlspecialchars(config('fel.emisor.departamento'));
        $emisorCodigoPostal = config('fel.emisor.codigo_postal');
        $emisorAfiliacionIva = config('fel.emisor.afiliacion_iva');

        // Establecimiento dinámico basado en la sucursal de la venta
        $establecimiento = $venta->sucursal->codigo ?? 1;

        // Datos del receptor
        $receptorNit = preg_replace('/[^a-zA-Z0-9]/', '', $venta->cliente_nit ?? 'CF');
        if (strtoupper($receptorNit) === 'CF' || strtoupper($receptorNit) === 'CF') {
            $receptorNit = 'CF';
        }
        $receptorNombre = htmlspecialchars($venta->cliente_nombre);
        $receptorCorreo = htmlspecialchars($venta->cliente_email ?? '');
        
        // Obtener dirección del cliente
        $receptorDireccion = 'GUATEMALA';
        $receptorMunicipio = '';
        $receptorDepartamento = '';

        if ($venta->cliente) {
            $receptorDireccion = htmlspecialchars($venta->cliente->direccion ?? 'GUATEMALA');
            $receptorMunicipio = htmlspecialchars($venta->cliente->municipio ?? '');
            $receptorDepartamento = htmlspecialchars($venta->cliente->departamento ?? '');
        }

        // Armar cabecera XML
        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<dte:GTDocumento Version="0.1" xmlns:dte="http://www.sat.gob.gt/dte/fel/0.2.0" xmlns:cfc="http://www.sat.gob.gt/dte/fel/CompCambiaria/0.1.0" xmlns:cex="http://www.sat.gob.gt/face2/ComplementoExportaciones/0.1.0" xmlns:cfe="http://www.sat.gob.gt/face2/ComplementoFacturaEspecial/0.1.0" xmlns:cno="http://www.sat.gob.gt/face2/ComplementoReferenciaNota/0.1.0" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <dte:SAT ClaseDocumento="dte">
    <dte:DTE ID="DatosCertificados">
      <dte:DatosEmision ID="DatosEmision">
        <dte:DatosGenerales Tipo="' . $tipoDte . '" FechaHoraEmision="' . $fechaHora . '" CodigoMoneda="GTQ" />
        <dte:Emisor NITEmisor="' . $emisorNit . '" NombreEmisor="' . $emisorNombre . '" CodigoEstablecimiento="' . $establecimiento . '" NombreComercial="' . $emisorNombreComercial . '" CorreoEmisor="" AfiliacionIVA="' . $emisorAfiliacionIva . '">
          <dte:DireccionEmisor>
            <dte:Direccion>' . $emisorDireccion . '</dte:Direccion>
            <dte:CodigoPostal>' . $emisorCodigoPostal . '</dte:CodigoPostal>
            <dte:Municipio>' . $emisorMunicipio . '</dte:Municipio>
            <dte:Departamento>' . $emisorDepartamento . '</dte:Departamento>
            <dte:Pais>GT</dte:Pais>
          </dte:DireccionEmisor>
        </dte:Emisor>
        <dte:Receptor IDReceptor="' . $receptorNit . '" NombreReceptor="' . $receptorNombre . '" CorreoReceptor="' . $receptorCorreo . '">
          <dte:DireccionReceptor>
            <dte:Direccion>' . $receptorDireccion . '</dte:Direccion>
            <dte:CodigoPostal>0</dte:CodigoPostal>
            <dte:Municipio>' . $receptorMunicipio . '</dte:Municipio>
            <dte:Departamento>' . $receptorDepartamento . '</dte:Departamento>
            <dte:Pais>GT</dte:Pais>
          </dte:DireccionReceptor>
        </dte:Receptor>
        <dte:Frases>
          <dte:Frase TipoFrase="1" CodigoEscenario="1" />
        </dte:Frases>
        <dte:Items>';

        // Recorrer detalles
        $totalImpuestosAcumulados = 0;
        foreach ($venta->detalles as $index => $detalle) {
            $linea = $index + 1;
            $cantidad = (float)$detalle->cantidad;
            $precioUnitario = (float)$detalle->precio_unitario;
            $descuento = (float)($detalle->descuento ?? 0);
            $totalFila = $detalle->total; // Ya restado el descuento

            $montoGravable = round($totalFila / 1.12, 4);
            $montoImpuesto = round($totalFila - $montoGravable, 4);
            $totalImpuestosAcumulados += $montoImpuesto;

            // Formatear a 4 decimales requeridos
            $cantStr = number_format($cantidad, 4, '.', '');
            $precioUnitStr = number_format($precioUnitario, 4, '.', '');
            $precioStr = number_format($precioUnitario * $cantidad, 4, '.', '');
            $descStr = number_format($descuento, 4, '.', '');
            $gravStr = number_format($montoGravable, 4, '.', '');
            $impStr = number_format($montoImpuesto, 4, '.', '');
            $totalFilaStr = number_format($totalFila, 4, '.', '');

            $xml .= '
          <dte:Item NumeroLinea="' . $linea . '" BienOServicio="B">
            <dte:Cantidad>' . $cantStr . '</dte:Cantidad>
            <dte:Descripcion>' . htmlspecialchars($detalle->descripcion) . '</dte:Descripcion>
            <dte:PrecioUnitario>' . $precioUnitStr . '</dte:PrecioUnitario>
            <dte:Precio>' . $precioStr . '</dte:Precio>
            <dte:Descuento>' . $descStr . '</dte:Descuento>
            <dte:Impuestos>
              <dte:Impuesto>
                <dte:NombreCorto>IVA</dte:NombreCorto>
                <dte:CodigoUnidadGravable>1</dte:CodigoUnidadGravable>
                <dte:MontoGravable>' . $gravStr . '</dte:MontoGravable>
                <dte:MontoImpuesto>' . $impStr . '</dte:MontoImpuesto>
              </dte:Impuesto>
            </dte:Impuestos>
            <dte:Total>' . $totalFilaStr . '</dte:Total>
          </dte:Item>';
        }

        $xml .= '
        </dte:Items>
        <dte:Totales>
          <dte:TotalImpuestos>
            <dte:TotalImpuesto NombreCorto="IVA" TotalMontoImpuesto="' . number_format($totalImpuestosAcumulados, 4, '.', '') . '" />
          </dte:TotalImpuestos>
          <dte:GranTotal>' . number_format($venta->total_final, 4, '.', '') . '</dte:GranTotal>
        </dte:Totales>';

        // 5. Complemento Factura Cambiaria (FCAM)
        if ($tipoDte === 'FCAM') {
            $xml .= '
        <dte:Complementos>
          <dte:Complemento IDComplemento="AbonosFCAM" NombreComplemento="AbonosFacturaCambiaria" URIComplemento="">
            <cfc:AbonosFacturaCambiaria Version="1">';
            
            // Loop cuotas
            $cuotas = $venta->ventaCredito->planPagos()->orderBy('numero_cuota', 'asc')->get();
            foreach ($cuotas as $cuota) {
                $xml .= '
              <cfc:Abono>
                <cfc:NumeroAbono>' . $cuota->numero_cuota . '</cfc:NumeroAbono>
                <cfc:FechaVencimiento>' . $cuota->fecha_vencimiento->format('Y-m-d') . '</cfc:FechaVencimiento>
                <cfc:MontoAbono>' . number_format($cuota->monto_cuota_proyectado, 2, '.', '') . '</cfc:MontoAbono>
              </cfc:Abono>';
            }
            
            $xml .= '
            </cfc:AbonosFacturaCambiaria>
          </dte:Complemento>
        </dte:Complementos>';
        }

        // Cerrar tags principales
        $xml .= '
      </dte:DatosEmision>
    </dte:DTE>
    <dte:Adenda>
      <DECertificador>' . $venta->id . '</DECertificador>
    </dte:Adenda>
  </dte:SAT>
</dte:GTDocumento>';

        return $xml;
    }

    /**
     * Generar el XML de anulación
     */
    private function generarAnulacionXml(Venta $venta, string $motivo): string
    {
        $fechaHoraAnulacion = now()->format('Y-m-d\TH:i:sP');
        $fechaEmision = $venta->created_at->format('Y-m-d\TH:i:sP');

        $emisorNit = config('fel.emisor.nit');
        
        $receptorNit = preg_replace('/[^a-zA-Z0-9]/', '', $venta->cliente_nit ?? 'CF');
        if (strtoupper($receptorNit) === 'CF') {
            $receptorNit = 'CF';
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>
<dte:GTAnulacionDocumento Version="0.1" xmlns:dte="http://www.sat.gob.gt/dte/fel/0.1.0" xmlns:ds="http://www.w3.org/2000/09/xmldsig#" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">
  <dte:SAT ClaseDocumento="dte">
    <dte:AnulacionDTE ID="DatosCertificados">
      <dte:DatosGenerales ID="DatosAnulacion" NumeroDocumentoAAnular="' . $venta->uuid_fel . '" NITEmisor="' . $emisorNit . '" IDReceptor="' . $receptorNit . '" FechaEmisionDocumentoAnular="' . $fechaEmision . '" FechaHoraAnulacion="' . $fechaHoraAnulacion . '" MotivoAnulacion="' . htmlspecialchars($motivo) . '" />
    </dte:AnulacionDTE>
  </dte:SAT>
</dte:GTAnulacionDocumento>';

        return $xml;
    }

    /**
     * Helper recursivo para encontrar un elemento en un array
     */
    private function buscarNodoRecursivo($array, $claveBuscar)
    {
        if (!is_array($array)) {
            return null;
        }

        if (array_key_exists($claveBuscar, $array)) {
            return $array[$claveBuscar];
        }

        foreach ($array as $subArray) {
            $res = $this->buscarNodoRecursivo($subArray, $claveBuscar);
            if ($res !== null) {
                return $res;
            }
        }

        return null;
    }
}
