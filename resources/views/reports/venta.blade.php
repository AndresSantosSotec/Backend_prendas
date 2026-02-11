<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Venta {{ $venta->codigo_venta }}</title>
    <style>
        body { font-family: 'Courier New', monospace; font-size: 11px; color: #000; line-height: 1.3; }
        .header { text-align: center; margin-bottom: 15px; border: 2px solid #000; padding: 10px; }
        .header h1 { margin: 0; color: #000; font-size: 18px; font-weight: bold; letter-spacing: 1px; }
        .header p { margin: 5px 0; color: #000; font-size: 10px; }
        .info-section { margin-bottom: 15px; }
        .info-col { width: 50%; display: inline-block; vertical-align: top; }
        .label { font-weight: bold; color: #000; }
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table th { background: #000; border: 1px solid #000; padding: 8px; text-align: left; color: white; font-size: 10px; }
        .table td { border: 1px solid #000; padding: 8px; font-size: 10px; }
        .totals { margin-top: 20px; text-align: right; }
        .totals-table { display: inline-table; width: 200px; font-size: 10px; }
        .totals-table td { padding: 5px; font-size: 10px; }
        .totals-table .total-row { font-weight: bold; font-size: 12px; border-top: 2px solid #000; }
        .footer { position: absolute; bottom: 0; width: 100%; text-align: center; color: #000; font-size: 10px; border-top: 1px solid #000; padding-top: 8px; }
        .badge { padding: 3px 8px; border: 1px solid #000; font-size: 9px; display: inline-block; font-weight: bold; }
        .badge-success { background: #f5f5f5; color: #000; }
        .badge-pending { background: #e0e0e0; color: #000; }
    </style>
</head>
<body>
    <div class="header">
        <h1>SISTEMA DE EMPEÑOS</h1>
        <p>{{ $venta->sucursal->nombre ?? 'Tienda Principal' }}</p>
        <p>{{ $venta->sucursal->direccion ?? '' }}</p>
        <p>Documento: {{ $venta->tipo_documento }} - {{ $venta->codigo_venta }}</p>
    </div>

    <div class="info-section">
        <table style="width:100%; border-collapse: collapse;">
            <tr>
                <td class="info-col">
                    <p><span class="label">CLIENTE:</span> {{ $venta->cliente_nombre }}</p>
                    <p><span class="label">NIT:</span> {{ $venta->cliente_nit ?? 'C/F' }}</p>
                    <p><span class="label">FECHA:</span> {{ \Carbon\Carbon::parse($venta->fecha_venta)->format('d/m/Y H:i') }}</p>
                </td>
                <td class="info-col">
                    <p><span class="label">VENDEDOR:</span> {{ $venta->vendedor->name ?? 'Admin' }}</p>
                    <p><span class="label">ESTADO:</span> <span class="badge {{ $venta->estado === 'pagada' ? 'badge-success' : 'badge-pending' }}">{{ strtoupper($venta->estado) }}</span></p>
                    @if($venta->certificada)
                        <p><span class="label">AUTORIZACI\u00d3N:</span> {{ $venta->no_autorizacion }}</p>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>CANT</th>
                <th>DESCRIPCIÓN</th>
                <th style="text-align: right;">PRECIO UNIT.</th>
                <th style="text-align: right;">DESC.</th>
                <th style="text-align: right;">SUBTOTAL</th>
            </tr>
        </thead>
        <tbody>
            @foreach($venta->detalles as $detalle)
                <tr>
                    <td>{{ $detalle->cantidad }}</td>
                    <td>{{ $detalle->descripcion }} ({{ $detalle->codigo }})</td>
                    <td style="text-align: right;">{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($detalle->precio_unitario, 2) }}</td>
                    <td style="text-align: right;">{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($detalle->descuento, 2) }}</td>
                    <td style="text-align: right;">{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($detalle->subtotal, 2) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="totals">
        <table class="totals-table">
            <tr>
                <td>Subtotal:</td>
                <td style="text-align: right;">{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($venta->subtotal, 2) }}</td>
            </tr>
            <tr>
                <td>Descuentos:</td>
                <td style="text-align: right;">-{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($venta->total_descuentos, 2) }}</td>
            </tr>
            <tr class="total-row">
                <td>TOTAL:</td>
                <td style="text-align: right;">{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($venta->total_final, 2) }}</td>
            </tr>
        </table>
    </div>

    @if($venta->pagos->count() > 0)
    <div style="margin-top: 30px;">
        <h3>Detalle de Pagos</h3>
        <table class="table" style="width: 50%;">
            <thead>
                <tr>
                    <th>MÉTODO</th>
                    <th style="text-align: right;">MONTO</th>
                </tr>
            </thead>
            <tbody>
                @foreach($venta->pagos as $pago)
                    <tr>
                        <td>{{ strtoupper($pago->metodo_nombre ?? ($pago->metodoPago->nombre ?? 'EFECTIVO')) }}</td>
                        <td style="text-align: right;">{{ $venta->moneda->simbolo ?? 'Q' }}{{ number_format($pago->monto, 2) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    <div class="footer">
        <p>Gracias por su compra. Este es un comprobante de venta oficial.</p>
        <p>Generado el {{ date('d/m/Y H:i:s') }}</p>
    </div>
</body>
</html>
