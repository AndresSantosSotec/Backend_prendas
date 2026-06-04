<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contrato de Compraventa - {{ $compra->codigo_compra }}</title>
    <style>
        @page {
            size: letter;
            margin-top: 7.8cm;
            margin-bottom: 4.7cm;
        }
        @page :left {
            margin-left: 1.5cm;
            margin-right: 3.5cm;
        }
        @page :right {
            margin-left: 3.5cm;
            margin-right: 1.5cm;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            font-size: 11px;
            line-height: 1.35;
            color: #000;
            text-align: justify;
        }
        
        .title {
            text-align: center;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 13px;
            margin-bottom: 15px;
        }
        
        .clause-title {
            font-weight: bold;
        }
        
        .contract-paragraph {
            margin-bottom: 12px;
            text-indent: 1.5cm;
        }
        
        .field-label {
            font-weight: bold;
        }
        
        .signatures-table {
            width: 100%;
            margin-top: 50px;
            border-collapse: collapse;
            page-break-inside: avoid;
        }
        
        .signatures-table td {
            width: 50%;
            text-align: center;
            vertical-align: top;
        }
        
        .signature-line {
            border-top: 1px solid #000;
            width: 70%;
            margin: 0 auto 5px auto;
        }
        
        .signature-name {
            font-weight: bold;
            font-size: 11px;
        }
        
        .signature-role {
            font-size: 10px;
        }
        
        .huella-box {
            font-size: 9px;
            color: #555;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="title">CONTRATO DE COMPRAVENTA DE BIEN MUEBLE EN DOCUMENTO PRIVADO</div>
    
    <div class="contract-paragraph">
        En la ciudad de <strong>{{ $sucursal->ciudad ?? '___________________' }}</strong>, departamento de <strong>{{ $sucursal->departamento ?? '_________________' }}</strong>, el <strong>{{ $dia }}</strong> del <strong>{{ $mes }}</strong> del año <strong>{{ $anio }}</strong> comparecemos nosotros: <strong>CARLOS ADRIAN MADEROS GARCIA</strong>, de veintisiete años de edad, soltero, Administrador, guatemalteco, de este domicilio, quien me identifico con el Documento Personal de Identificación (DPI), Código Único de Identificación (CUI) número: dos mil novecientos noventa y cuatro espacio cuarenta y cinco mil seiscientos cincuenta y ocho espacio cero ciento uno (2994 45658 0101), extendido por el Registro Nacional de las Personas de la República de Guatemala, comparezco como Administrador Único y Representante Legal de la entidad <strong>COMERCIALIZADORA SUPREMA, SOCIEDAD ANÓNIMA</strong>, calidad que acredito con el Acta Notarial de Nombramiento de Administrador Único y Representante Legal, autorizada en la ciudad de Esquipulas, departamento de Chiquimula, el diez de octubre del año dos mil veinticinco, por el Notario FREDY OSVALDO OROZCO NOVA, la cual se encuentra inscrita en el Registro Mercantil General de la República con el número de registro ochocientos diez mil trescientos sesenta y seis (810366.), folio sesenta y dos (62) del libro ochocientos cincuenta y tres (853), de Auxiliares de Comercio; llamada en lo sucesivo <strong>“LA COMPRADORA”</strong> y por la otra parte: El señor(a) <strong>{{ strtoupper($compra->cliente_nombre) }}</strong>, de <strong>{{ $edad }}</strong> años de edad (<strong>{{ $edadLetras }}</strong> años), estado civil <strong>{{ $estadoCivil }}</strong>, de profesión/oficio <strong>{{ $profesion }}</strong>, con domicilio en <strong>{{ $cliente->direccion ?? '____________________' }}</strong>, quien me identifico con el Documento Personal de Identificación (DPI), Código Único de Identificación (CUI) número: <strong>{{ $dpiLetras }}</strong> (<strong>{{ $dpiFormateado }}</strong>), extendido por el Registro Nacional de las Personas de la República de Guatemala. Los comparecientes aseguramos ser de los datos de identificación anteriormente consignados, que nos encontramos en el libre ejercicio de nuestros derechos civiles y que por el presente acto celebramos <strong>CONTRATO DE COMPRAVENTA DE BIEN MUEBLE EN DOCUMENTO PRIVADO</strong>, de conformidad con las cláusulas siguientes:
    </div>
    
    <div class="contract-paragraph">
        <span class="clause-title">PRIMERA: OBJETO (DESCRIPCIÓN DEL BIEN).</span> EL VENDEDOR vende y trasfiere de forma definitiva a LA COMPRADORA el siguiente bien mueble:
        <br />
        <span class="field-label">TIPO DE BIEN:</span> <u>{{ $compra->categoria_nombre }}</u>;
        <br />
        <span class="field-label">MARCA / FABRICANTE:</span> <u>{{ $compra->marca ?? 'N/A' }}</u>;
        <br />
        <span class="field-label">MODELO / ESTILO:</span> <u>{{ $compra->modelo ?? 'N/A' }}</u>;
        <br />
        <span class="field-label">IDENTIFICACIÓN:</span> <u>{{ $identificacion }}</u>;
        <br />
        <span class="field-label">ESTADO FÍSICO:</span> <u>{{ $estadoFisico }}</u>;
    </div>
    
    <div class="contract-paragraph">
        <span class="clause-title">SEGUNDA: PRECIO.</span> El precio de la venta es de <strong>Q {{ number_format($compra->monto_pagado, 2) }}</strong> (<strong>{{ $montoCompleto }}</strong>), los cuales EL VENDEDOR recibe en este acto a su entera satisfacción.
    </div>
    
    <div class="contract-paragraph">
        <span class="clause-title">TERCERA: DECLARACIÓN JURADA Y ORIGEN.</span> EL VENDEDOR declara bajo juramento, con pleno conocimiento de las responsabilidades penales en caso de falsedad (Art. 459 Código Penal), que: a) Es el único y legítimo propietario del bien descrito; b) El bien no tiene reporte de robo, hurto, ni es objeto de investigación judicial; c) El bien no tiene gravámenes, embargos ni limitaciones para su venta.
    </div>
    
    <div class="contract-paragraph">
        <span class="clause-title">CUARTA: SANEAMIENTO Y RESPONSABILIDAD.</span> EL VENDEDOR queda obligado al saneamiento de ley por evicción o vicios ocultos. Asimismo, acepta que, si el bien resultara ser de procedencia ilícita, asumirá toda la responsabilidad legal ante el Ministerio Público o cualquier autoridad competente, liberando a LA COMPRADORA de todo perjuicio.
    </div>
    
    <div class="contract-paragraph">
        <span class="clause-title">QUINTA: ACEPTACIÓN.</span> Las partes aceptan el contrato y firman de conformidad.
    </div>
    
    <table class="signatures-table">
        <tr>
            <td>
                <div class="signature-line"></div>
                <div class="signature-name">LA COMPRADORA</div>
                <div class="signature-role">Representante Legal</div>
                <div class="signature-role">COMERCIALIZADORA SUPREMA, S.A.</div>
            </td>
            <td>
                <div class="signature-line"></div>
                <div class="signature-name">EL VENDEDOR</div>
                <div class="signature-role">{{ strtoupper($compra->cliente_nombre) }}</div>
                <div class="huella-box">(Huella dactilar del vendedor)</div>
            </td>
        </tr>
    </table>
</body>
</html>
