<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contrato de Compraventa - {{ $compra->codigo_compra }}</title>
    <style>
        @page {
            size: letter;
            margin: 2.5cm 1.5cm 2cm 3.5cm;
        }
        @page :left {
            margin: 2.5cm 3.5cm 2cm 1.5cm;
        }
        @page :right {
            margin: 2.5cm 1.5cm 2cm 3.5cm;
        }

        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 2;
            color: #000;
            text-align: justify;
        }

        header {
            border-bottom: 1.5px solid #000;
            text-align: center;
            padding-bottom: 5px;
            margin-bottom: 12px;
            line-height: 1.2;
        }

        .hdr-name {
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .hdr-detail {
            font-size: 10px;
        }

        .contract-body {
            margin: 0;
            padding: 0;
            text-align: justify;
            text-indent: 0;
            line-height: 2;
        }

        .signatures-table {
            width: 100%;
            margin-top: 40px;
            border-collapse: collapse;
            page-break-inside: avoid;
            line-height: 1.2;
        }

        .signatures-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding-top: 5px;
        }

        .signature-prefix {
            font-size: 11px;
            margin-bottom: 2px;
        }

        .signature-line {
            border-top: 1px solid #000;
            width: 75%;
            margin: 0 auto 8px auto;
        }

        .signature-name {
            font-weight: bold;
            font-size: 11px;
            text-transform: uppercase;
        }

        .huella-box {
            font-size: 9px;
            color: #555;
            margin-top: 3px;
        }
    </style>
</head>
<body>

    <header>
        <table width="100%" style="border-collapse: collapse;">
            <tr>
                <td width="25%" style="text-align: left; vertical-align: middle;">
                    <img src="data:image/png;base64,{{ base64_encode(file_get_contents(resource_path('logos/avanza_logo.png'))) }}" alt="Logo" style="height: 64px;">
                </td>
                <td width="50%" style="text-align: center; vertical-align: middle;">
                    <div class="hdr-name">COMERCIALIZADORA SUPREMA, SOCIEDAD ANÓNIMA</div>
                    @if(!empty($sucursal->nombre))<div class="hdr-detail">{{ $sucursal->nombre }}</div>@endif
                    @if(!empty($sucursal->direccion))<div class="hdr-detail">{{ $sucursal->direccion }}</div>@endif
                    <div class="hdr-detail">
                        @if(!empty($sucursal->ciudad) || !empty($sucursal->departamento))
                            {{ $sucursal->ciudad ?? '' }}@if(!empty($sucursal->ciudad) && !empty($sucursal->departamento)), @endif{{ $sucursal->departamento ?? '' }}
                        @endif
                        @if(!empty($sucursal->telefono)) &nbsp;|&nbsp; Tel.: {{ $sucursal->telefono }}@endif
                        @if(!empty($sucursal->nit)) &nbsp;|&nbsp; NIT: {{ $sucursal->nit }}@endif
                    </div>
                </td>
                <td width="25%" style="text-align: right; vertical-align: middle; font-size: 9px;">
                    No. {{ $compra->codigo_compra }}
                </td>
            </tr>
        </table>
    </header>

    <p class="contract-body">
        En la ciudad de <strong>{{ $sucursal->ciudad ?? '___________________' }}</strong>, departamento de <strong>{{ $sucursal->departamento ?? '_________________' }}</strong>, el <strong>{{ $dia }}</strong> del <strong>{{ $mes }}</strong> del año <strong>{{ $anio }}</strong> comparecemos nosotros: <strong>CARLOS ADRIAN MADEROS GARCIA</strong>, de veintisiete años de edad, soltero, Administrador, guatemalteco, de este domicilio, quien me identifico con el Documento Personal de Identificación (DPI), Código Único de Identificación (CUI) número: dos mil novecientos noventa y cuatro espacio cuarenta y cinco mil seiscientos cincuenta y ocho espacio cero ciento uno (2994 45658 0101), extendido por el Registro Nacional de las Personas de la República de Guatemala, comparezco como Administrador Único y Representante Legal de la entidad <strong>COMERCIALIZADORA SUPREMA, SOCIEDAD ANÓNIMA</strong>, calidad que acredito con el Acta Notarial de Nombramiento de Administrador Único y Representante Legal, autorizada en la ciudad de Esquipulas, departamento de Chiquimula, el diez de octubre del año dos mil veinticinco, por el Notario FREDY OSVALDO OROZCO NOVA, la cual se encuentra inscrita en el Registro Mercantil General de la República con el número de registro ochocientos diez mil trescientos sesenta y seis (810366.), folio sesenta y dos (62) del libro ochocientos cincuenta y tres (853), de Auxiliares de Comercio; llamada en lo sucesivo <strong>“LA COMPRADORA”</strong> y por la otra parte: El señor(a) <strong>{{ strtoupper($compra->cliente_nombre) }}</strong>, de <strong>{{ $edad }}</strong> años de edad, estado civil <strong>{{ $estadoCivil }}</strong>, de profesión/oficio <strong>{{ $profesion }}</strong>, con domicilio en <strong>{{ $cliente->direccion ?? '____________________' }}</strong>, quien me identifico con el Documento Personal de Identificación (DPI), Código Único de Identificación (CUI) número: <strong>{{ $dpiLetras }}</strong> (<strong>{{ $dpiFormateado }}</strong>), extendido por el Registro Nacional de las Personas de la República de Guatemala. Los comparecientes aseguramos ser de los datos de identificación anteriormente consignados, que nos encontramos en el libre ejercicio de nuestros derechos civiles y que por el presente acto celebramos <strong>CONTRATO DE COMPRAVENTA DE BIEN MUEBLE EN DOCUMENTO PRIVADO</strong>, de conformidad con las cláusulas siguientes: <strong>PRIMERA: OBJETO (DESCRIPCIÓN DEL BIEN).</strong> EL VENDEDOR vende y trasfiere de forma definitiva a LA COMPRADORA el siguiente bien mueble: TIPO DE BIEN: <strong>{{ $compra->categoria_nombre }}</strong>; MARCA / FABRICANTE: <strong>{{ $compra->marca ?? '______________________' }}</strong>; MODELO / ESTILO: <strong>{{ $compra->modelo ?? '______________________' }}</strong>; IDENTIFICACIÓN: (Serie, número de motor, kilataje, peso en gramos, color, dimensiones o cualquier seña particular): <strong>{{ $identificacion ?: '__________________________________________________' }}</strong>; ESTADO FÍSICO: (Indicar si tiene rayones, golpes o si está nuevo/usado): <strong>{{ $estadoFisico }}</strong>; <strong>SEGUNDA: PRECIO.</strong> El precio de la venta es de <strong>Q {{ number_format($compra->monto_pagado, 2) }}</strong> (<strong>{{ $montoCompleto }}</strong>), los cuales EL VENDEDOR recibe en este acto a su entera satisfacción. <strong>TERCERA: DECLARACIÓN JURADA Y ORIGEN.</strong> EL VENDEDOR declara bajo juramento, con pleno conocimiento de las responsabilidades penales en caso de falsedad (Art. 459 Código Penal), que: a) Es el único y legítimo propietario del bien descrito; b) El bien no tiene reporte de robo, hurto, ni es objeto de investigación judicial; c) El bien no tiene gravámenes, embargos ni limitaciones para su venta. <strong>CUARTA: SANEAMIENTO Y RESPONSABILIDAD.</strong> EL VENDEDOR queda obligado al saneamiento de ley por evicción o vicios ocultos. Asimismo, acepta que, si el bien resultara ser de procedencia ilícita, asumirá toda la responsabilidad legal ante el Ministerio Público o cualquier autoridad competente, liberando a LA COMPRADORA de todo perjuicio. <strong>QUINTA: ACEPTACIÓN.</strong> Las partes aceptan el contrato y firman de conformidad.
    </p>

    <table class="signatures-table">
        <tr>
            <td>
                <div class="signature-line"></div>
                <div class="signature-name">LA COMPRADORA</div>
            </td>
            <td>
                <div class="signature-line"></div>
                <div class="signature-name">EL VENDEDOR</div>
                <div class="huella-box">(Huella dactilar del vendedor)</div>
            </td>
        </tr>
    </table>

</body>
</html>
