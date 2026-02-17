<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización {{ $cotizacion->numero_cotizacion }}</title>
    <style>
        @page {
            margin: 1.5cm;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11px;
            color: #000;
            line-height: 1.4;
            padding: 15px;
        }
        .encabezado {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #000;
        }
        .empresa {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .titulo-documento {
            font-size: 14px;
            font-weight: bold;
            margin: 10px 0;
            letter-spacing: 1px;
        }
        .info-grid {
            margin: 15px 0;
        }
        table.info {
            width: 100%;
            border-collapse: collapse;
        }
        table.info td {
            padding: 4px 8px;
            vertical-align: top;
        }
        table.info td.label {
            font-weight: bold;
            width: 35%;
        }
        .seccion-titulo {
            font-weight: bold;
            background-color: #f5f5f5;
            padding: 8px;
            margin: 15px 0 8px 0;
            border: 1px solid #000;
            text-align: center;
            font-size: 12px;
        }
        table.productos {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        table.productos th {
            background-color: #000;
            color: #fff;
            padding: 8px;
            text-align: left;
            font-size: 10px;
            font-weight: bold;
        }
        table.productos td {
            padding: 6px 8px;
            border-bottom: 1px solid #ddd;
            font-size: 10px;
        }
        table.productos td.codigo {
            font-family: 'Courier New', monospace;
            font-size: 9px;
        }
        table.productos td.numero {
            text-align: center;
            font-weight: bold;
        }
        table.productos td.precio {
            text-align: right;
            font-weight: bold;
        }
        .totales {
            margin-top: 20px;
            padding: 10px;
            background-color: #f9f9f9;
            border: 2px solid #000;
        }
        table.totales-tabla {
            width: 100%;
            border-collapse: collapse;
        }
        table.totales-tabla td {
            padding: 5px 10px;
        }
        table.totales-tabla td.label {
            text-align: right;
            font-weight: bold;
            width: 70%;
        }
        table.totales-tabla td.valor {
            text-align: right;
            font-weight: bold;
            width: 30%;
        }
        .total-final {
            font-size: 16px;
            padding: 8px 10px !important;
            background-color: #000;
            color: #fff;
        }
        .notas {
            margin-top: 20px;
            padding: 10px;
            background-color: #f5f5f5;
            border: 1px solid #000;
            font-size: 9px;
        }
        .notas-titulo {
            font-weight: bold;
            margin-bottom: 5px;
        }
        .vigencia {
            margin-top: 15px;
            padding: 8px;
            background-color: #fff;
            border: 2px solid #000;
            text-align: center;
            font-weight: bold;
        }
        .firma-seccion {
            margin-top: 40px;
        }
        table.firmas {
            width: 100%;
            border-collapse: collapse;
        }
        table.firmas td {
            width: 50%;
            padding: 10px;
            text-align: center;
            vertical-align: top;
        }
        .firma-linea {
            border-top: 2px solid #000;
            margin-top: 50px;
            margin-bottom: 5px;
            width: 80%;
            margin-left: auto;
            margin-right: auto;
        }
        .firma-texto {
            font-size: 10px;
            font-weight: bold;
        }
        .pie-pagina {
            margin-top: 20px;
            text-align: center;
            font-size: 9px;
            color: #666;
            padding-top: 10px;
            border-top: 1px solid #000;
        }
    </style>
</head>
<body>
    <!-- Encabezado -->
    <div class="encabezado">
        <div class="empresa">{{ $cotizacion->sucursal->nombre ?? 'DIGIPRENDA' }}</div>
        <div style="font-size: 10px;">
            {{ $cotizacion->sucursal->direccion ?? '' }}<br>
            Tel: {{ $cotizacion->sucursal->telefono ?? '' }}
        </div>
        <div class="titulo-documento">COTIZACIÓN DE VENTA</div>
        <div style="font-size: 12px; font-weight: bold;">No. {{ $cotizacion->numero_cotizacion }}</div>
    </div>

    <!-- Información General -->
    <div class="info-grid">
        <table class="info">
            <tr>
                <td class="label">FECHA:</td>
                <td>{{ $cotizacion->fecha->format('d/m/Y') }}</td>
                <td class="label">VÁLIDA HASTA:</td>
                <td>{{ $cotizacion->fecha_vencimiento ? $cotizacion->fecha_vencimiento->format('d/m/Y') : 'N/A' }}</td>
            </tr>
            <tr>
                <td class="label">CLIENTE:</td>
                <td colspan="3">{{ $cotizacion->cliente_nombre ?? 'Cliente General' }}</td>
            </tr>
            @if($cotizacion->cliente)
            <tr>
                <td class="label">CÓDIGO CLIENTE:</td>
                <td>{{ $cotizacion->cliente->codigo ?? '' }}</td>
                <td class="label">TELÉFONO:</td>
                <td>{{ $cotizacion->cliente->telefono ?? '' }}</td>
            </tr>
            @endif
            <tr>
                <td class="label">TIPO DE VENTA:</td>
                <td colspan="3" style="font-weight: bold;">AL CONTADO</td>
            </tr>
        </table>
    </div>

    <!-- Productos -->
    <div class="seccion-titulo">PRODUCTOS COTIZADOS</div>
    <table class="productos">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 15%;">CÓDIGO</th>
                <th style="width: 50%;">DESCRIPCIÓN</th>
                <th style="width: 10%;">CANT.</th>
                <th style="width: 20%;">PRECIO UNIT.</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cotizacion->productos as $index => $producto)
            <tr>
                <td class="numero">{{ $index + 1 }}</td>
                <td class="codigo">{{ $producto['codigo_prenda'] }}</td>
                <td>{{ $producto['descripcion'] }}</td>
                <td class="numero">{{ $producto['cantidad'] }}</td>
                <td class="precio">Q{{ number_format($producto['precio_unitario'], 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <!-- Totales -->
    <div class="totales">
        <table class="totales-tabla">
            <tr>
                <td class="label">SUBTOTAL:</td>
                <td class="valor">Q{{ number_format($cotizacion->subtotal, 2) }}</td>
            </tr>
            @if($cotizacion->descuento > 0)
            <tr>
                <td class="label">DESCUENTO:</td>
                <td class="valor">-Q{{ number_format($cotizacion->descuento, 2) }}</td>
            </tr>
            @endif
            <tr>
                <td class="label total-final">TOTAL A PAGAR:</td>
                <td class="valor total-final">Q{{ number_format($cotizacion->total, 2) }}</td>
            </tr>
        </table>
    </div>

    <!-- Vigencia -->
    <div class="vigencia">
        ESTA COTIZACIÓN ES VÁLIDA HASTA EL {{ $cotizacion->fecha_vencimiento ? strtoupper($cotizacion->fecha_vencimiento->isoFormat('D [de] MMMM [de] YYYY')) : 'FECHA INDICADA' }}
    </div>

    <!-- Notas -->
    <div class="notas">
        <div class="notas-titulo">TÉRMINOS Y CONDICIONES:</div>
        <ul style="margin: 5px 0; padding-left: 20px;">
            <li>Esta cotización no constituye un compromiso de compra.</li>
            <li>Los precios están sujetos a disponibilidad del producto.</li>
            <li>El pago debe realizarse en efectivo, tarjeta o transferencia bancaria.</li>
            <li>Los productos cotizados podrían venderse a otro cliente si no se confirma la compra antes de la fecha de vencimiento.</li>
            <li>Al realizar la compra se emitirá factura correspondiente.</li>
        </ul>
        @if($cotizacion->observaciones)
        <div style="margin-top: 10px;">
            <strong>OBSERVACIONES:</strong> {{ $cotizacion->observaciones }}
        </div>
        @endif
    </div>

    <!-- Firmas -->
    <div class="firma-seccion">
        <table class="firmas">
            <tr>
                <td>
                    <div class="firma-linea"></div>
                    <div class="firma-texto">VENDEDOR</div>
                    <div style="font-size: 9px; margin-top: 3px;">{{ $cotizacion->usuario->name ?? '' }}</div>
                </td>
                <td>
                    <div class="firma-linea"></div>
                    <div class="firma-texto">CLIENTE</div>
                    <div style="font-size: 9px; margin-top: 3px;">{{ $cotizacion->cliente_nombre ?? 'Cliente General' }}</div>
                </td>
            </tr>
        </table>
    </div>

    <!-- Pie de página -->
    <div class="pie-pagina">
        Documento generado el {{ now()->format('d/m/Y H:i:s') }}
        <br>
        {{ $cotizacion->sucursal->nombre ?? 'Sistema de Gestión de Empeños' }}
    </div>
</body>
</html>
