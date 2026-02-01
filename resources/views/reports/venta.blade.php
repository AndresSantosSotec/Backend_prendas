<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Venta {{ $venta->codigo_venta }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; color: #333; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #eee; padding-bottom: 10px; }
        .header h1 { margin: 0; color: #2563eb; }
        .header p { margin: 5px 0; color: #666; }
        .info-section { margin-bottom: 20px; }
        .info-grid { display: table; width: 100%; }
        .info-col { display: table-cell; width: 50%; vertical-align: top; }
        .label { font-weight: bold; color: #555; }
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table th { background: #f8fafc; border: 1px solid #e2e8f0; padding: 8px; text-align: left; }
        .table td { border: 1px solid #e2e8f0; padding: 8px; }
        .totals { margin-top: 20px; text-align: right; }
        .totals-table { display: inline-table; width: 200px; }
        .totals-table td { padding: 5px; }
        .totals-table .total-row { font-weight: bold; font-size: 14px; border-top: 2px solid #2563eb; }
        .footer { position: absolute; bottom: 0; width: 100%; text-align: center; color: #999; font-size: 10px; }
        .badge { padding: 3px 8px; border-radius: 10px; font-size: 10px; display: inline-block; }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-pending { background: #fef9c3; color: #854d0e; }
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
        <div class="info-grid">
            <div class="info-col">
                <p><span class="label">CLIENTE:</span> {{ $venta->cliente_nombre }}</p>
                <p><span class="label">NIT:</span> {{ $venta->cliente_nit ?? 'C/F' }}</p>
                <p><span class="label">FECHA:</span> {{ \Carbon\Carbon::parse($venta->fecha_venta)->format('d/m/Y H:i') }}</p>
            </div>
            <div class="info-col">
                <p><span class="label">VENDEDOR:</span> {{ $venta->vendedor->name ?? 'Admin' }}</p>
                <p><span class="label">ESTADO:</span> <span class="badge {{ $venta->estado === 'pagada' ? 'badge-success' : 'badge-pending' }}">{{ strtoupper($venta->estado) }}</span></p>
                @if($venta->certificada)
                    <p><span class="label">AUTORIZACIÓN:</span> {{ $venta->no_autorizacion }}</p>
                @endif
            </div>
        </div>
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
