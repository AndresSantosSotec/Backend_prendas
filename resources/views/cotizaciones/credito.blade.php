<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cotización a Crédito {{ $cotizacion->numero_cotizacion }}</title>
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
        .subtitulo {
            font-size: 12px;
            font-weight: bold;
            color: #666;
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
        .plan-pagos-resumen {
            margin: 15px 0;
            padding: 10px;
            background-color: #f9f9f9;
            border: 2px solid #000;
        }
        table.resumen-tabla {
            width: 100%;
            border-collapse: collapse;
        }
        table.resumen-tabla td {
            padding: 5px 10px;
            font-size: 10px;
        }
        table.resumen-tabla td.label {
            font-weight: bold;
            width: 60%;
        }
        table.resumen-tabla td.valor {
            text-align: right;
            font-weight: bold;
            width: 40%;
        }
        .plan-pagos-detalle {
            margin: 15px 0;
        }
        table.cuotas {
            width: 100%;
            border-collapse: collapse;
        }
        table.cuotas th {
            background-color: #000;
            color: #fff;
            padding: 8px;
            text-align: center;
            font-size: 10px;
            font-weight: bold;
        }
        table.cuotas td {
            padding: 6px 8px;
            border-bottom: 1px solid #ddd;
            text-align: center;
            font-size: 10px;
        }
        table.cuotas td.monto {
            text-align: right;
            font-weight: bold;
        }
        .totales {
            margin-top: 15px;
            padding: 10px;
            background-color: #f5f5f5;
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
            font-size: 14px;
            padding: 8px 10px !important;
            background-color: #000;
            color: #fff;
        }
        .interes-total {
            background-color: #fff;
            padding: 8px 10px !important;
            border-top: 2px solid #000;
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
            margin-top: 30px;
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
            margin-top: 40px;
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
            margin-top: 15px;
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
        <div class="titulo-documento">COTIZACIÓN DE VENTA A CRÉDITO</div>
        <div class="subtitulo">CON PLAN DE PAGOS</div>
        <div style="font-size: 12px; font-weight: bold; margin-top: 5px;">No. {{ $cotizacion->numero_cotizacion }}</div>
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

    <!-- Resumen Plan de Pagos -->
    <div class="seccion-titulo">RESUMEN DEL PLAN DE PAGOS</div>
    <div class="plan-pagos-resumen">
        <table class="resumen-tabla">
            <tr>
                <td class="label">PRECIO DE CONTADO:</td>
                <td class="valor">Q{{ number_format($cotizacion->total, 2) }}</td>
            </tr>
            @if($cotizacion->plan_pagos)
            <tr>
                <td class="label">NÚMERO DE CUOTAS MENSUALES:</td>
                <td class="valor">{{ $cotizacion->plan_pagos['numero_cuotas'] }} cuotas</td>
            </tr>
            <tr>
                <td class="label">TASA DE INTERÉS MENSUAL:</td>
                <td class="valor">{{ number_format($cotizacion->plan_pagos['tasa_interes'], 2) }}%</td>
            </tr>
            <tr style="background-color: #fff;">
                <td class="label" style="font-size: 12px;">CUOTA MENSUAL FIJA:</td>
                <td class="valor" style="font-size: 14px; color: #000;">Q{{ number_format($cotizacion->plan_pagos['monto_cuota'], 2) }}</td>
            </tr>
            <tr>
                <td class="label">TOTAL DE INTERESES:</td>
                <td class="valor">Q{{ number_format($cotizacion->plan_pagos['total_con_intereses'] - $cotizacion->total, 2) }}</td>
            </tr>
            <tr class="interes-total">
                <td class="label" style="font-size: 12px;">TOTAL A PAGAR:</td>
                <td class="valor" style="font-size: 14px;">Q{{ number_format($cotizacion->plan_pagos['total_con_intereses'], 2) }}</td>
            </tr>
            @endif
        </table>
    </div>

    <!-- Detalle de Cuotas Proyectadas -->
    @if($cotizacion->plan_pagos)
    <div class="seccion-titulo">DETALLE DE CUOTAS PROYECTADAS</div>
    <div class="plan-pagos-detalle">
        <table class="cuotas">
            <thead>
                <tr>
                    <th style="width: 15%;">CUOTA #</th>
                    <th style="width: 35%;">FECHA ESTIMADA</th>
                    <th style="width: 25%;">MONTO</th>
                    <th style="width: 25%;">SALDO PENDIENTE</th>
                </tr>
            </thead>
            <tbody>
                @php
                    $saldoPendiente = $cotizacion->plan_pagos['total_con_intereses'];
                    $montoCuota = $cotizacion->plan_pagos['monto_cuota'];
                    $fechaPago = $cotizacion->fecha->copy()->addMonth();
                @endphp
                @for($i = 1; $i <= $cotizacion->plan_pagos['numero_cuotas']; $i++)
                    @php
                        $esUltimaCuota = ($i == $cotizacion->plan_pagos['numero_cuotas']);
                        $montoEfectivo = $esUltimaCuota ? $saldoPendiente : $montoCuota;
                        $saldoPendiente -= $montoEfectivo;
                    @endphp
                    <tr>
                        <td>{{ $i }}</td>
                        <td>{{ $fechaPago->format('d/m/Y') }}</td>
                        <td class="monto">Q{{ number_format($montoEfectivo, 2) }}</td>
                        <td class="monto">Q{{ number_format(max(0, $saldoPendiente), 2) }}</td>
                    </tr>
                    @php
                        $fechaPago->addMonth();
                    @endphp
                @endfor
            </tbody>
        </table>
    </div>
    @endif

    <!-- Vigencia -->
    <div class="vigencia">
        ESTA COTIZACIÓN ES VÁLIDA HASTA EL {{ $cotizacion->fecha_vencimiento ? strtoupper($cotizacion->fecha_vencimiento->isoFormat('D [de] MMMM [de] YYYY')) : 'FECHA INDICADA' }}
    </div>

    <!-- Notas -->
    <div class="notas">
        <div class="notas-titulo">TÉRMINOS Y CONDICIONES DEL CRÉDITO:</div>
        <ul style="margin: 5px 0; padding-left: 20px;">
            <li>Esta cotización no constituye un compromiso de compra ni aprobación de crédito.</li>
            <li>El crédito está sujeto a aprobación y verificación de referencias del cliente.</li>
            <li>Se requerirá anticipo mínimo del 20% al momento de la compra.</li>
            <li>Las cuotas deben pagarse puntualmente en las fechas establecidas.</li>
            <li>El interés se calcula sobre el saldo pendiente del financiamiento.</li>
            <li>En caso de mora, se aplicarán cargos adicionales según reglamento.</li>
            <li>Los productos cotizados podrían venderse a otro cliente si no se confirma antes del vencimiento.</li>
            <li>Al formalizar el crédito se firmará contrato específico con todas las condiciones.</li>
        </ul>
        @if($cotizacion->observaciones)
        <div style="margin-top: 10px;">
            <strong>OBSERVACIONES:</strong> {{ $cotizacion->observaciones }}
        </div>
        @endif
        <div style="margin-top: 10px; font-weight: bold; text-align: center;">
            LAS FECHAS Y MONTOS DE CUOTAS SON ESTIMADOS Y PUEDEN VARIAR SEGÚN LA FECHA FINAL DE APROBACIÓN DEL CRÉDITO
        </div>
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
        {{ $cotizacion->sucursal->nombre ?? 'Sistema de Gestión de Empeños' }} - Cotización referencial sujeta a aprobación
    </div>
</body>
</html>
