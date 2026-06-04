<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Contrato Prendario - {{ $credito->codigo_credito ?? $credito->numero_credito }}</title>
    <style>
        @page {
            margin: 0;
            size: letter;
        }
        * { box-sizing: border-box; }
        body {
            font-family: 'Arial Narrow', Arial, Helvetica, sans-serif;
            font-size: 10px;
            color: #000;
            line-height: 1.0;
            margin-top: 2.5cm;
            margin-bottom: 1cm;
            margin-left: 3.5cm;
            margin-right: 1.5cm;
            text-align: justify;
        }
        /* ENCABEZADO — solo primera página */
        header {
            border-bottom: 1.5px solid #000;
            text-align: center;
            padding-bottom: 3px;
            margin-bottom: 5px;
        }
        .hdr-name   { font-size: 11px; font-weight: bold; text-transform: uppercase; }
        .hdr-detail { font-size: 10px; }
        
        /* TÍTULO */
        .titulo-empresa {
            text-align: center;
            font-size: 11px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .titulo-contrato {
            text-align: center;
            font-size: 10px;
            font-weight: bold;
            text-transform: uppercase;
            margin-bottom: 8px;
            line-height: 1.2;
        }
        /* CUERPO */
        .seccion-heading {
            font-weight: bold;
            text-transform: uppercase;
            margin: 3px 0 1px 0;
            font-size: 10px;
        }
        .parrafo  { margin-bottom: 2px; text-align: justify; }
        .clausula { margin-bottom: 2px; text-align: justify; }
        .clausula-num { font-weight: bold; text-transform: uppercase; }
        .cierre   { margin-top: 6px; text-align: justify; }
        /* FIRMAS */
        .firmas-tabla { width: 100%; border-collapse: collapse; margin-top: 15px; }
        .firma-celda  { width: 42%; text-align: center; vertical-align: bottom; padding-top: 15px; }
        .firma-linea  { border-top: 1px solid #000; margin: 0 auto 3px auto; width: 85%; }
        .firma-nombre { font-weight: bold; font-size: 10px; text-transform: uppercase; }
        .firma-rol    { font-size: 10px; }
    </style>
</head>
<body>

{{-- ENCABEZADO --}}
<header style="border-bottom: 0; padding-bottom: 0; margin-bottom: 5px;">
    <table width="100%" style="border-bottom: 1.5px solid #000; padding-bottom: 5px;">
        <tr>
            <td width="25%" style="text-align: left; vertical-align: middle;">
                <img src="data:image/png;base64,{{ base64_encode(file_get_contents(resource_path('logos/avanza_logo.png'))) }}" alt="Logo" style="height: 64px;">
            </td>
            <td width="50%" style="text-align: center; vertical-align: middle;">
                <div class="hdr-name">{{ $sucursal->nombre ?? 'GRUPO VALOR' }}</div>
                @if(!empty($sucursal->direccion))<div class="hdr-detail">{{ $sucursal->direccion }}</div>@endif
                <div class="hdr-detail">
                    @if(!empty($sucursal->telefono))Tel.: {{ $sucursal->telefono }}@endif
                    @if(!empty($sucursal->telefono) && !empty($sucursal->email)) &nbsp;|&nbsp; @endif
                    @if(!empty($sucursal->email)){{ $sucursal->email }}@endif
                    @if(!empty($sucursal->nit)) &nbsp;|&nbsp; NIT: {{ $sucursal->nit }}@endif
                </div>
            </td>
            <td width="25%"></td>
        </tr>
    </table>
</header>

{{-- TÍTULO + DECLARACIONES --}}
<div class="seccion-heading" style="margin-top:0; font-size: 10px; text-align: left;">
    GRUPO VALOR, SOCIEDAD ANÓNIMA
</div>
<div class="seccion-heading" style="margin-top:0;">
    Contrato de Adhesión de Mutuo con Garantía Prendaria que celebra
    GRUPO VALOR, SOCIEDAD ANÓNIMA (La Acreedora),
    y la Persona Física cuyo nombre aparece al anverso de este documento
    (Deudor Prendario), conforme las Declaraciones y Cláusulas siguientes:
</div>
<p class="seccion-heading" style="margin-top:2px;">Declaraciones:</p>

<p class="parrafo">
    Declara el <strong>MUTUANTE:</strong> (Acreedor Prendario) que su representada legalmente constituida conforme a las leyes de la República de Guatemala, según consta en el primer testimonio de la escritura pública número 175 de fecha 10 de octubre de 2025 y su ampliación escritura No. 180 de fecha 23 de octubre de 2025, ambas autorizadas en la ciudad de Esquipulas, departamento de Chiquimula por el Notario Fredy Osvaldo Orozco Nova e inscrita en el Registro Mercantil General de la República bajo el No. 39,329 folio 798 del Libro 27 electrónicos de Sociedades Mercantiles; b) Que su representada cuenta con las facultades necesarias para la celebración del presente contrato de mutuo con garantía prendaria en los artículos 1, 5, 6, 47 y 52 del Decreto 08-2003, Ley de Protección al Consumidor y Usuario, y por los artículos 10, 12, 13, 38, 58, 60, 65, 75 y 78 del Decreto Número 51-2007, Ley de Garantías Mobiliarias, y su reglamento.
</p>

<p class="parrafo">
    Declara el <strong>MUTUARIO:</strong> (Deudor Prendario) a) Que tiene su domicilio como se describe en el anverso de este documento; b) Enterado de las penas relativas al delito de perjuicio manifiesta, que es legítimo propietario de los bienes que se describen en el anverso del presente contrato y conforme lo establecido en la cláusula QUINTA de este contrato.
</p>

<p class="parrafo">
    Declaran ambas partes: Que es su voluntad celebrar el presente contrato de mutuo con garantía prendaria, conforme a lo dispuesto por el artículo 1952 del Código Civil y las siguientes:
</p>

{{-- CLÁUSULAS --}}
<p class="seccion-heading">Cláusulas</p>

<div class="clausula"><span class="clausula-num">Primera: Objeto (Préstamo a Mutuo).</span> El deudor por el presente acto se reconoce lino y llano deudor de GRUPO VALOR, SOCIEDAD ANÓNIMA por la cantidad y demás condiciones que se detallan en el anverso del presente documento y de conformidad con el presente contrato de mutuo mercantil, cantidad que tiene recibida en efectivo y a su entera satisfacción.</div>

<div class="clausula"><span class="clausula-num">Segunda: Condiciones.</span> El deudor se obliga a pagar la cantidad adeudada en la forma, modo, con el interés, que se detalla en el anverso, tales tasas serán devengadas inclusive en los casos en que la ACREEDORA retenga la prenda, a la que se refiere este contrato por falta de pago del mutuo y sus accesorios. Además, el pago deberá hacerse en efectivo junto con los intereses y almacenaje en el mismo establecimiento en que se suscribe este documento dentro del plazo máximo estipulado en el anverso de este documento, plazo que podrá prorrogarse por periodos iguales, previo cumplimiento de las demás condiciones establecidas en este instrumento. El deudor podrá realizar pagos parciales a cuenta del mutuo, los interés y accesorios a su cargo, los cuales devengaran interés a favor del deudor, a la misma tasa del mutuo y se aplicara a amortizar el adeudo, en el orden siguiente: Capital (Mutuo), intereses y accesorios, al momento de establecer las condiciones de pago establecidas en el anverso al realizarse la venta directa del bien. En caso de Prorroga, los pagos a cuenta y los intereses que devengan se acreditaran al pago de los intereses, y deposito, en el orden ya indicado.</div>

<div class="clausula"><span class="clausula-num">Tercera: Garantía.</span> En garantía del capital, intereses, gastos y costas si llegaren a causarse, EL DEUDOR constituye a favor de GRUPO VALOR, SOCIEDAD ANÓNIMA, en calidad de PRENDA, el bien mueble usado que se describe en el anverso, en el entendido de que esta entrega del bien convierte a la ACREEDORA en propietaria de la prenda.</div>

<div class="clausula"><span class="clausula-num">Cuarta:</span> El valor de la prenda es el que se establece en el anverso, en virtud del avalúo practicado por LA ACREEDORA, con criterio de objetividad y equidad y la entera satisfacción de ambas partes.</div>

<div class="clausula"><span class="clausula-num">Quinta:</span> EL DEUDOR PRENDARIO declara expresamente que es único y legítimo propietario de la prenda con el derecho de uso y disfrute sobre la misma y que sobre dichos bienes, no existen gravámenes ni limitaciones que puedan perjudicar los derechos de la ACREEDORA, obligándose a responder del saneamiento de conformidad con la ley. EL DEUDOR PRENDARIO reconoce como de su propiedad el bien descrito en el anverso y declara que el mismo es usado.</div>

<div class="clausula"><span class="clausula-num">Sexta:</span> EL DEUDOR PRENDARIO se obliga a no variar la condición jurídica de la prenda, por lo que no podrá enajenar, gravarla ni comprometerla en forma alguna.</div>

<div class="clausula"><span class="clausula-num">Séptima:</span> Si la ACREEDORA fuere perturbada en la posesión de la prenda por causa imputable al DEUDOR PRENDARIO, avisará por escrito a este para que lleve a cabo las acciones legales pertinentes.</div>

<div class="clausula"><span class="clausula-num">Octava:</span> Si en cumplimiento de una orden dictada por autoridad competente, la ACREEDORA fuera desposeída de la prenda, el DEUDOR PRENDARIO le entregara a su entera satisfacción otra prenda equivalente a esta en peso, calidad, condición y valor dentro de los diez días hábiles siguientes a la notificación que por escrito efectúe el ACREEDOR.</div>

<div class="clausula"><span class="clausula-num">Novena:</span> En caso de pérdida, robo o destrucción de la prenda por cualquier causa no imputable al DEUDOR PRENDARIO, LA ACREEDORA pagará en efectivo al DEUDOR PRENDARIO el valor del avalúo establecido en el anverso de este documento, menos la cantidad entregada por concepto de mutuo y los intereses, así como los accesorios señalados en este contrato que haya devengado hasta la fecha de pago y conforme a las tasas que se indican en el anverso, en su caso. Si se reintegran al DEUDOR PRENDARIO los pagos a cuenta efectuados los intereses devengados.</div>

<div class="clausula"><span class="clausula-num">Décima:</span> LA ACREEDORA tendrá a su cargo la guarda y manejo de la prenda en los términos del artículo 1974 del Código Civil y demás relacionados; en ningún caso, será responsable de los daños y deterioros que pudieren sufrir las cosas custodiadas por culpa o fuerza mayor. Para los efectos de esta cláusula y la anterior, EL DEUDOR PRENDARIO y LA ACREEDORA convienen en que ésta última podrá contratar una aseguradora autorizada por autoridad competente, a costa de esta.</div>

<div class="clausula"><span class="clausula-num">Décima Primera:</span> Si EL DEUDOR PRENDARIO efectúa el pago íntegro y oportuno de la suma dada en mutuo, los intereses, accesorios y demás conceptos que se refieren este contrato, en la forma y plazo convenidos, la ACREEDORA queda facultada por los contratantes, para proceder a la venta directa privada, en los términos del artículo 65 de la Ley de Garantías Mobiliarias, sin necesidad de formalismo o procedimiento alguno, tomando como referencia el valor del avaluó estipulado en la cláusula cuarta, sirviendo como notificación el aviso de intensión de venta que se haga al EL DEUDOR PRENDARIO, quien podrá hacer suspender la enajenación de la prenda, pagando el mutuo, los rendimientos del mismo y demás conceptos en este instrumento, previo a la venta programada.</div>

<div class="clausula"><span class="clausula-num">Décima Segunda:</span> La ACREEDORA en ningún caso, será responsable de la evicción de objeto vendido.</div>

<div class="clausula"><span class="clausula-num">Décima Tercera:</span> La aplicación del producto de la venta. El DEUDOR PRENDARIO faculta a la acreedora a aplicar el producto de la venta de la prenda, al pago del importe del mutuo, de los intereses y accesorios del mismo que se hayan devengado hasta la fecha de la venta, así como los porcentajes que se consignan en el anverso por concepto de gastos de operación y de comisión por venta. Si hubiera algún remanente, será dispuesto a la disposición de EL DEUDOR PRENDARIO; el remanente no cobrado en un lapso de doce meses consecutivos, contados a partir de la fecha de venta, quedará a favor de la acreedora, pues se entenderá como renuncia y cubrirá los gastos de almacenaje. Si el producto de la venta no alcanzara a cubrir el monto del mutuo y demás cobros, en su totalidad, la ACREEDORA tendrá derecho de demandar al DEUDOR por lo que reste de cubrir dicha deuda.</div>

<div class="clausula"><span class="clausula-num">Décima Cuarta: Venta Anticipada.</span> Si el DEUDOR lo solicita y la ACREEDORA lo autoriza expresamente, podrá adelantarse la venta de la prenda antes del vencimiento del plazo, para lo cual se estará a lo dispuesto en las cláusulas relativas a la venta del bien. La respuesta de la ACREEDORA será dada en un período de tiempo no mayor a ocho días hábiles siguientes a la fecha de presentación de la solicitud.</div>

<div class="clausula"><span class="clausula-num">Décima Quinta:</span> EL DEUDOR PRENDARIO tendrá el derecho de cubrir el saldo total del mutuo, sus intereses, almacenaje, y accesorios antes del vencimiento del plazo establecido en el anverso y conforme a las opciones de pago descritas en cuyo caso, dará aviso a la ACREEDORA en forma verbal o telefónica, un día antes de la fecha en que desee efectuar el pago anticipado del mutuo o del bien; efectuado el pago, se procederá a la devolución de la prenda en el acto.</div>

<div class="clausula"><span class="clausula-num">Décima Sexta:</span> El DEUDOR PRENDARIO tendrá el derecho de renovar o prorrogar el plazo del contrato por un periodo igual y sucesivo al pactado, antes del vencimiento del plazo indicado en el anverso, siempre y cuando se cubran en su totalidad los intereses, almacenaje y accesorios devengados hasta la fecha de la renovación, de acuerdo con las opciones de pago que se indican en el anverso. Los nuevos intereses, almacenaje y gastos de operación y comisión por venta, serán calculados a las tasas vigentes de la fecha de la renovación, se suscribirá un anexo de renovación entre las partes.</div>

<div class="clausula"><span class="clausula-num">Décima Séptima:</span> a) EL DEUDOR PRENDARIO no podrá ceder, enajenar ni gravar los derechos derivados del contrato, salvo que obtenga autorización previa y por escrito de la ACREEDORA. b) EL DEUDOR PRENDARIO autoriza a la ACREEDORA, para ceder, gravar, enajenar o disponer de cualquier forma ante terceros los derechos que tiene a su favor.</div>

<div class="clausula"><span class="clausula-num">Décima Octava:</span> El DEUDOR PRENDARIO deberá cumplir con todos los impuestos y contribuciones que resulten a su cargo conforme las leyes impositivas correspondientes.</div>

<div class="clausula"><span class="clausula-num">Décima Novena:</span> Todos y cada uno de los derechos y deberes asumidos por el DEUDOR PRENDARIO, en el marco de este contrato serán ejercidos o cumplimentados personalmente por este o por conducto de un representante legal debidamente acreditado de acuerdo con la legislación guatemalteca.</div>

<div class="clausula"><span class="clausula-num">Vigésima: Legitimidad.</span> Para el ejercicio de los derechos, o el incumplimiento de los deberes a su cargo, EL DEUDOR PRENDARIO deberá presentar a la ACREEDORA este contrato, así como una identificación extendida por autoridad competente. En caso de extravío del contrato, se podrá tramitar su reposición, solicitándolo por escrito previo y cubriendo el gasto administrativo de Q.10.00.</div>

<div class="clausula"><span class="clausula-num">Vigésima Primera:</span> La invalidez, ilegalidad, falta de coercibilidad de cualquiera de las disposiciones contenidas en este contrato, no afectará la validez y exigibilidad de las demás disposiciones acordadas de las partes. De haber alguna causal de nulidad, la misma acta solamente a la cláusula en la que específicamente se hubiera incurrido en el vicio correspondiente.</div>

<div class="clausula"><span class="clausula-num">Vigésima Segunda:</span> Cualquier modificación o extinción de los derechos y obligaciones contenidas en el presente acuerdo de voluntades, deberá hacerse mediante convenio escrito. El efectuarse el respectivo pago del mutuo con los intereses y accesorios establecidos en este contrato, EL DEUDOR PRENDARIO recibirá la prenda en el mismo lugar de la entregó y extenderá a la ACREEDORA el finiquito respectivo.</div>

<div class="clausula"><span class="clausula-num">Vigésima Tercera: Derecho Aplicable.</span> Este contrato se rige por lo dispuesto en la Ley de Garantías Mobiliarias, el Código Civil, y la Ley de Defensa del Consumidor y Usuario de la República de Guatemala.</div>

<div class="clausula"><span class="clausula-num">Vigésima Cuarta:</span> Para todo lo relativo a la interpretación, aplicación y cumplimiento del contrato, las partes acuerdan someterse en primera instancia a la dirección de atención y asistencia al consumidor (DIACO), del Ministerio de Economía, y en caso de no resolverse el diferendo, a la jurisdicción de los tribunales competentes del fuero común de la ciudad de Guatemala, renunciando al fuero de sus domicilios y señalando como lugar para recibir notificaciones los indicados en el anverso del presente documento.</div>

{{-- CIERRE Y FIRMAS --}}
<div class="cierre">
    Leído lo escrito, enterados de su contenido, objeto, validez y consecuencias legales, este contrato es suscrito en duplicado, en la ciudad de {{ $sucursal->ciudad ?? 'Esquipulas' }}, en la fecha que se indica en el anverso.
</div>

<table class="firmas-tabla">
    <tr>
        <td class="firma-celda">
            <div class="firma-linea"></div>
            <div class="firma-nombre">"Acreedora"</div>
            <div class="firma-rol">GRUPO VALOR, SOCIEDAD ANÓNIMA</div>
        </td>
        <td style="width:16%;"></td>
        <td class="firma-celda">
            <div class="firma-linea"></div>
            <div class="firma-nombre">"Deudor (a)"</div>
            <div class="firma-rol">{{ strtoupper($cliente->nombres . ' ' . $cliente->apellidos) }}</div>
        </td>
    </tr>
</table>



</body>
</html>
