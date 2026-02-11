<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Recibo de Compra - {{ $compra->codigo_compra }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Courier New', monospace;
            padding: 15px;
            font-size: 11px;
            color: #000;
            line-height: 1.3;
        }
        .header {
            text-align: center;
            margin-bottom: 12px;
            border: 2px solid #000;
            padding: 8px;
        }
        .header h1 {
            font-size: 18px;
            margin-bottom: 3px;
            color: #000;
            letter-spacing: 1px;
            font-weight: bold;
        }
        .header p {
            font-size: 11px;
            color: #000;
            margin-top: 2px;
        }
        .codigo-recibo {
            text-align: center;
            font-size: 13px;
            font-weight: bold;
            margin: 8px 0;
            padding: 5px;
            border-top: 1px solid #000;
            border-bottom: 1px solid #000;
            letter-spacing: 1px;
        }
        .section {
            margin-bottom: 10px;
            page-break-inside: avoid;
        }
        .section-title {
            font-size: 10px;
            font-weight: bold;
            border-bottom: 2px solid #000;
            padding: 3px 0;
            margin-bottom: 4px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            background-color: #f5f5f5;
            padding-left: 5px;
        }
        .info-grid {
            display: table;
            width: 100%;
            border: 1px solid #000;
            border-collapse: collapse;
        }
        .info-row {
            display: table-row;
        }
        .info-label {
            display: table-cell;
            font-weight: bold;
            padding: 4px 5px;
            width: 40%;
            border: 1px solid #000;
            font-size: 9px;
            background-color: #f9f9f9;
        }
        .info-value {
            display: table-cell;
            padding: 4px 5px;
            border: 1px solid #000;
            font-size: 9px;
        }
        .valores-section {
            margin: 15px 0;
            border: 2px solid #000;
            padding: 12px 15px;
        }
        .valor-row {
            width: 100%;
            border-bottom: 1px dotted #000;
            padding: 8px 0;
            overflow: hidden;
        }
        .valor-row:last-child {
            border-bottom: none;
        }
        .valor-label {
            font-weight: bold;
            font-size: 8px;
            float: left;
        }
        .valor-amount {
            font-size: 8px;
            font-weight: bold;
            float: right;
        }
        .total-pagado {
            border: 3px double #000;
            padding: 12px;
            margin: 12px 0;
            text-align: center;
        }
        .total-pagado .label {
            font-size: 12px;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
        }
        .total-pagado .amount {
            font-size: 20px;
            font-weight: bold;
            color: #000;
            letter-spacing: 1px;
        }
        .firma-section {
            margin-top: 25px;
            display: flex;
            justify-content: space-between;
            page-break-inside: avoid;
        }
        .firma-box {
            text-align: center;
            width: 48%;
        }
        .firma-line {
            border-top: 1px solid #000;
            margin-bottom: 5px;
            margin-top: 50px;
        }
        .firma-box p {
            font-size: 9px;
            margin-top: 2px;
        }
        .firma-box strong {
            font-size: 10px;
        }
        .footer {
            margin-top: 15px;
            text-align: center;
            font-size: 9px;
            color: #000;
            border-top: 1px solid #000;
            padding-top: 8px;
        }
        .footer p {
            margin: 2px 0;
        }
        .estado-badge {
            display: inline-block;
            padding: 2px 8px;
            border: 1px solid #000;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9px;
        }
        .observaciones {
            border: 1px solid #000;
            padding: 6px;
            margin-top: 8px;
            font-style: italic;
            font-size: 10px;
        }
        .two-column {
            display: flex;
            justify-content: space-between;
            gap: 8px;
            margin-bottom: 10px;
        }
        .column {
            width: 49.5%;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>RECIBO DE COMPRA</h1>
        <p>{{ config('app.name', 'Sistema de Gestión') }}</p>
        <p style="font-size: 9px; margin-top: 2px;">{{ $compra->sucursal->nombre ?? 'N/A' }} | {{ $compra->fecha_compra->format('d/m/Y H:i') }}</p>
    </div>

    <div class="codigo-recibo">
        No. {{ $compra->codigo_compra }}
    </div>

    <!-- Información en dos columnas para aprovechar espacio -->
    <table style="width:100%; border-collapse: collapse; margin-bottom:10px;">
        <tr>
            <td style="width:50%; vertical-align: top; padding-right:4px;">
                <!-- Columna 1: Vendedor -->
                <div class="section">
                    <div class="section-title">Datos del Vendedor</div>
                    <div class="info-grid">
                        <div class="info-row">
                            <div class="info-label">Nombre:</div>
                            <div class="info-value">{{ $compra->cliente_nombre }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Documento:</div>
                            <div class="info-value">{{ $compra->cliente_documento ?? 'N/A' }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Teléfono:</div>
                            <div class="info-value">{{ $compra->cliente_telefono ?? 'N/A' }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Código:</div>
                            <div class="info-value">{{ $compra->cliente_codigo ?? 'N/A' }}</div>
                        </div>
                    </div>
                </div>
            </td>
            <td style="width:50%; vertical-align: top; padding-left:4px;">
                <!-- Columna 2: Transacción -->
                <div class="section">
                    <div class="section-title">Datos de Transacción</div>
                    <div class="info-grid">
                        <div class="info-row">
                            <div class="info-label">Fecha:</div>
                            <div class="info-value">{{ $compra->fecha_compra->format('d/m/Y') }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Hora:</div>
                            <div class="info-value">{{ $compra->fecha_compra->format('H:i') }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Atendido por:</div>
                            <div class="info-value">{{ $compra->usuario->name ?? $compra->usuario->username ?? 'N/A' }}</div>
                        </div>
                        <div class="info-row">
                            <div class="info-label">Estado:</div>
                            <div class="info-value">
                                <span class="estado-badge">{{ strtoupper($compra->estado) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Detalles del Producto -->
    <div class="section">
        <div class="section-title">Artículo Adquirido</div>
        <div class="info-grid">
            <div class="info-row">
                <div class="info-label">Categoría:</div>
                <div class="info-value">{{ $compra->categoria_nombre }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Descripción:</div>
                <div class="info-value">{{ $compra->descripcion }}</div>
            </div>
            <div class="info-row">
                <div class="info-label">Código Prenda:</div>
                <div class="info-value"><strong>{{ $compra->codigo_prenda_generado }}</strong></div>
            </div>
            @if($compra->marca || $compra->modelo)
            <div class="info-row">
                <div class="info-label">Marca/Modelo:</div>
                <div class="info-value">{{ $compra->marca ?? '' }} {{ $compra->modelo ?? '' }}</div>
            </div>
            @endif
            @if($compra->serie)
            <div class="info-row">
                <div class="info-label">Serie:</div>
                <div class="info-value">{{ $compra->serie }}</div>
            </div>
            @endif
            @if($compra->color)
            <div class="info-row">
                <div class="info-label">Color:</div>
                <div class="info-value">{{ $compra->color }}</div>
            </div>
            @endif
            <div class="info-row">
                <div class="info-label">Condición:</div>
                <div class="info-value">{{ ucfirst(str_replace('_', ' ', $compra->condicion)) }}</div>
            </div>
        </div>
    </div>

    <!-- Valores de la Operación -->
    <div class="valores-section">
        <div class="valor-row">
            <span class="valor-label">VALOR DE TASACIÓN:</span>
            <span class="valor-amount">Q {{ number_format($compra->valor_tasacion, 2) }}</span>
        </div>
        <div class="valor-row">
            <span class="valor-label">MÉTODO DE PAGO:</span>
            <span class="valor-amount">{{ strtoupper($compra->metodo_pago) }}</span>
        </div>
    </div>

    <div class="total-pagado">
        <div class="label">TOTAL PAGADO AL VENDEDOR</div>
        <div class="amount">Q {{ number_format($compra->monto_pagado, 2) }}</div>
    </div>

    @if($compra->observaciones)
    <div class="observaciones">
        <strong>OBSERVACIONES:</strong> {{ $compra->observaciones }}
    </div>
    @endif

    <!-- Firmas -->
    <table style="width:100%; margin-top:40px; border-collapse: collapse;">
        <tr>
            <td style="width:50%; text-align:center; vertical-align: top; padding-right: 10px;">
                <div class="firma-line"></div>
                <p style="font-size: 10px; margin-top: 5px;"><strong>FIRMA DEL VENDEDOR</strong></p>
                <p style="font-size: 9px; margin-top: 3px;">{{ $compra->cliente_nombre }}</p>
                <p style="font-size: 9px; margin-top: 2px;">{{ $compra->cliente_documento ?? '' }}</p>
            </td>
            <td style="width:50%; text-align:center; vertical-align: top; padding-left: 10px;">
                <div class="firma-line"></div>
                <p style="font-size: 10px; margin-top: 5px;"><strong>RECIBÍ CONFORME</strong></p>
                <p style="font-size: 9px; margin-top: 3px;">{{ $compra->usuario->name ?? 'Autorizado' }}</p>
                <p style="font-size: 9px; margin-top: 2px;">{{ config('app.name') }}</p>
            </td>
        </tr>
    </table>

    <!-- Footer -->
    <div class="footer">
        <p>Documento generado el: {{ $fecha_actual }}</p>
        <p>Este recibo constituye comprobante oficial de la transacción realizada.</p>
        <p><strong>{{ config('app.name') }}</strong> - Sistema de Gestión de Compras</p>
    </div>
</body>
</html>
