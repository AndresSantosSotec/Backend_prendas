<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $empresa['nombre'] ?? 'PRENDAMAS' }} - Contrato {{ $credito->codigo_credito ?? $credito->numero_credito ?? 'S/N' }}</title>
    <style>
        @page {
            size: carta;
            margin-top: 1cm;
            margin-right: 1.27cm;
            margin-bottom: 0.49cm;
            margin-left: 3cm;
        }
        @page :left {
            margin-top: 1cm;
            margin-right: 3cm;
            margin-bottom: 0.49cm;
            margin-left: 1.27cm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: 'Arial MT', 'Arial', Helvetica, sans-serif;
            font-size: 8pt;
            line-height: 1.35;
            color: #000;
            text-align: justify;
            margin: 0;
            padding: 0;
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
        .page-break {
            page-break-after: always;
            clear: both;
        }
        .anverso-marco {
            border: 1.5px solid #222;
            padding: 8px 9px;
            width: 100%;
            font-size: 8pt;
            line-height: 1.25;
            text-align: left;
        }
        .anverso-titulo {
            text-align: center;
            font-weight: bold;
            font-size: 9pt;
            padding: 2px 0 6px;
            border-bottom: 1px solid #444;
        }
        .anverso-numero { text-align: center; font-weight: bold; padding: 7px 0; }
        .anverso-datos { width: 100%; border-collapse: collapse; table-layout: fixed; }
        .anverso-datos td { padding: 4px 5px; vertical-align: top; }
        .anverso-datos .etiqueta { width: 22%; font-size: 7pt; font-weight: bold; text-decoration: underline; white-space: nowrap; }
        .anverso-datos .valor { width: 28%; border-bottom: 1px solid #777; }
        .anverso-datos .separador { border-left: 1px solid #555; }
        .anverso-garantia { border-top: 1px solid #444; margin-top: 5px; padding-top: 7px; }
        .anverso-subtitulo { font-weight: bold; font-size: 8pt; margin-bottom: 5px; }
        .anverso-prendas { width: 100%; border-collapse: collapse; table-layout: fixed; font-size: 7.5pt; }
        .anverso-prendas th, .anverso-prendas td { border: 1px solid #333; padding: 4px 5px; text-align: left; }
        .anverso-prendas th { font-weight: bold; font-size: 7pt; }
        .anverso-pie { display: table; width: 100%; border-top: 1px solid #444; margin-top: 6px; padding-top: 8px; font-size: 7pt; }
        .anverso-pie > div { display: table-cell; width: 50%; vertical-align: top; }
        .anverso-pie .derecha { text-align: right; }
        
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

    $generoRaw = strtolower(trim($cliente->genero ?? ''));
    if (in_array($generoRaw, ['femenino', 'f', 'mujer', 'femenina'])) {
        $esFemenino = true;
    } elseif (in_array($generoRaw, ['masculino', 'm', 'hombre', 'varon'])) {
        $esFemenino = false;
    } elseif (!empty($cliente->estado_civil) && str_ends_with(strtolower(trim($cliente->estado_civil)), 'a')) {
        $esFemenino = true;
    } elseif (!empty($cliente->estado_civil) && str_ends_with(strtolower(trim($cliente->estado_civil)), 'o')) {
        $esFemenino = false;
    } else {
        $esFemenino = false;
    }

    $clienteTratamiento = $esFemenino ? 'la señora' : 'el señor';
    $clienteRol = $esFemenino ? 'LA DEUDORA' : 'EL DEUDOR';
    $clienteEstadoCivil = !empty($cliente->estado_civil) ? strtolower(trim($cliente->estado_civil)) : ($esFemenino ? 'casada' : 'casado');
    $clienteNacionalidad = !empty($cliente->nacionalidad) ? strtolower(trim($cliente->nacionalidad)) : ($esFemenino ? 'guatemalteca' : 'guatemalteco');
    $clienteProfesion = !empty($cliente->profesion) ? strtolower(trim($cliente->profesion)) : 'comerciante';

    // Edad del cliente en letras (con fallback a mayor de edad para evitar 'cero años de edad')
    $aniosCliente = 0;
    if (!empty($cliente->fecha_nacimiento) && $cliente->fecha_nacimiento !== '0000-00-00') {
        try {
            $parsedAge = Carbon::parse($cliente->fecha_nacimiento)->age;
            if ($parsedAge > 0 && $parsedAge < 120) {
                $aniosCliente = $parsedAge;
            }
        } catch (\Throwable $e) {}
    }
    if ($aniosCliente <= 0 && !empty($cliente->edad) && (int)$cliente->edad > 0) {
        $aniosCliente = (int) $cliente->edad;
    }

    $clienteEdadTexto = ($aniosCliente > 0) 
        ? 'de ' . NumeroALetrasHelper::convertir($aniosCliente) . ' años de edad' 
        : 'mayor de edad';

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
        <table width="100%">
            <tr>
                <td width="70%" align="center">
                    <div style="font-size: 13pt; font-weight: bold; color: #1e3a8a;">{{ $nombreEmpresa }}</div>
                    <div style="font-size: 9pt; color: #333;">{{ $razonSocial }}</div>
                    <div style="font-size: 8.5pt; color: #555;">{{ $sucursal->direccion ?? 'Oficinas Centrales' }} | Tel: {{ $sucursal->telefono ?? $empresa['telefono'] ?? 'PBX' }}</div>
                    <div class="linea-firma" style="width: 100%;"></div>
                </td>
            </tr>
        </table>
    <p>
        <strong>CONTRATO DE ADHESIÓN DE MUTUO CON GARANTÍA PRENDARIA QUE CELEBRA {{ $nombreEmpresa }} (LA ACREEDORA), Y LA
        PERSONA FÍSICA CUYO NOMBRE APARECE AL ANVERSO DE ESTE DOCUMENTO (DEUDOR PRENDARIO), CONFORME LAS
        DECLARACIONES Y CLÁUSULAS SIGUIENTES:</strong>
    </p>

    <p>
        <strong>DECLARACIONES:</strong>
    </p>

    <p>
        Declara el <strong>MUTUANTE</strong>: (Acreedor Prendario) que su representada legalmente constituida conforme a las leyes de la República de
        Guatemala, según consta en el primer testimonio de la escritura pública número 175 de fecha 10 de octubre de 2025 y su ampliación
        escritura No. 180 de fecha 23 de octubre de 2025, ambas autorizadas en la ciudad de {{ $nombreEmpresa }}, departamento de Chiquimula por el
        Notario Fredy Osvaldo Orozco Nova e inscrita en el Registro Mercantil General de la República bajo el No. 39,329 folio 798 del Libro 27
        electrónicos de Sociedades Mercantiles; b) Que su representada cuenta con las facultades necesarias para la celebración del presente
        contrato de mutuo con garantía prendaria en los artículos 1, 5, 6, 47 y 52 del Decreto 08-2003, Ley de Protección al Consumidor y Usuario, y
        por los artículos 10, 12, 13, 38, 58, 60, 65, 75 y 78 del Decreto Número 51-2007, Ley de Garantías Mobiliarias, y su reglamento.
    </p>

    <p>
        Declara el <strong>MUTUARIO</strong>: (Deudor Prendario) a) Que tiene su domicilio como se describe en el anverso de este documento; b) Enterado de las
        penas relativas al delito de perjuicio manifiesta, que es legítimo propietario de los bienes que se describen en el anverso del presente
        contrato y conforme lo establecido en la cláusula QUINTA de este contrato.
    </p>

    <p>
        Declaran ambas partes: Que es su voluntad celebrar el presente contrato de mutuo con garantía prendaria, conforme a lo dispuesto por el
        artículo 1952 del Código Civil y las siguientes:
    </p>

    <p>
        <strong>CLÁUSULAS</strong>
    </p>

    <p>
        <strong>PRIMERA: OBJETO (PRÉSTAMO A MUTUO).</strong> El deudor por el presente acto se reconoce lino y llano deudor de {{ $nombreEmpresa }} por la
        cantidad y demás condiciones que se detallan en el anverso del presente documento y de conformidad con el presente contrato de mutuo
        mercantil, cantidad que tiene recibida en efectivo y a su entera satisfacción.
    </p>

    <p>
        <strong>SEGUNDA: CONDICIONES.</strong> El deudor se obliga a pagar la cantidad adeudada en la forma, modo, con el interés, que se detalla en el
        anverso, tales tasas serán devengadas inclusive en los casos en que la ACREEDORA retenga la prenda, a la que se refiere este contrato
        por falta de pago del mutuo y sus accesorios. Además, el pago deberá hacerse en efectivo junto con los intereses y almacenaje en el mismo
        establecimiento en que se suscribe este documento dentro del plazo máximo estipulado en el anverso de este documento, plazo que podrá
        prorrogarse por periodos iguales, previo cumplimiento de las demás condiciones establecidas en este instrumento. El deudor podrá realizar
        pagos parciales a cuenta del mutuo, los interés y accesorios a su cargo, los cuales devengaran interés a favor del deudor, a la misma tasa
        del mutuo y se aplicara a amortizar el adeudo, en el orden siguiente: Capital (Mutuo), intereses y accesorios, al momento de establecer las
        condiciones de pago establecidas en el anverso al realizarse la venta directa del bien. En caso de Prorroga, los pagos a cuenta y los
        intereses que devengan se acreditaran al pago de los intereses, y deposito, en el orden ya indicado.
    </p>

    <p>
        <strong>TERCERA: GARANTÍA.</strong> En garantía del capital, intereses, gastos y costas si llegaren a causarse, EL DEUDOR constituye a favor de
        {{ $nombreEmpresa }}, en calidad de PRENDA, el bien mueble usado que se describe en el anverso, en el entendido de que esta entrega del bien
        convierte a la ACREEDORA en propietaria de la prenda.
    </p>

    <p>
        <strong>CUARTA:</strong> VALOR DE LA PRENDA. El valor de la prenda es el que se establece en el anverso, en virtud del avalúo practicado por LA ACREEDORA, con criterio de objetividad y equidad y la entera satisfacción de ambas partes.
    </p>

    <p>
        <strong>QUINTA:</strong> EL DEUDOR PRENDARIO declara expresamente que es único y legítimo propietario de la prenda con el derecho de uso y disfrute
        sobre la misma y que sobre dichos bienes, no existen gravámenes ni limitaciones que puedan perjudicar los derechos de la ACREEDORA,
        obligándose a responder del saneamiento de conformidad con la ley. EL DEUDOR PRENDARIO reconoce como de su propiedad el bien
        descrito en el anverso y declara que el mismo es usado.
    </p>

    <p>
        <strong>SEXTA:</strong> EL DEUDOR PRENDARIO se obliga a no variar la condición jurídica de la prenda, por lo que no podrá enajenar, gravarla ni
        comprometerla en forma alguna.
    </p>

    <p>
        <strong>SÉPTIMA:</strong> Si la ACREEDORA fuere perturbada en la posesión de la prenda por causa imputable al DEUDOR PRENDARIO, avisará por
        escrito a este para que lleve a cabo las acciones legales pertinentes.
    </p>

    <p>
        <strong>OCTAVA:</strong> Si en cumplimiento de una orden dictada por autoridad competente, la ACREEDORA fuera desposeída de la prenda, el DEUDOR
        PRENDARIO le entregara a su entera satisfacción otra prenda equivalente a esta en peso, calidad, condición y valor dentro de los diez días
        hábiles siguientes a la notificación que por escrito efectúe el ACREEDOR.
    </p>

    <p>
        <strong>NOVENA:</strong> En caso de pérdida, robo o destrucción de la prenda por cualquier causa no imputable al DEUDOR PRENDARIO, LA
        ACREEDORA pagará en efectivo al DEUDOR PRENDARIO el valor del avalúo establecido en el anverso de este documento, menos la
        cantidad entregada por concepto de mutuo y los intereses, así como los accesorios señalados en este contrato que haya devengado hasta la
        fecha de pago y conforme a las tasas que se indican en el anverso, en su caso. Si se reintegran al DEUDOR PRENDARIO los pagos a
        cuenta efectuados los intereses devengados.
    </p>

    <p>
        <strong>DÉCIMA:</strong> LA ACREEDORA tendrá a su cargo la guarda y manejo de la prenda en los términos del artículo 1974 del Código Civil y demás
        relacionados; en ningún caso, será responsable de los daños y deterioros que pudieren sufrir las cosas custodiadas por culpa o fuerza
        mayor. Para los efectos de esta cláusula y la anterior, EL DEUDOR PRENDARIO y LA ACREEDORA convienen en que ésta última podrá
        contratar una aseguradora autorizada por autoridad competente, a costa de esta.
    </P>

    <p>
        <strong>DÉCIMA PRIMERA:</strong> Si EL DEUDOR PRENDARIO efectúa el pago íntegro y oportuno de la suma dada en mutuo, los intereses, accesorios y
        demás conceptos que se refieren este contrato, en la forma y plazo convenidos, la ACREEDORA queda facultada por los contratantes, para
        proceder a la venta directa privada, en los términos del artículo 65 de la Ley de Garantías Mobiliarias, sin necesidad de formalismo o
        procedimiento alguno, tomando como referencia el valor del avaluó estipulado en la cláusula cuarta, sirviendo como notificación el aviso de
        intensión de venta que se haga al EL DEUDOR PRENDARIO, quien podrá hacer suspender la enajenación de la prenda, pagando el mutuo,
        los rendimientos del mismo y demás conceptos en este instrumento, previo a la venta programada.
    </P>

    <p>
        <strong>DÉCIMA SEGUNDA:</strong> La ACREEDORA en ningún caso, será responsable de la evicción de objeto vendido.
    </P>

    <p>
        <strong>DÉCIMA TERCERA:</strong> La aplicación del producto de la venta. El DEUDOR PRENDARIO faculta a la acreedora a aplicar el producto de la
        venta de la prenda, al pago del importe del mutuo, de los intereses y accesorios del mismo que se hayan devengado hasta la fecha de la
        venta, así como los porcentajes que se consignan en el anverso por concepto de gastos de operación y de comisión por venta. Si hubiera
        algún remanente, será dispuesto a la disposición de EL DEUDOR PRENDARIO; el remanente no cobrado en un lapso de doce meses
        consecutivos, contados a partir de la fecha de venta, quedará a favor de la acreedora, pues se entenderá como renuncia y cubrirá los gastos
        de almacenaje. Si el producto de la venta no alcanzara a cubrir el monto del mutuo y demás cobros, en su totalidad, la ACREEDORA tendrá
        derecho de demandar al DEUDOR por lo que reste de cubrir dicha deuda.
    </P>

    <p>
        <strong>DÉCIMA CUARTA:</strong> VENTA ANTICIPADA. Si el DEUDOR lo solicita y la ACREEDORA lo autoriza expresamente, podrá adelantarse la
        venta de la prenda antes del vencimiento del plazo, para lo cual se estará a lo dispuesto en las cláusulas relativas a la venta del bien. La
        respuesta de la ACREEDORA será dada en un período de tiempo no mayor a ocho días hábiles siguientes a la fecha de presentación de la
        solicitud.
    </P>

    <p>
        <strong>DÉCIMA QUINTA:</strong> EL DEUDOR PRENDARIO tendrá el derecho de cubrir el saldo total del mutuo, sus intereses, almacenaje, y accesorios
        antes del vencimiento del plazo establecido en el anverso y conforme a las opciones de pago descritas en cuyo caso, dará aviso a la
        ACREEDORA en forma verbal o telefónica, un día antes de la fecha en que desee efectuar el pago anticipado del mutuo o del bien;
        efectuado el pago, se procederá a la devolución de la prenda en el acto.
    </P>

    <p>
        <strong>DÉCIMA SEXTA:</strong> El DEUDOR PRENDARIO tendrá el derecho de renovar o prorrogar el plazo del contrato por un periodo igual y sucesivo al
        pactado, antes del vencimiento del plazo indicado en el anverso, siempre y cuando se cubran en su totalidad los intereses, almacenaje y
        accesorios devengados hasta la fecha de la renovación, de acuerdo con las opciones de pago que se indican en el anverso. Los nuevos
        intereses, almacenaje y gastos de operación y comisión por venta, serán calculados a las tasas vigentes de la fecha de la renovación, se
        suscribirá un anexo de renovación entre las partes.
    </P>

    <p>
        <strong>DÉCIMA SÉPTIMA:</strong> a) EL DEUDOR PRENDARIO no podrá ceder, enajenar ni gravar los derechos derivados del contrato, salvo que
        obtenga autorización previa y por escrito de la ACREEDORA. b) EL DEUDOR PRENDARIO autoriza a la ACREEDORA, para ceder, gravar,
        enajenar o disponer de cualquier forma ante terceros los derechos que tiene a su favor.
    </P>

    <p>
        <strong>DÉCIMA OCTAVA:</strong> El DEUDOR PRENDARIO deberá cumplir con todos los impuestos y contribuciones que resulten a su cargo conforme
        las leyes impositivas correspondientes.
    </P>

    <p>
        <strong>DÉCIMA NOVENA:</strong> Todos y cada uno de los derechos y deberes asumidos por el DEUDOR PRENDARIO, en el marco de este contrato
        serán ejercidos o cumplimentados personalmente por este o por conducto de un representante legal debidamente acreditado de acuerdo con
        la legislación guatemalteca.
    </P>

    <p>
        <strong>VIGÉSIMA: LEGITIMIDAD.</strong> Para el ejercicio de los derechos, o el incumplimiento de los deberes a su cargo, EL DEUDOR PRENDARIO
        deberá presentar a la ACREEDORA este contrato, así como una identificación extendida por autoridad competente. En caso de extravío del
        contrato, se podrá tramitar su reposición, solicitándolo por escrito previo y cubriendo el gasto administrativo de Q.10.00.
    </P>

    <p>
        <strong>VIGÉSIMA PRIMERA:</strong> La invalidez, ilegalidad, falta de coercibilidad de cualquiera de las disposiciones contenidas en este contrato, no
        afectará la validez y exigibilidad de las demás disposiciones acordadas de las partes. De haber alguna causal de nulidad, la misma acta
        solamente a la cláusula en la que específicamente se hubiera incurrido en el vicio correspondiente.
    </P>

    <p>
        <strong>VIGÉSIMA SEGUNDA:</strong> Cualquier modificación o extinción de los derechos y obligaciones contenidas en el presente acuerdo de voluntades,
        deberá hacerse mediante convenio escrito. El efectuarse el respectivo pago del mutuo con los intereses y accesorios establecidos en este
        contrato, EL DEUDOR PRENDARIO recibirá la prenda en el mismo lugar de la entregó y extenderá a la ACREEDORA el finiquito respectivo.
    </P>

    <p>
        <strong>VIGÉSIMA TERCERA:</strong> DERECHO APLICABLE. Este contrato se rige por lo dispuesto en la Ley de Garantías Mobiliarias, el Código Civil, y
        la Ley de Defensa del Consumidor y Usuario de la República de Guatemala.
    </P>

    <p>
        <strong>VIGÉSIMA CUARTA:</strong> Para todo lo relativo a la interpretación, aplicación y cumplimiento del contrato, las partes acuerdan someterse en
        primera instancia a la dirección de atención y asistencia al consumidor (DIACO), del Ministerio de Economía, y en caso de no resolverse el
        diferendo, a la jurisdicción de los tribunales competentes del fuero común de la ciudad de Guatemala, renunciando al fuero de sus domicilios
        y señalando como lugar para recibir notificaciones los indicados en el anverso del presente documento.
    </P>

    <p> 

    </p>
    

    <p>
        Leído lo escrito, enterados de su contenido, objeto, validez y consecuencias legales, este contrato es suscrito en duplicado, en la ciudad de
        {{ $nombreEmpresa }}, en la fecha que se indica en el anverso.
    </p><br>


        {{-- FIRMAS TRAS LA AUTÉNTICA --}}
        <table class="firmas-tabla" style="margin-top: 25px; margin-bottom: 20px;">
            <tr>
                <td class="firma-col">
                    <div class="linea-firma"></div>
                    <div style="font-weight: bold; font-size: 8pt;">"ACREEDORA"</div>
                    <div class="normal">{{ $nombreEmpresa }}</div>
                </td>
                <td class="firma-col">
                    <div class="linea-firma"></div>
                    <div style="font-weight: bold; font-size: 8pt;">"DEUDOR (A)"</div>
                    <div class="normal">{{ $clienteNombre }}</div>
                </td>
            </tr>
        </table>

        <div class="ante-mi">
            <div class="linea-firma" style="width: 100%;"></div>
            <div style="font-size: 8pt;">Contrato No. {{ $credito->codigo_credito ?? $credito->numero_credito ?? 'S/N' }} {{ $nombreEmpresa }}</div>
        </div>
    </div>

    <div class="page-break"></div>

    <div class="anverso-marco">
        <div class="anverso-titulo">ANVERSO DEL CONTRATO — DATOS DEL CLIENTE Y CONDICIONES</div>
        <div class="anverso-numero">No. de Contrato: {{ $credito->codigo_credito ?? $credito->numero_credito ?? '—' }}</div>

        <table class="anverso-datos">
            <tr>
                <td class="etiqueta">NOMBRE COMPLETO</td><td class="valor">{{ $clienteNombre ?: '—' }}</td>
                <td class="etiqueta separador">MONTO DEL PRÉSTAMO</td><td class="valor"><strong>Q {{ number_format($montoNum, 2) }}</strong></td>
            </tr>
            <tr>
                <td class="etiqueta">DPI / PASAPORTE</td><td class="valor">{{ $clienteDpiRaw ?: '—' }}</td>
                <td class="etiqueta separador">TASA DE INTERÉS</td><td class="valor">{{ number_format($tasaInteres, 2) }}% {{ $credito->periodo_tasa ?? 'anual' }}</td>
            </tr>
            <tr>
                <td class="etiqueta">DIRECCIÓN</td><td class="valor">{{ $clienteDireccion ?: '—' }}</td>
                <td class="etiqueta separador">PLAZO</td><td class="valor">{{ $credito->plazo_cuotas ?? $credito->numero_cuotas ?? $plazoDias }} {{ ($credito->plazo_cuotas ?? $credito->numero_cuotas ?? null) ? 'cuota(s)' : 'días' }}</td>
            </tr>
            <tr>
                <td class="etiqueta">TELÉFONO</td><td class="valor">{{ $cliente->telefono ?? $cliente->celular ?? '—' }}</td>
                <td class="etiqueta separador">DÍAS DE GRACIA</td><td class="valor">{{ $credito->dias_gracia ?? 0 }} días</td>
            </tr>
            <tr>
                <td class="etiqueta">FECHA DEL CONTRATO</td><td class="valor">{{ $fechaContratoObj->format('d/m/Y') }}</td>
                <td class="etiqueta separador">FECHA DE VENCIMIENTO</td><td class="valor">{{ $fechaVencObj->format('d/m/Y') }}</td>
            </tr>
            <tr>
                <td></td><td></td><td class="etiqueta separador">FRECUENCIA DE PAGO</td><td class="valor">{{ ucfirst($credito->frecuencia_pago ?? 'Mensual') }}</td>
            </tr>
        </table>

        <div class="anverso-garantia">
            <div class="anverso-subtitulo">DESCRIPCIÓN DE LA PRENDA (GARANTÍA)</div>
            <table class="anverso-prendas">
                <thead><tr><th style="width: 11%">CÓDIGO</th><th style="width: 42%">DESCRIPCIÓN</th><th style="width: 18%">MARCA / MODELO</th><th style="width: 18%">SERIE / NO.</th><th style="width: 11%">ESTADO</th></tr></thead>
                <tbody>
                @forelse($listaPrendas as $prenda)
                    <tr>
                        <td>{{ $prenda->codigo ?? $prenda->codigo_prenda ?? '—' }}</td>
                        <td>{{ $prenda->descripcion ?? '—' }}</td>
                        <td>{{ trim(($prenda->marca ?? '') . ' ' . ($prenda->modelo ?? '')) ?: '—' }}</td>
                        <td>{{ $prenda->numero_serie ?? $prenda->serie ?? '—' }}</td>
                        <td>{{ $prenda->estado ?? '—' }}</td>
                    </tr>
                @empty
                    <tr><td>—</td><td>Sin prendas registradas</td><td>—</td><td>—</td><td>—</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="anverso-pie">
            <div><strong>GASTOS DE OPERACIÓN / COMISIÓN POR VENTA:</strong> {{ $credito->gastos_operacion ?? 'Según tarifa vigente' }}</div>
            <div class="derecha"><strong>INTERESES ESTIMADOS AL VENCIMIENTO:</strong> <u>Q {{ number_format((float) ($credito->intereses_estimados ?? $credito->interes_total ?? 0), 2) }}</u></div>
        </div>
    </div>

</body>
</html>
