<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $empresa['nombre'] ?? 'PRENDAMAS' }} - Contrato {{ $credito->codigo_credito ?? $credito->numero_credito ?? 'S/N' }}</title>
    <style>
        @page {
            size: letter;
            margin-top: 2.22cm;
            margin-right: 1.27cm;
            margin-bottom: 0.49cm;
            margin-left: 0.63cm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial MT', 'Arial', Helvetica, sans-serif;
            font-size: 10pt;
            line-height: 1.35;
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
        p {
            margin: 0 0 10px 0;
            text-align: justify;
            text-indent: 0;
        }
        .negrita {
            font-weight: bold;
        }
        .mayusculas {
            text-transform: uppercase;
        }
        .firmas-tabla {
            width: 100%;
            border-collapse: collapse;
            margin-top: 35px;
            margin-bottom: 30px;
            page-break-inside: avoid;
        }
        .firma-col {
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding: 0 15px;
        }
        .linea-firma {
            border-top: 1.5px solid #000;
            width: 85%;
            margin: 0 auto 5px auto;
        }
        .huella-box {
            display: inline-block;
            width: 55px;
            height: 70px;
            border: 1px dashed #777;
            margin-top: 6px;
            font-size: 8pt;
            color: #666;
            line-height: 70px;
            text-align: center;
        }
        .autentica-seccion {
            border-top: 1.5px solid #000;
            margin-top: 25px;
            padding-top: 15px;
            page-break-inside: avoid;
        }
        .ante-mi {
            margin-top: 45px;
            text-align: center;
            page-break-inside: avoid;
        }
    </style>
</head>
<body>

@php
    use App\Support\NumeroALetrasHelper;
    use Carbon\Carbon;

    // Entidad
    $nombreEmpresa = $empresa['nombre'] ?? 'PRENDAMAS';
    $razonSocial = $empresa['razon_social'] ?? 'GRUPO JUMERC, SOCIEDAD ANÓNIMA';

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
        $clienteNombre = 'FELISA JOSEFINA GUTIERREZ CHUC DE PONCIO';
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
    $destinoCredito = !empty($credito->destino) ? strtoupper($credito->destino) : 'INVERSION DE NEGOCIOS';

    // Plazo y vencimiento
    $plazoDias = (int) ($credito->plazo_dias ?? 30);
    $plazoTexto = ($plazoDias >= 28 && $plazoDias <= 31) ? 'UN MES' : strtoupper(NumeroALetrasHelper::convertir($plazoDias)) . ' DÍAS';

    $fechaVencObj = !empty($credito->fecha_vencimiento) 
        ? Carbon::parse($credito->fecha_vencimiento) 
        : $fechaContratoObj->copy()->addDays($plazoDias);
    $fechaVencLetras = strtoupper(NumeroALetrasHelper::fechaEnLetras($fechaVencObj));

    // Tasa de interés
    $tasaInteres = (float) ($credito->tasa_interes ?? 13.44);
    $tasaEntero = (int) floor($tasaInteres);
    $tasaDecimal = (int) round(($tasaInteres - $tasaEntero) * 100);
    $tasaLetras = strtoupper(NumeroALetrasHelper::convertir($tasaEntero)) . ($tasaDecimal > 0 ? ' PUNTO ' . strtoupper(NumeroALetrasHelper::convertir($tasaDecimal)) : '') . ' POR CIENTO';

    // Prendas / Garantías
    $listaPrendas = collect($prendas ?? $credito->prendas ?? []);
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

    <p>
        En el municipio y departamento de Quetzaltenango, el día <strong>{{ $fechaContratoLetras }}</strong>; 
        <strong>NOSOTROS:</strong> <strong>{{ $representanteNombre }}</strong> de {{ $representanteEdad }}, {{ $representanteEstadoCivil }}, 
        {{ $representanteNacionalidad }}, {{ $representanteProfesion }}, con domicilio en el {{ $representanteDomicilio }}, 
        me identifico con Documento Personal de Identificación con Código Único de Identificación número: 
        <strong>{{ $representanteDpiLetras }}</strong>, extendido por el Registro Nacional de las Personas de la República de Guatemala, 
        actúo en mi calidad de <strong>{{ $representanteTitulo }} DE LA ENTIDAD DENOMINADA {{ $razonSocial }}</strong>, 
        calidad que acredito con el nombramiento contenido en Acta Notarial autorizada el municipio de Quetzaltenango, del departamento de Quetzaltenango 
        el día {{ $representanteActaFecha }} por el Notario {{ $representanteActaNotario }}, la cual quedó inscrita en el Registro Mercantil 
        al número {{ $representanteRegMercantil }}, folio {{ $representanteFolio }} del libro {{ $representanteLibro }} de Auxiliares de Comercio. 
        Representación que conforme a la ley es suficiente para la celebración de este acto; y Yo, <strong>{{ $clienteNombre }}</strong>, 
        de {{ $clienteEdad }}, {{ $clienteEstadoCivil }}, {{ $clienteNacionalidad }}, {{ $clienteProfesion }}, con domicilio y residencia en 
        {{ $clienteDireccion }}, me identifico con el Documento Personal de Identificación con Código Único de Identificación número: 
        <strong>{{ $clienteDpiLetras }}</strong> extendido por el Registro Nacional de las Personas de la República de Guatemala. 
        A quien en el transcurso del presente documento se me podrá denominar como <strong>{{ $clienteRol }}</strong>. 
        Manifestamos ser de los datos anteriores, hallarnos en el libre ejercicio de nuestros derechos civiles y por este acto otorgamos 
        CONTRATO DE MUTUO CON GARANTÍA PRENDARIA, de conformidad a lo siguiente:
    </p>

    <p>
        <strong>PRIMERO:</strong> Yo, <strong>{{ $representanteNombre }}</strong>, en la calidad con que actúo, manifiesto que por este acto se autoriza el préstamo solicitado por {{ $clienteTratamiento }}: <strong>{{ $clienteNombre }}</strong> en calidad de Mutuo con Garantía Prendaria, como se detalla más adelante.
    </p>

    <p>
        <strong>SEGUNDA:</strong> Por mi parte Yo, <strong>{{ $clienteNombre }}</strong>, manifiesto que por este acto me reconozco <strong>LISA Y LLANA DEUDORA</strong> de la entidad representada por el señor <strong>{{ $representanteNombre }}</strong>, por la cantidad de <strong>{{ $montoLetras }}</strong> misma que será destinada para <strong>{{ $destinoCredito }}</strong> y que dicha suma la cancelaré de conformidad con las siguientes estipulaciones:
    </p>

    <p>
        <strong>A) DEL PLAZO:</strong> El plazo mediante el cual cancelaré el capital e intereses adeudados tendrá un plazo máximo para su cumplimiento de <strong>{{ $plazoTexto }}</strong> contado a partir de la presente fecha de la celebración del presente contrato por lo que vencerá el día <strong>{{ $fechaVencLetras }}</strong> fecha en la cual habré cancelado totalmente la cantidad adeudada a la Acreedora.
    </p>

    <p>
        <strong>B) DE LA FORMA DE PAGO:</strong> El capital adeudado lo cancelaré al vencimiento del plazo del presente contrato, haciéndose efectivo dicho pago el día <strong>{{ $fechaVencLetras }}</strong>.
    </p>

    <p>
        <strong>C) DE LOS INTERESES REMUNERATORIOS Y MORATORIOS:</strong> Los intereses serán pagados al vencimiento del plazo del presente contrato, haciéndose efectivo dicho pago el día <strong>{{ $fechaVencLetras }}</strong>. Serán calculados a razón del <strong>{{ $tasaLetras }} ({{ number_format($tasaInteres, 2) }}%) MENSUAL</strong>, tasa moratoria que se aplicará en caso yo, <strong>{{ $clienteNombre }}</strong> cancele la cuota de intereses el siguiente día al de la fecha pactada en el presente documento privado.
    </p>

    <p>
        <strong>D) LUGAR DE PAGO:</strong> Yo, <strong>{{ $representanteNombre }}</strong>, en la calidad con que actúo, y, Yo, <strong>{{ $clienteNombre }}</strong>, acordamos de manera expresa que todo pago tanto de capital como de intereses, serán realizados en la dirección de las oficinas centrales de la entidad representada por el señor <strong>{{ $representanteNombre }}</strong>, las cuales yo, <strong>{{ $clienteNombre }}</strong> manifiesto que conozco perfectamente y que cada amortización la realizaré sin necesidad de cobro ni requerimiento alguno.
    </p>

    <p>
        <strong>E) DEPÓSITO:</strong> Ambas partes convenimos en que, durante la vigencia de este contrato la entidad <strong>{{ $razonSocial }}</strong>, sea depositaria del bien dado en prenda, de conformidad con las disposiciones legales correspondientes. Asimismo, {{ $clienteTratamiento }}: <strong>{{ $clienteNombre }}</strong> manifiesta de forma expresa su consentimiento para que el bien dado en garantía sea depositado, indistintamente en cualquiera de las agencias de la acreedora, y autoriza expresamente el traslado de dicho bien según lo estime necesario la entidad <strong>{{ $razonSocial }}</strong>.
    </p>

    <p>
        <strong>TERCERO: DE LA GARANTÍA:</strong> Continuo manifestando yo, <strong>{{ $clienteNombre }}</strong>, de forma expresa que en garantía del pago del capital, intereses, costas judiciales, gastos de cobranza y demás obligaciones exigibles conforme a este documento privado y la ley, respondo con mis bienes presentes y futuros susceptibles de embargo sin orden de prelación, y enajenables al momento de exigir el cumplimiento de esta obligación, y especialmente con el bien mueble identificado como 
        @if($listaPrendas->isNotEmpty())
            @foreach($listaPrendas as $p)
                <strong>{{ $p->descripcion ?? $p->descripcion_general ?? 'Bien mueble prendario' }}</strong>
                @if(!empty($p->peso_neto) || !empty($p->peso_bruto))
                    , CON PESO DE {{ $p->peso_neto ?? $p->peso_bruto }} gramos
                @endif
                @if(!empty($p->kilataje))
                    de {{ $p->kilataje }} kilates de oro
                @endif
                @if(!empty($p->peso_piedra))
                    y peso de la piedra: {{ $p->peso_piedra }} gramos
                @endif
                @if(!empty($p->valor_tasacion) || !empty($p->monto_avaluo))
                    (Valor de tasación: Q.{{ number_format($p->valor_tasacion ?? $p->monto_avaluo, 2) }})
                @endif
                {{ !$loop->last ? '; ' : '' }}
            @endforeach
        @else
            <strong>Par de Aretes con forma de Flor con piedras rojas, CON PESO DE tres punto nueve (3.9) gramos de diez kilates de oro cada uno y peso de la piedra: Cero punto diez gramos (0.10 Grms)</strong>
        @endif, incluyendo todo cuanto de hecho y por derecho le correspondan al referido bien, declarando expresamente yo, <strong>{{ $clienteNombre }}</strong> que el bien dado en garantía se encuentra libre de gravámenes, anotaciones y/o reclamaciones tanto administrativas y/o judiciales que pudieran afectar los derechos de terceras personas y especialmente los de la entidad que representa el señor <strong>{{ $representanteNombre }}</strong>, y en todo caso me someto al saneamiento de ley respectivo, y a la vez manifiesto de forma expresa que renuncio al fuero de mi domicilio y me someto a la competencia de los órganos jurisdiccionales que la entidad representada por el señor <strong>{{ $representanteNombre }}</strong> elija señalado como lugar para recibir notificaciones y citaciones la dirección de mi residencia consignada en el presente documento privado, aceptando como buenas y exactas las cuentas que se me formulen sobre este contrato y como líquido, ejecutivo, exigible, y de plazo vencido el saldo que se me reclame, siendo por mi cuenta los gastos que judicial y extrajudicialmente se causen por el incumplimiento de este contrato, y que en caso de ser necesario promover el proceso de ejecución correspondiente por existir incumplimiento por parte del deudor, ambos comparecientes en la calidad con que actuamos acordamos de manera expresa que el bien mueble dado en garantía se adjudiquen en pago en favor de la entidad representada por el señor <strong>{{ $representanteNombre }}</strong> en caso de incumplimiento y además del bien pignorado, en caso de insuficiencia, dejo afectos mis ingresos salariales y cuentas bancarias y que la modalidad de este contrato sea de <strong>PRENDA CON DESPLAZAMIENTO</strong>.
    </p>

    <p>
        <strong>CUARTA:</strong> Por mi parte yo, el señor <strong>{{ $representanteNombre }}</strong>, en la calidad con que actúo manifiesto que <strong>ACEPTO EXPRESAMENTE</strong> el bien mueble dejado en garantía en favor de mi representada.
    </p>

    <p>
        <strong>QUINTA:</strong> Ambos otorgantes en la calidad con que actuamos manifestamos que aceptamos el contenido íntegro del presente documento privado el cual procedimos a dar lectura integra en el que bien impuestos de su contenido, validez y demás efectos legales, manifestamos que lo aceptamos, ratificamos y firmamos.
    </p>

    {{-- TABLA DE FIRMAS --}}
    <table class="firmas-tabla">
        <tr>
            <td class="firma-col">
                <div class="linea-firma"></div>
                <div class="negrita mayusculas">{{ $representanteNombre }}</div>
                <div style="font-size: 9pt;">{{ $representanteTitulo }}</div>
                <div style="font-size: 8.5pt; color: #444;">{{ $razonSocial }}</div>
            </td>
            <td class="firma-col">
                <div class="linea-firma"></div>
                <div class="negrita mayusculas">{{ $clienteNombre }}</div>
                <div style="font-size: 9pt;">{{ $clienteRol }}</div>
                <div style="font-size: 8.5pt; color: #444;">DPI: {{ $clienteDpiRaw }}</div>
                <div><span class="huella-box">HUELLA</span></div>
            </td>
        </tr>
    </table>

    {{-- AUTÉNTICA NOTARIAL --}}
    <div class="autentica-seccion">
        <p>
            <strong>AUTÉNTICA:</strong> En el municipio de Quetzaltenango departamento de Quetzaltenango el día <strong>{{ $fechaContratoLetras }}</strong>, como Notario <strong>DOY FÉ</strong>, que las firmas que anteceden son auténticas por haber sido puestas en mi presencia el día de hoy por el señor <strong>{{ $representanteNombre }}</strong>, quien se identifica con el Documento Personal de Identificación con Código Único de Identificación número: <strong>{{ $representanteDpiLetras }}</strong>, extendido por el Registro Nacional de las Personas de la República de Guatemala, actúo en mi calidad de <strong>{{ $representanteTitulo }} DE LA ENTIDAD DENOMINADA {{ $razonSocial }}</strong>, calidad que acredita con el nombramiento contenido en Acta Notarial autorizada el municipio de Quetzaltenango, del departamento de Quetzaltenango el día {{ $representanteActaFecha }} por el Notario {{ $representanteActaNotario }}, la cual quedó inscrita en el Registro Mercantil al número {{ $representanteRegMercantil }}, folio {{ $representanteFolio }} del libro {{ $representanteLibro }} de Auxiliares de Comercio; y por {{ $clienteTratamiento }} <strong>{{ $clienteNombre }}</strong>, quien se identifica con el Documento Personal de Identificación con Código Único de Identificación número <strong>{{ $clienteDpiLetras }}</strong>, extendido por el Registro Nacional de las Personas de la República de Guatemala, quienes de conformidad con lo actuado lo aceptan, ratifican y vuelven a firmar, firmando a continuación yo el Notario autorizante que de todo lo relacionado <strong>DOY FE</strong>.
        </p>

        {{-- FIRMAS TRAS LA AUTÉNTICA --}}
        <table class="firmas-tabla" style="margin-top: 25px; margin-bottom: 20px;">
            <tr>
                <td class="firma-col">
                    <div class="linea-firma"></div>
                    <div class="negrita mayusculas">{{ $representanteNombre }}</div>
                </td>
                <td class="firma-col">
                    <div class="linea-firma"></div>
                    <div class="negrita mayusculas">{{ $clienteNombre }}</div>
                </td>
            </tr>
        </table>

        <div class="ante-mi">
            <div style="font-weight: bold; margin-bottom: 35px;">ANTE MÍ:</div>
            <div class="linea-firma" style="width: 50%;"></div>
            <div style="font-size: 10pt; font-weight: bold;">ABOGADO Y NOTARIO</div>
            <div style="font-size: 8.5pt; color: #555;">(SELLO Y TIMBRES NOTARIALES)</div>
        </div>
    </div>

</body>
</html>
