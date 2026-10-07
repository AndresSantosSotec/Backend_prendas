<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $empresa['nombre'] ?? 'CREDIPRENDAS' }} - Contrato {{ $credito->codigo_credito ?? $credito->numero_credito ?? 'S/N' }}</title>
    <style>
        @page {
            size: letter;
            margin: 2.2cm 2cm 1.8cm 2.5cm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            line-height: 1.5;
            color: #000;
            text-align: justify;
            margin: 0;
            padding: 0;
        }
        .header-logo {
            text-align: center;
            margin-bottom: 12px;
            padding-bottom: 8px;
            border-bottom: 1px solid #ccc;
        }
        .pdf-footer {
            position: fixed;
            right: 0;
            bottom: -1.1cm;
            left: 0;
            border-top: 1px solid #999;
            padding-top: 3px;
            color: #555;
            font-size: 8pt;
            text-align: center;
        }
        .pdf-footer .pagina::after {
            content: "Página " counter(page);
        }
        p {
            margin: 0 0 10px 0;
            text-align: justify;
            text-indent: 0;
        }

        titulo {
            margin: 0 0 10px 0;
            text-align: center;
            text-indent: 0;
            font-size: 15pt;
            font-weight: bold;
        }

        subtitulo {
            margin: 0 0 10px 0;
            text-align: left;
            text-indent: 0;
            font-size: 10pt;
            font-weight: bold;
        }

        .page-break {
            page-break-after: always;
            clear: both;
        }

        .negrita {
            font-weight: bold;
        }
        .mayusculas {
            text-transform: uppercase;
        }
        
        .linea-firma {
            border-top: 1.5px solid #000;
            width: 85%;
            margin: 0 auto 5px auto;
        }
        .firma-izq {
            width: 50%;
            background-color: rgb(155, 148, 148);
        }
        .firma-der {
            width: 50%;
            background-color: rgb(155, 148, 148);
        }

        .contenido-tabla {
            width: 100%;
            border-collapse: collapse;
            table-layout: fixed;
            margin: 0 0 12px 0;
            page-break-inside: auto;
        }
        .campo-col-izq {
            width: 35%;
            background-color: rgb(155, 148, 148);
        }
        .informacion-col-der {
            width: 65%;
            background-color: rgb(155, 148, 148);
        }
        .contenido-tabla th,
        .contenido-tabla td {
            border-color: rgb(0, 0, 0);
            border-style: solid;
            border-width: 1px;
            padding: 4px 6px;
            text-align: left;
            vertical-align: top;
            overflow-wrap: break-word;
            word-wrap: break-word;
        }
        .contenido-tabla th {
            text-align: center;
        }
        .contenido-tabla tbody th {
            font-weight: normal;
            text-align: left;
        }

    </style>
</head>
<body>

@php
    use App\Support\NumeroALetrasHelper;
    use Carbon\Carbon;

    // Entidad
    $nombreEmpresa = $empresa['nombre'] ?? 'CREDIPRENDAS';
    $razonSocial = $empresa['razon_social'] ?? 'GRUPO JUMERC, SOCIEDAD ANÓNIMA';
    $direccionEmpresa = $empresa['direccion'] ?? 'Soloma, Ciudad de Guatemala';
    $nitEmpresa = $empresa['nit'] ?? '11111';
    $correoEmpresa = $empresa['correo'] ?? 'correo@dominio.com';
    $telefonoEmpresa = $empresa['telefono'] ?? '11111 11111';

    // Representante Legal
    $representanteNombre = $docConfig->firmante_2_nombre ?: ($empresa['representante_legal'] ?? 'MANUEL FRANCISCO GARCÍA ROBLES');
    $representanteTitulo = $docConfig->firmante_2_titulo ?: 'ADMINISTRADOR ÚNICO Y REPRESENTANTE LEGAL';
    $representanteEdad = 'treinta y seis años';
    $representanteEstadoCivil = 'casado';
    $representanteNacionalidad = 'guatemalteco';
    $representanteProfesion = 'Perito Contador';
    $representanteDomicilio = 'departamento Totonicapán';
    $representanteDpiLetras = 'Dos mil ciento ochenta y dos, setenta y cuatro mil cuatrocientos dieciséis, cero ochocientos uno (2182 74416 0801)';
    $representanteActaNotario = 'Herson José Argueta Ola';
    $representanteActaFecha = 'treinta y uno de agosto del año dos mil veintiséis';
    $representanteRegMercantil = 'ochocientos cincuenta y ocho mil setecientos sesenta y nueve (858,769)';
    $representanteFolio = 'setecientos noventa y ocho (798)';
    $representanteLibro = 'ochocientos sesenta y seis (866)';

    // Fecha del contrato
    $fechaContratoObj = !empty($credito->fecha_desembolso) 
        ? Carbon::parse($credito->fecha_desembolso) 
        : (!empty($credito->created_at) ? Carbon::parse($credito->created_at) : Carbon::now());
    $fechaContratoLetras = NumeroALetrasHelper::fechaEnLetras($fechaContratoObj);

    // Cliente / Deudora (Auto-rellenado con datos del cliente)
    $clienteNombre = $cliente->nombre_completo ?? trim(($cliente->nombres ?? $cliente->nombre ?? '') . ' ' . ($cliente->apellidos ?? $cliente->apellido ?? ''));
    if (empty($clienteNombre)) {
        $clienteNombre = 'JUAN SIMON JUAN JOSE';
    }

    $esFemenino = !in_array(strtolower($cliente->genero ?? 'femenino'), ['masculino', 'm', 'hombre', 'varon']);
    $clienteTratamiento = $esFemenino ? 'la señora' : 'el señor';
    $clienteRol = $esFemenino ? 'LA DEUDORA' : 'EL DEUDOR';
    $clienteEstadoCivil = !empty($cliente->estado_civil) ? strtolower($cliente->estado_civil) : ($esFemenino ? 'casada' : 'casado');
    $clienteNacionalidad = !empty($cliente->nacionalidad) ? strtolower($cliente->nacionalidad) : ($esFemenino ? 'guatemalteca' : 'guatemalteco');
    $clienteProfesion = !empty($cliente->profesion) ? strtolower($cliente->profesion) : 'comerciante';

    // Edad del cliente en letras
    if (!empty($cliente->fecha_nacimiento)) {
        $aniosCliente = Carbon::parse($cliente->fecha_nacimiento)->age;
        $clienteEdad = NumeroALetrasHelper::convertir($aniosCliente) . ' años de edad';
    } elseif (!empty($cliente->edad)) {
        $clienteEdad = NumeroALetrasHelper::convertir((int)$cliente->edad) . ' años de edad';
    } else {
        $clienteEdad = 'sesenta y cuatro años de edad';
    }

    // Dirección / Domicilio
    $clienteDireccion = $cliente->direccion_completa ?? $cliente->direccion ?? '';
    if (!empty($cliente->municipio) && !str_contains($clienteDireccion, $cliente->municipio)) {
        $clienteDireccion .= (!empty($clienteDireccion) ? ', ' : '') . 'municipio de ' . $cliente->municipio;
    }
    if (empty($clienteDireccion)) {
        $clienteDireccion = 'Cantón Cojxac, Paraje Paracansigua del municipio de Totonicapán, departamento de Totonicapán';
    }

    // DPI / CUI en letras y número
    $clienteDpiRaw = $cliente->dpi ?? $cliente->cui ?? $cliente->numero_documento ?? '2601 35151 0801';
    $clienteDpiLetras = NumeroALetrasHelper::cuiEnLetras($clienteDpiRaw);

    // Monto del Crédito
    $montoNum = (float) ($credito->monto_prestamo ?? $credito->monto_aprobado ?? 700);
    $montoLetras = NumeroALetrasHelper::montoEnLetras($montoNum);
    $destinoCredito = !empty($credito->destino) ? strtoupper($credito->destino) : 'operación e inversión';

    // Plazo y vencimiento
    $plazoDias = (int) ($credito->plazo_dias ?? 30);
    $plazoTexto = ($plazoDias >= 28 && $plazoDias <= 31) ? 'UN MES' : strtoupper(NumeroALetrasHelper::convertir($plazoDias)) . ' DÍAS';

    // Cantidad de cuotas
    $cantidadCuotas = (int) ($credito->numero_cuotas ?? 12);
    $cantidadCuotasTexto = ($cantidadCuotas === 1) ? 'UNA' : strtoupper(NumeroALetrasHelper::convertir($cantidadCuotas));
    $tipoCuota = $credito->tipo_interes ?? 'mensual';
    $montoCuota = $credito->monto_cuota ?? '';
    $planCuotas = collect($planPagos ?? $credito->planPagos ?? [])
        ->sortBy('numero_cuota')
        ->values();
    $cantidadCuotasPlan = $planCuotas->count();
    $cantidadCuotas = $cantidadCuotasPlan > 0 ? $cantidadCuotasPlan : $cantidadCuotas;
    $cantidadCuotasTexto = ($cantidadCuotas === 1)
        ? 'UNA'
        : strtoupper(NumeroALetrasHelper::convertir($cantidadCuotas));
    $cuotasPorMonto = $planCuotas
        ->map(function ($cuota) {
            $monto = data_get($cuota, 'monto_cuota_proyectado')
                ?? data_get($cuota, 'cuota_total')
                ?? data_get($cuota, 'monto_cuota');

            return $monto !== null && $monto !== ''
                ? number_format((float) $monto, 2, '.', '')
                : null;
        })
        ->filter()
        ->countBy();

    if ($cuotasPorMonto->isEmpty() && $montoCuota !== '') {
        $cuotasPorMonto = collect([
            number_format((float) $montoCuota, 2, '.', '') => $cantidadCuotas,
        ]);
    }

    $fechaVencObj = !empty($credito->fecha_vencimiento) 
        ? Carbon::parse($credito->fecha_vencimiento) 
        : $fechaContratoObj->copy()->addDays($plazoDias);
    $fechaVencLetras = strtoupper(NumeroALetrasHelper::fechaEnLetras($fechaVencObj));
    $fechaVencNum = $fechaVencObj->format('d/m/Y');

    // Tasa de interés
    $tasaInteres = (float) ($credito->tasa_interes ?? 13.44);
    $tasaEntero = (int) floor($tasaInteres);
    $tasaDecimal = (int) round(($tasaInteres - $tasaEntero) * 100);
    $tasaLetras = strtoupper(NumeroALetrasHelper::convertir($tasaEntero)) . ($tasaDecimal > 0 ? ' PUNTO ' . strtoupper(NumeroALetrasHelper::convertir($tasaDecimal)) : '') . ' POR CIENTO';

    //tasa mora
    $tasaMora = (float) ($credito->tasa_mora ?? 5.00);

    // Prendas / Garantías
    $listaPrendas = collect($prendas ?? $credito->prendas ?? []);

    // Descripción de la prenda. Los datos faltantes se muestran como una cadena vacía.
    $prenda = $listaPrendas->first();
    $nombrePrenda = $prenda?->descripcion ?? '';
    $categoriaID = $prenda?->categoria_producto_id ?? '';
    $categoriaPrenda = $prenda?->categoriaProducto?->nombre ?? '';
    $marcaPrenda = $prenda?->marca ?? '';
    $modeloPrenda = $prenda?->modelo ?? '';
    $anioPrenda = $prenda?->anio ?? '';
    $colorPrenda = $prenda?->color ?? '';
    $seriePrenda = $prenda?->serie ?? '';
    $motorPrenda = $prenda?->numero_motor ?? '';
    $chasisPrenda = $prenda?->numero_chasis ?? '';
    $imeiPrenda = $prenda?->imei ?? '';
    $placaPrenda = $prenda?->placa ?? '';
    $despPrenda = $prenda?->caracteristicas ?? '';
    $estadoPrenda = $prenda?->condicion ?? '';
    $valorComercialPrenda = $prenda?->valor_estimado_cliente ?? '';
    $valorAvaluoPrenda = $prenda?->valor_tasacion ?? '';
    $garantiaPrenda = $prenda?->valor_garantia ?? '';
    $propiedadPrenda = $prenda?->documento_propiedad ?? '';
    $numeroDocumentoPrenda = $prenda?->numero_documento ?? '';




@endphp

    {{-- LOGO INSTITUCIONAL --}}
    @if($mostrarLogo ?? true)
    <div class="header-logo">
        <table width="100%">
            <tr>
                <td width="30%" align="left">
                    @include('pdf.partials.logo', ['height' => '65px'])
                </td>
                <td width="70%" align="right">
                    <div style="font-size: 13pt; font-weight: bold; color: #1e3a8a;">{{ $nombreEmpresa }}</div>
                    <div style="font-size: 9pt; color: #333;">{{ $razonSocial }}</div>
                    <div style="font-size: 8.5pt; color: #555;">{{ $sucursal->direccion ?? 'Oficinas Centrales' }} | Tel: {{ $sucursal->telefono ?? $empresa['telefono'] ?? 'PBX' }}</div>
                </td>
            </tr>
        </table>
    </div>
    @endif

    <titulo>
         <center>CREDIPRENDAS</center>
         <center>CONTRATO DE CRÉDITO CON GARANTÍA PRENDARIAS</center>
    </titulo>
    <p><center>Soloma, Guatemala</center></p>

    <p>
        CREDIPRENDAS, con sede en {{ $direccionEmpresa }}, identificada con NIT {{ $nitEmpresa }}, correo electrónico {{ $correoEmpresa }} y teléfono {{ $telefonoEmpresa }}, en adelante denominada <strong>“LA ACREEDORA”</strong>, y por otra parte <strong>{{ $clienteNombre }}</strong>, en adelante denominado "<strong>{{ $clienteRol }}</strong>",  celebran el presente CONTRATO DE CRÉDITO CON GARANTÍA PRENDARIA, sujeto a las siguientes cláusulas:
    </p>

    <subtitulo>PRIMERA. ANTECEDENTES Y APROBACIÓN DEL CRÉDITO</subtitulo>
    <p>
        LA ACREEDORA, con base en su reglamento general de créditos y en el dictamen No. 383-26 del Departamento de Créditos, de fecha {{ $fechaVencNum }}, aprobó a favor de {{ $clienteRol }}  un crédito con recursos provenientes de fondos propios. El crédito corresponde a la modalidad MENSUAL SANTA INDIVIDUAL, bajo la modalidad de crédito individual, destinado a gastos de {{ $destinoCredito }}.
    </p>

    <subtitulo>SEGUNDA. MONTO DEL CRÉDITO</subtitulo>
    <p>
        LA ACREEDORA concede a {{ $clienteRol }} un crédito por la cantidad de <strong>{{ $montoLetras }}</strong>. El monto será entregado a {{ $clienteRol }} mediante una sola entrega, utilizando el documento legal de pago que corresponda.
    </p>

    <subtitulo>TERCERA. PLAZO</subtitulo>
    <p>
        El plazo del crédito será de <strong>{{ $cantidadCuotasTexto}} ({{ $cantidadCuotas }}) cuotas {{ $tipoCuota }}</strong>, contadas a partir de la fecha efectiva de desembolso de los fondos.
    </p>

    <subtitulo>CUARTA. TASA DE INTERÉS</subtitulo>
    <p>
        El crédito devengará una tasa de interés del {{ $tasaInteres}}% de conformidad con las condiciones aprobadas por LA ACREEDORA. La forma de cálculo, periodicidad y aplicación de los intereses será la establecida en el plan de pagos que forma parte integrante del presente contrato.
    </p>

    <subtitulo>QUINTA. FORMA DE PAGO</subtitulo>
    <p>
        {{ $clienteRol }} se obliga a cancelar el crédito mediante {{ $cantidadCuotasTexto }} ({{ $cantidadCuotas }}) cuotas {{ $tipoCuota }}.
        @if($cuotasPorMonto->isNotEmpty())
            <strong>
                Se establecen
                @foreach($cuotasPorMonto as $monto => $cantidad)
                    {{ $cantidad }} {{ $cantidad === 1 ? 'cuota' : 'cuotas' }} {{ $tipoCuota }} de
                    Q. {{ number_format((float) $monto, 2, '.', ',') }}
                    @if($cantidad > 1)
                        cada una
                    @endif
                    {{ $loop->last ? '.' : ($loop->remaining === 1 ? ' y ' : ', ') }}
                @endforeach
            </strong>
        @else
            <strong>Se establecen {{ $cantidadCuotas }} cuotas {{ $tipoCuota }}.</strong>
        @endif
        El calendario exacto de vencimientos será el establecido en el plan de pagos entregado a {{ $clienteRol }}.
    </p>

    <subtitulo>SEXTA. CONSTITUCIÓN DE GARANTÍA PRENDARIA</subtitulo>
    <p>
        Para garantizar el cumplimiento de todas las obligaciones derivadas del presente contrato, {{ $clienteRol }} constituye a favor de LA ACREEDORA <strong>GARANTÍA PRENDARIA</strong> sobre el bien descrito en el presente contrato. La garantía comprende las obligaciones derivadas del capital, intereses, intereses moratorios, gastos de cobranza, gastos administrativos, costas y demás obligaciones legalmente exigibles que se deriven del crédito.
    </p>

    <subtitulo>SÉPTIMA. IDENTIFICACIÓN DEL BIEN DADO EN PRENDA</subtitulo>
    <p>
        El bien objeto de la garantía queda identificado en el cuadro de identificación incluido en este contrato. La identificación deberá corresponder exactamente al bien recibido como garantía.
    </p>

    <subtitulo>OCTAVA. PROPIEDAD Y LEGITIMIDAD DEL BIEN</subtitulo>
    <p>
        {{ $clienteRol }} declara bajo su responsabilidad que el bien entregado en garantía es de su legítima propiedad o que cuenta con las facultades legales suficientes para constituirlo en garantía. Asimismo, declara que el bien se encuentra libre de gravámenes, limitaciones, embargos o reclamaciones de terceros, salvo aquellas expresamente declaradas y aceptadas por LA ACREEDORA.
    </p>

    <subtitulo>NOVENA. ENTREGA Y CUSTODIA DEL BIEN</subtitulo>
    <p>
        El bien dado en garantía quedará bajo la modalidad de custodia que corresponda de acuerdo con su naturaleza y las políticas de LA ACREEDORA. Cuando el bien sea entregado físicamente a LA ACREEDORA, esta deberá mantenerlo bajo custodia durante la vigencia de la obligación, salvo las situaciones previstas en el presente contrato y en la legislación aplicable.
    </p>

    <subtitulo>DÉCIMA. CONSERVACIÓN DEL BIEN</subtitulo>
    <p>
        {{ $clienteRol }} se obliga a conservar el bien objeto de la garantía en condiciones adecuadas y a informar inmediatamente a LA ACREEDORA cualquier daño, pérdida, destrucción, deterioro, robo o circunstancia que pueda disminuir significativamente su valor.
    </p>

    <subtitulo>DÉCIMA PRIMERA. PROHIBICIÓN DE DISPOSICIÓN</subtitulo>
    <p>
        Mientras existan obligaciones pendientes de pago, {{ $clienteRol }} no podrá vender, donar, ceder, transferir, ocultar, sustituir, gravar o disponer del bien dado en garantía sin autorización previa de LA ACREEDORA, cuando dicha autorización sea legalmente necesaria.
    </p>

    <subtitulo>DÉCIMA SEGUNDA. MORA</subtitulo>
    <p>
        En caso de que {{ $clienteRol }} no cancele las obligaciones correspondientes en las fechas establecidas, LA ACREEDORA podrá aplicar el recargo por mora aprobado para el crédito. El recargo establecido en la resolución corresponde al <strong>{{ $tasaMora }}% anual sobre los intereses vencidos sobre la tasa vigente</strong>, computándose a partir del primer día posterior al vencimiento.
    </p>

    <subtitulo>DÉCIMA TERCERA. INCUMPLIMIENTO</subtitulo>
    <p>
        Se considerará incumplimiento la falta de pago de cualquiera de las cuotas; la falsedad o inexactitud de la información proporcionada; la pérdida, ocultamiento, venta, transferencia o disposición no autorizada del bien; la disminución sustancial del valor de la garantía; o el incumplimiento de cualquiera de las obligaciones establecidas en este contrato.
    </p>

    <subtitulo>DÉCIMA CUARTA. EJECUCIÓN DE LA GARANTÍA</subtitulo>
    <p>
        En caso de incumplimiento de las obligaciones garantizadas, LA ACREEDORA podrá hacer efectiva la garantía prendaria conforme al procedimiento establecido por la legislación aplicable. El producto obtenido de la realización de la garantía será aplicado al pago de las obligaciones pendientes, en el orden que legalmente corresponda.
    </p>

    <subtitulo>DÉCIMA QUINTA. DESCUENTOS Y CARGOS</subtitulo>
    <p>
        {{ $clienteRol }} reconoce los siguientes cargos asociados al crédito: gastos administrativos del <strong>2.50%</strong> sobre el monto del préstamo y seguros del <strong>0.50%</strong> sobre el monto del préstamo, aplicados conforme a las condiciones aprobadas y al plan de pagos.
    </p>

    <subtitulo>DÉCIMA SEXTA. DESTINO DEL CRÉDITO</subtitulo>
    <p>
        {{ $clienteRol }} manifiesta que los fondos recibidos serán destinados a <strong>gastos de {{ $destinoCredito }}</strong>, conforme a la finalidad declarada y aprobada por LA ACREEDORA.
    </p>

    <subtitulo>DÉCIMA SÉPTIMA. DOCUMENTACIÓN INTEGRANTE</subtitulo>
    <p>
        Forman parte integrante del presente contrato: resolución de aprobación del crédito, plan de pagos, documento de identificación de {{ $clienteRol }}, documento que acredita la propiedad del bien, avalúo o valoración cuando corresponda, acta o constancia de entrega y recepción, fotografías o documentación de identificación y cualquier otro documento relacionado con la garantía.
    </p>

    <subtitulo>DÉCIMA OCTAVA. VIGENCIA</subtitulo>
    <p>
        El presente contrato tendrá vigencia desde la fecha de su firma y permanecerá vigente hasta la cancelación total de las obligaciones derivadas del crédito y la correspondiente liberación de la garantía prendaria.
    </p>

    <subtitulo>DÉCIMA NOVENA. ACEPTACIÓN</subtitulo>
    <p>
        Leído íntegramente el presente contrato, las partes manifiestan que conocen y aceptan su contenido, condiciones y obligaciones, y lo suscriben en señal de conformidad.
    </p>

    <div class="page-break"></div>

    <subtitulo>ANEXO A. IDENTIFICACIÓN DEL BIEN DADO EN PRENDA</subtitulo>

    <table class="contenido-tabla">
        <thead>
            <tr>
                <th class="campo-col-izq">Campo</th>
                <th class="informacion-col-der">Información</th>
            </tr>
        </thead>
        <tbody>
            @empty($nombrePrenda)
            @else
            <tr><th>Tipo de bien</th><td>{{ $nombrePrenda }}</td></tr>
            @endempty
            @empty($categoriaPrenda)
            @else
            <tr><th>Categoría</th><td>{{ $categoriaPrenda }}</td></tr>
            @endempty
            @empty($marcaPrenda)
            @else
            <tr><th>Marca</th><td>{{ $marcaPrenda }}</td></tr>
            @endempty
            @empty($modeloPrenda)
            @else
            <tr><th>Modelo</th><td>{{ $modeloPrenda }}</td></tr>
            @endempty
            @empty($anioPrenda)
            @else
            <tr><th>Año</th><td>{{ $anioPrenda }}</td></tr>
            @endempty
            @empty($colorPrenda)
            @else
            <tr><th>Color</th><td>{{ $colorPrenda }}</td></tr>
            @endempty
            @empty($seriePrenda)
            @else
            <tr><th>Número de serie</th><td>{{ $seriePrenda }}</td></tr>
            @endempty
            @empty($motorPrenda)
            @else
            <tr><th>Número de motor</th><td>{{ $motorPrenda }}</td></tr>
            @endempty
            @empty($chasisPrenda)
            @else
            <tr><th>Número de chasis/VIN</th><td>{{ $chasisPrenda }}</td></tr>
            @endempty
            @empty($imeiPrenda)
            @else
            <tr><th>IMEI</th><td>{{ $imeiPrenda }}</td></tr>
            @endempty
            @empty($placaPrenda)
            @else
            <tr><th>Placa</th><td>{{ $placaPrenda }}</td></tr>
            @endempty
            @empty($despPrenda)
            @else
            <tr><th>Descripción detallada</th><td>{{ $despPrenda }}</td></tr>
            @endempty
            @empty($estadoPrenda)
            @else
            <tr><th>Estado físico</th><td>{{ $estadoPrenda }}</td></tr>
            @endempty
            @empty($valorComercialPrenda)
            @else
            <tr><th>Valor comercial</th><td>Q. {{ $valorComercialPrenda }}</td></tr>
            @endempty
            @empty($valorAvaluoPrenda)
            @else
            <tr><th>Valor de avalúo</th><td>Q. {{ $valorAvaluoPrenda }}</td></tr>
            @endempty
            @empty($garantiaPrenda)
            @else
            <tr><th>Valor asignado como garantía</th><td>Q. {{ $garantiaPrenda }}</td></tr>
            @endempty
            @empty($propiedadPrenda)
            @else
            <tr><th>Documento de propiedad</th><td>{{ $propiedadPrenda }}</td></tr>
            @endempty
            @empty($numeroDocumentoPrenda)
            @else
            <tr><th>Número de documento</th><td>{{ $numeroDocumentoPrenda }}</td></tr>
            @endempty
        </tbody>
    </table>

    <subtitulo>FIRMAS</subtitulo><br>

    <table class="contenido-tabla">
        <thead>
            <tr>
                <th class="firma-izq">LA ACREEDORA</th>
                <th class="firma-der">EL DEUDOR</th>
            </tr>
        </thead>
        <body>
            
            <tr>
                <th><br><br><br>
                    <div class="linea-firma"></div>
                    <div style="text-align: center; font-size: 10pt;">{{ $nombreEmpresa ?? 'CREDIPRENDAS' }}</div>
                    <div style="text-align: center; font-size: 9pt;">Nombre y firma</div>
                </th>

                <td><br><br><br>
                    <div class="linea-firma"></div>
                    <div style="text-align: center; font-size: 10pt;">{{ $clienteNombre }}</div>
                    <div style="text-align: center; font-size: 9pt;">{{ $clienteRol }}</div>
                </td>
            </tr>            
        </body>

        <thead>
            <tr>
                <th class="firma-izq">TESTIGO / RESPONSABLE</th>
                <th class="firma-der">RECEPCIÓN DEL BIEN</th>
            </tr>
        </thead>
        <body>
            
            <tr>
                <th><br><br><br><br>
                    <div class="linea-firma"></div>
                    <div style="text-align: center; font-size: 9pt;">Nombre y firma</div>
                </th>

                <td><br><br><br><br>
                    <div class="linea-firma"></div>
                    <div style="text-align: center; font-size: 9pt;">Nombre y firma</div>
                </td>
            </tr>
        </body>
    </table>

    <div class="pdf-footer">
        Contrato de Crédito con Garantía Prendaria - 
        <span class="pagina"></span>
    </div>
</body>
</html>