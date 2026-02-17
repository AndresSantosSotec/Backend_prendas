<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Crédito - {{ $credito->numero_credito }}</title>
    <style>
        @page {
            margin: 0.5cm;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            color: #333;
            line-height: 1.4;
            margin: 0;
            padding: 10px;
        }
        .header {
            text-align: center;
            border-bottom: 3px solid #2563eb;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .brand-title {
            font-size: 18px;
            font-weight: bold;
            color: #1e293b;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .brand-subtitle {
            font-size: 10px;
            color: #64748b;
        }
        .document-title {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            color: #2563eb;
            margin: 15px 0;
            text-transform: uppercase;
        }
        .info-section {
            margin-bottom: 15px;
        }
        .info-row {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }
        .info-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding-right: 10px;
        }
        .info-label {
            font-weight: bold;
            color: #64748b;
            font-size: 9px;
            text-transform: uppercase;
            display: block;
            margin-bottom: 2px;
        }
        .info-value {
            color: #1e293b;
            font-size: 11px;
            font-weight: 500;
        }
        .amount-value {
            color: #2563eb;
            font-weight: bold;
            font-size: 13px;
        }
        .barcode-section {
            text-align: center;
            margin: 20px 0;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 5px;
        }
        .barcode-image {
            max-width: 100%;
            height: auto;
            margin: 10px 0;
        }
        .barcode-number {
            font-family: 'Courier New', monospace;
            font-size: 14px;
            font-weight: bold;
            letter-spacing: 2px;
            margin-top: 10px;
            color: #1e293b;
        }
        .summary-box {
            border: 2px solid #e2e8f0;
            border-radius: 5px;
            padding: 12px;
            margin: 15px 0;
            background-color: #f8fafc;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e2e8f0;
        }
        .summary-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 12px;
            color: #2563eb;
            margin-top: 5px;
            padding-top: 5px;
            border-top: 2px solid #2563eb;
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #ddd;
            font-size: 8px;
            color: #94a3b8;
            text-align: center;
        }
        .prenda-info {
            background-color: #f1f5f9;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        .prenda-item {
            margin-bottom: 5px;
            font-size: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="brand-title">{{ $sucursal->nombre ?? 'DigiPrenda' }}</div>
        <div class="brand-subtitle">{{ $sucursal->direccion ?? 'Sistema de Gestión de Créditos' }}</div>
    </div>

    <h2 class="document-title">Recibo de Crédito Prendario</h2>

    <div class="info-section">
        <div class="info-row">
            <div class="info-col">
                <span class="info-label">Número de Crédito</span>
                <span class="info-value">{{ $credito->numero_credito }}</span>
            </div>
            <div class="info-col">
                <span class="info-label">Fecha de Emisión</span>
                <span class="info-value">{{ \Carbon\Carbon::now()->format('d/m/Y H:i:s') }}</span>
            </div>
        </div>
    </div>

    <div class="info-section">
        <div class="info-row">
            <div class="info-col">
                <span class="info-label">Cliente</span>
                <span class="info-value">{{ $cliente->nombres ?? '' }} {{ $cliente->apellidos ?? '' }}</span>
            </div>
            <div class="info-col">
                <span class="info-label">DPI</span>
                <span class="info-value">{{ $cliente->dpi ?? 'N/A' }}</span>
            </div>
        </div>
        @if($cliente->telefono)
        <div class="info-row">
            <div class="info-col">
                <span class="info-label">Teléfono</span>
                <span class="info-value">{{ $cliente->telefono }}</span>
            </div>
        </div>
        @endif
    </div>

    @if($prendas && count($prendas) > 0)
    <div class="info-section">
        <span class="info-label">Prenda(s) Empeñada(s)</span>
        <div class="prenda-info">
            @foreach($prendas as $prenda)
            <div class="prenda-item">
                <strong>{{ $prenda->descripcion ?? $prenda->descripcion_general ?? 'Sin descripción' }}</strong>
                @if(isset($prenda->marca) && $prenda->marca)
                    - Marca: {{ $prenda->marca }}
                @endif
                @if(isset($prenda->modelo) && $prenda->modelo)
                    - Modelo: {{ $prenda->modelo }}
                @endif
                @if(isset($prenda->codigo_prenda) && $prenda->codigo_prenda)
                    <br><small>Código: {{ $prenda->codigo_prenda }}</small>
                @endif
            </div>
            @endforeach
        </div>
    </div>
    @endif

    <div class="summary-box">
        <div class="summary-row">
            <span>Monto Aprobado:</span>
            <span class="amount-value">Q {{ number_format($credito->monto_aprobado ?? $credito->monto_solicitado ?? 0, 2, '.', ',') }}</span>
        </div>
        @if(isset($credito->valor_tasacion) && $credito->valor_tasacion)
        <div class="summary-row">
            <span>Valor de Tasación:</span>
            <span>Q {{ number_format($credito->valor_tasacion, 2, '.', ',') }}</span>
        </div>
        @endif
        <div class="summary-row">
            <span>Tasa de Interés:</span>
            <span>{{ number_format($credito->tasa_interes ?? 0, 2) }}% {{ ucfirst($credito->tipo_interes ?? 'mensual') }}</span>
        </div>
        @if(isset($credito->tasa_mora) && $credito->tasa_mora)
        <div class="summary-row">
            <span>Tasa de Mora:</span>
            <span>{{ number_format($credito->tasa_mora, 2) }}% diario</span>
        </div>
        @endif
        <div class="summary-row">
            <span>Plazo:</span>
            @if(isset($credito->tipo_interes) && strtolower($credito->tipo_interes) == 'mensual')
                <span>{{ $credito->numero_cuotas ?? 'N/A' }} {{ ($credito->numero_cuotas ?? 0) == 1 ? 'mes' : 'meses' }}</span>
            @else
                <span>{{ $credito->plazo_dias ?? 'N/A' }} días</span>
            @endif
        </div>
        @if(isset($credito->numero_cuotas) && $credito->numero_cuotas)
        <div class="summary-row">
            <span>Número de Cuotas:</span>
            <span>{{ $credito->numero_cuotas }}</span>
        </div>
        @endif
        @if(isset($credito->fecha_vencimiento) && $credito->fecha_vencimiento)
        <div class="summary-row">
            <span>Fecha de Vencimiento:</span>
            <span>{{ \Carbon\Carbon::parse($credito->fecha_vencimiento)->format('d/m/Y') }}</span>
        </div>
        @endif
        @if(isset($credito->fecha_desembolso) && $credito->fecha_desembolso)
        <div class="summary-row">
            <span>Fecha de Desembolso:</span>
            <span>{{ \Carbon\Carbon::parse($credito->fecha_desembolso)->format('d/m/Y') }}</span>
        </div>
        @endif
    </div>

    <div class="barcode-section">
        <div class="info-label">Código de Barras</div>
        @if(isset($barcodeImage))
            <img src="data:image/png;base64,{{ $barcodeImage }}" alt="Código de Barras" class="barcode-image">
        @endif
        <div class="barcode-number">{{ $credito->numero_credito }}</div>
    </div>

    <div class="footer">
        <p>Este recibo es un comprobante de la transacción realizada. Consérvelo para sus registros.</p>
        <p>Generado el {{ \Carbon\Carbon::now()->format('d/m/Y H:i:s') }} - {{ $sucursal->nombre ?? 'Sistema de Gestión' }}</p>
    </div>
</body>
</html>
