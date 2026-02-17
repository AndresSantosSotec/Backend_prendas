<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Recibo POS - {{ $venta->codigo_venta }}</title>
    <style>
        /* Configuración para impresora térmica 80mm (3 1/8") */
        @page {
            size: 80mm auto;
            margin: 0;
        }
        
        body {
            font-family: 'Courier New', 'Consolas', monospace;
            font-size: 10px;
            line-height: 1.3;
            color: #000;
            margin: 0;
            padding: 8px 10px;
            width: 80mm;
            max-width: 80mm;
            background: white;
        }
        
        /* Centrado */
        .center {
            text-align: center;
        }
        
        /* Negrita */
        .bold {
            font-weight: bold;
        }
        
        /* Header del negocio */
        .header {
            text-align: center;
            margin-bottom: 8px;
            padding-bottom: 6px;
            border-bottom: 1px dashed #000;
        }
        
        .header .empresa {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .header .info {
            font-size: 9px;
            line-height: 1.4;
        }
        
        /* Información del documento */
        .doc-info {
            margin: 8px 0;
            font-size: 9px;
            line-height: 1.5;
        }
        
        .doc-info .row {
            margin: 2px 0;
        }
        
        .doc-info .label {
            display: inline-block;
            width: 70px;
            font-weight: bold;
        }
        
        /* Separador */
        .separator {
            border-top: 1px dashed #000;
            margin: 6px 0;
        }
        
        .separator-double {
            border-top: 1px solid #000;
            margin: 6px 0;
        }
        
        /* Productos */
        .productos {
            margin: 8px 0;
        }
        
        .producto {
            margin: 5px 0;
            padding: 3px 0;
            border-bottom: 1px dotted #ccc;
        }
        
        .producto-desc {
            font-size: 9px;
            word-wrap: break-word;
        }
        
        .producto-detalle {
            font-size: 9px;
            margin-top: 2px;
            display: flex;
            justify-content: space-between;
        }
        
        /* Totales */
        .totales {
            margin: 8px 0;
            font-size: 10px;
        }
        
        .total-row {
            display: flex;
            justify-content: space-between;
            padding: 2px 0;
        }
        
        .total-row.highlight {
            font-weight: bold;
            font-size: 11px;
            padding: 4px 0;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            margin-top: 4px;
        }
        
        /* Pagos */
        .pagos {
            margin: 8px 0;
            font-size: 9px;
        }
        
        .pago-row {
            display: flex;
            justify-content: space-between;
            padding: 1px 0;
        }
        
        /* Footer */
        .footer {
            margin-top: 10px;
            padding-top: 6px;
            border-top: 1px dashed #000;
            text-align: center;
            font-size: 8px;
            line-height: 1.4;
        }
        
        /* Estado badge */
        .badge {
            display: inline-block;
            padding: 2px 6px;
            font-size: 8px;
            font-weight: bold;
            border: 1px solid #000;
            margin: 3px 0;
        }
        
        /* Espacio de corte */
        .cut-space {
            height: 30mm;
            border-top: 1px dashed #ccc;
            margin-top: 10mm;
            text-align: center;
            padding-top: 5mm;
        }
        
        .cut-space::before {
            content: "✂ -- Cortar aquí --";
            font-size: 8px;
            color: #666;
        }
        
        /* Responsive a menos de 80mm */
        @media print {
            body {
                padding: 4px 6px;
            }
            .cut-space {
                page-break-after: always;
            }
        }
    </style>
</head>
<body>
    <!-- HEADER -->
    <div class="header">
        <div class="empresa">{{ $venta->sucursal->nombre ?? 'DIGIPRENDA' }}</div>
        <div class="info">
            @if($venta->sucursal)
                {{ $venta->sucursal->direccion ?? '' }}<br>
                @if($venta->sucursal->telefono)
                Tel: {{ $venta->sucursal->telefono }}<br>
                @endif
            @endif
            NIT: {{ $venta->sucursal->nit ?? 'CF' }}
        </div>
    </div>
    
    <!-- INFORMACIÓN DEL DOCUMENTO -->
    <div class="doc-info">
        <div class="row">
            <span class="label">DOCUMENTO:</span> {{ $venta->tipo_documento ?? 'NOTA' }}
        </div>
        <div class="row">
            <span class="label">No.:</span> {{ $venta->codigo_venta }}
        </div>
        <div class="row">
            <span class="label">FECHA:</span> {{ \Carbon\Carbon::parse($venta->fecha_venta)->format('d/m/Y H:i') }}
        </div>
        <div class="row">
            <span class="label">CLIENTE:</span> {{ $venta->cliente_nombre }}
        </div>
        <div class="row">
            <span class="label">NIT:</span> {{ $venta->cliente_nit ?? 'C/F' }}
        </div>
        @if($venta->vendedor)
        <div class="row">
            <span class="label">VENDEDOR:</span> {{ $venta->vendedor->name }}
        </div>
        @endif
    </div>
    
    <div class="separator"></div>
    
    <!-- ESTADO -->
    <div class="center">
        <span class="badge">
            ESTADO: {{ strtoupper($venta->estado) }}
        </span>
        @if($venta->tipo_venta !== 'contado')
        <span class="badge">
            {{ strtoupper($venta->tipo_venta) }}
        </span>
        @endif
    </div>
    
    <div class="separator-double"></div>
    
    <!-- PRODUCTOS -->
    <div class="productos">
        @foreach($venta->detalles as $detalle)
            <div class="producto">
                <div class="producto-desc bold">
                    {{ $detalle->descripcion }}
                </div>
                <div class="producto-detalle">
                    <span>{{ $detalle->cantidad }} x {{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($detalle->precio_unitario, 2) }}</span>
                    <span class="bold">{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($detalle->subtotal, 2) }}</span>
                </div>
                @if($detalle->descuento > 0)
                <div class="producto-detalle" style="font-size: 8px; color: #666;">
                    <span>Descuento:</span>
                    <span>-{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($detalle->descuento, 2) }}</span>
                </div>
                @endif
            </div>
        @endforeach
    </div>
    
    <div class="separator-double"></div>
    
    <!-- TOTALES -->
    <div class="totales">
        <div class="total-row">
            <span>SUBTOTAL:</span>
            <span>{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($venta->subtotal, 2) }}</span>
        </div>
        
        @if($venta->total_descuentos > 0)
        <div class="total-row">
            <span>DESCUENTOS:</span>
            <span>-{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($venta->total_descuentos, 2) }}</span>
        </div>
        @endif
        
        <div class="total-row highlight">
            <span>TOTAL:</span>
            <span>{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($venta->total_final, 2) }}</span>
        </div>
    </div>
    
    <!-- INFORMACIÓN DE PAGO -->
    @if($venta->pagos && $venta->pagos->count() > 0)
    <div class="separator"></div>
    <div class="pagos">
        <div class="bold center" style="margin-bottom: 4px;">FORMA DE PAGO</div>
        @foreach($venta->pagos as $pago)
            <div class="pago-row">
                <span>{{ strtoupper($pago->metodoPago->nombre ?? $pago->metodo) }}:</span>
                <span>{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($pago->monto, 2) }}</span>
            </div>
            @if($pago->referencia)
            <div style="font-size: 8px; color: #666; margin-left: 10px;">
                Ref: {{ $pago->referencia }}
            </div>
            @endif
        @endforeach
        
        <div class="separator" style="margin: 4px 0;"></div>
        
        <div class="pago-row bold">
            <span>TOTAL PAGADO:</span>
            <span>{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($venta->total_pagado, 2) }}</span>
        </div>
        
        @if($venta->saldo_pendiente > 0)
        <div class="pago-row bold" style="color: #d00;">
            <span>SALDO PENDIENTE:</span>
            <span>{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($venta->saldo_pendiente, 2) }}</span>
        </div>
        @endif
        
        @if(($venta->total_pagado - $venta->total_final) > 0)
        <div class="pago-row">
            <span>CAMBIO:</span>
            <span>{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($venta->total_pagado - $venta->total_final, 2) }}</span>
        </div>
        @endif
    </div>
    @endif
    
    <!-- INFORMACIÓN ADICIONAL PARA CRÉDITO/APARTADO -->
    @if($venta->tipo_venta === 'credito' && $venta->ventaCredito)
    <div class="separator"></div>
    <div class="center bold" style="margin: 6px 0; font-size: 10px;">
        INFORMACIÓN DEL CRÉDITO
    </div>
    <div class="doc-info">
        <div class="row">
            <span class="label">Enganche:</span> {{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($venta->ventaCredito->enganche, 2) }}
        </div>
        <div class="row">
            <span class="label">Cuotas:</span> {{ $venta->ventaCredito->numero_cuotas }}
        </div>
        <div class="row">
            <span class="label">Cuota Mensual:</span> {{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($venta->ventaCredito->monto_cuota, 2) }}
        </div>
        <div class="row">
            <span class="label">Tasa:</span> {{ number_format($venta->ventaCredito->tasa_interes, 2) }}% mensual
        </div>
    </div>
    @endif
    
    @if($venta->tipo_venta === 'apartado' && $venta->apartado)
    <div class="separator"></div>
    <div class="center bold" style="margin: 6px 0; font-size: 10px;">
        INFORMACIÓN DEL APARTADO
    </div>
    <div class="doc-info">
        <div class="row">
            <span class="label">Anticipo:</span> {{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($venta->apartado->anticipo, 2) }}
        </div>
        <div class="row">
            <span class="label">Saldo:</span> {{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($venta->apartado->saldo_pendiente, 2) }}
        </div>
        <div class="row">
            <span class="label">Vencimiento:</span> {{ \Carbon\Carbon::parse($venta->apartado->fecha_limite)->format('d/m/Y') }}
        </div>
    </div>
    @endif
    
    <!-- CERTIFICACIÓN -->
    @if($venta->certificada && $venta->no_autorizacion)
    <div class="separator"></div>
    <div class="center" style="font-size: 8px; margin: 4px 0;">
        <div class="bold">DOCUMENTO CERTIFICADO</div>
        <div>Autorización: {{ $venta->no_autorizacion }}</div>
    </div>
    @endif
    
    <!-- FOOTER -->
    <div class="footer">
        <div class="bold">¡GRACIAS POR SU COMPRA!</div>
        <div style="margin: 3px 0;">
            Este documento es válido<br>
            como comprobante de venta
        </div>
        <div style="margin-top: 4px;">
            Impreso: {{ now()->format('d/m/Y H:i:s') }}
        </div>
        @if($venta->observaciones)
        <div style="margin-top: 4px; font-size: 7px;">
            {{ $venta->observaciones }}
        </div>
        @endif
    </div>
    
    <!-- ESPACIO DE CORTE -->
    <div class="cut-space"></div>
</body>
</html>
