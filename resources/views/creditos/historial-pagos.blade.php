<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Historial de Pagos - {{ $credito->codigo_credito ?? $credito->numero_credito }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 11px;
            color: #1f2937;
            line-height: 1.4;
            background-color: #ffffff;
        }

        .container {
            padding: 20px;
            max-width: 100%;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid #3b82f6;
        }

        .header h1 {
            font-size: 18px;
            color: #1e40af;
            margin-bottom: 5px;
        }

        .header h2 {
            font-size: 14px;
            color: #6b7280;
            font-weight: normal;
        }

        .header .sucursal {
            font-size: 10px;
            color: #9ca3af;
            margin-top: 5px;
        }

        /* Info Grid */
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }

        .info-row {
            display: table-row;
        }

        .info-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding: 5px;
        }

        .info-box {
            background-color: #f3f4f6;
            border-radius: 6px;
            padding: 12px;
            margin-bottom: 10px;
        }

        .info-box h3 {
            font-size: 12px;
            color: #4b5563;
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .info-item {
            margin-bottom: 4px;
        }

        .info-item .label {
            color: #6b7280;
            font-size: 10px;
        }

        .info-item .value {
            color: #1f2937;
            font-weight: 600;
            font-size: 11px;
        }

        /* Totales */
        .totales-section {
            margin-bottom: 25px;
        }

        .totales-grid {
            display: table;
            width: 100%;
            border-collapse: collapse;
        }

        .totales-row {
            display: table-row;
        }

        .total-box {
            display: table-cell;
            width: 25%;
            padding: 8px;
            text-align: center;
            background-color: #f9fafb;
            border: 1px solid #e5e7eb;
        }

        .total-box.primary {
            background-color: #dbeafe;
            border-color: #93c5fd;
        }

        .total-box.success {
            background-color: #dcfce7;
            border-color: #86efac;
        }

        .total-box.warning {
            background-color: #fef3c7;
            border-color: #fcd34d;
        }

        .total-box.danger {
            background-color: #fee2e2;
            border-color: #fca5a5;
        }

        .total-box .amount {
            font-size: 14px;
            font-weight: bold;
            color: #1f2937;
        }

        .total-box .label {
            font-size: 9px;
            color: #6b7280;
            text-transform: uppercase;
            margin-top: 3px;
        }

        /* Tabla de movimientos */
        .movimientos-section h3 {
            font-size: 13px;
            color: #1e40af;
            margin-bottom: 10px;
            padding-bottom: 5px;
            border-bottom: 1px solid #e5e7eb;
        }

        .movimientos-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .movimientos-table th {
            background-color: #1e40af;
            color: #ffffff;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
            padding: 8px 6px;
            text-align: left;
            letter-spacing: 0.3px;
        }

        .movimientos-table th:last-child,
        .movimientos-table td:last-child {
            text-align: right;
        }

        .movimientos-table td {
            padding: 8px 6px;
            border-bottom: 1px solid #e5e7eb;
            font-size: 10px;
        }

        .movimientos-table tr:nth-child(even) {
            background-color: #f9fafb;
        }

        .movimientos-table tr:hover {
            background-color: #f3f4f6;
        }

        /* Badges de tipo */
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 8px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-desembolso {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .badge-pago-interes {
            background-color: #dcfce7;
            color: #166534;
        }

        .badge-abono {
            background-color: #fef3c7;
            color: #92400e;
        }

        .badge-liquidacion {
            background-color: #ede9fe;
            color: #5b21b6;
        }

        .badge-renovacion {
            background-color: #cffafe;
            color: #0e7490;
        }

        .badge-mora {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .badge-interes-adelantado {
            background-color: #d1fae5;
            color: #065f46;
        }

        /* Montos */
        .monto {
            font-family: 'DejaVu Sans Mono', monospace;
            text-align: right;
        }

        .monto-positive {
            color: #059669;
        }

        .monto-negative {
            color: #dc2626;
        }

        /* Footer */
        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e5e7eb;
            text-align: center;
            font-size: 9px;
            color: #9ca3af;
        }

        .footer .generated {
            margin-bottom: 5px;
        }

        /* Resumen final */
        .resumen-final {
            margin-top: 20px;
            padding: 15px;
            background-color: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 6px;
        }

        .resumen-final h4 {
            font-size: 11px;
            color: #0369a1;
            margin-bottom: 10px;
        }

        .resumen-final-grid {
            display: table;
            width: 100%;
        }

        .resumen-final-row {
            display: table-row;
        }

        .resumen-final-item {
            display: table-cell;
            padding: 5px 10px;
        }

        .resumen-final-item .label {
            font-size: 9px;
            color: #6b7280;
        }

        .resumen-final-item .value {
            font-size: 12px;
            font-weight: bold;
            color: #1f2937;
        }

        /* Page break para impresión */
        @media print {
            .container {
                padding: 10px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>HISTORIAL DE PAGOS</h1>
            <h2>{{ $credito->codigo_credito ?? $credito->numero_credito }}</h2>
            @if($sucursal)
            <div class="sucursal">{{ $sucursal->nombre ?? 'Sucursal Principal' }}</div>
            @endif
        </div>

        <!-- Información del crédito y cliente -->
        <div class="info-grid">
            <div class="info-row">
                <div class="info-col">
                    <div class="info-box">
                        <h3>Información del Cliente</h3>
                        <div class="info-item">
                            <span class="label">Nombre:</span>
                            <span class="value">{{ $cliente->nombre_completo ?? ($cliente->nombres . ' ' . $cliente->apellidos) }}</span>
                        </div>
                        <div class="info-item">
                            <span class="label">DPI:</span>
                            <span class="value">{{ $cliente->dpi ?? $cliente->numero_documento ?? 'N/A' }}</span>
                        </div>
                        <div class="info-item">
                            <span class="label">Teléfono:</span>
                            <span class="value">{{ $cliente->telefono ?? 'N/A' }}</span>
                        </div>
                    </div>
                </div>
                <div class="info-col">
                    <div class="info-box">
                        <h3>Información del Crédito</h3>
                        <div class="info-item">
                            <span class="label">Capital:</span>
                            <span class="value">Q {{ number_format($credito->monto_prestamo ?? $credito->capital, 2) }}</span>
                        </div>
                        <div class="info-item">
                            <span class="label">Tasa de Interés:</span>
                            <span class="value">{{ $credito->tasa_interes }}% mensual</span>
                        </div>
                        <div class="info-item">
                            <span class="label">Estado:</span>
                            <span class="value">{{ ucfirst($credito->estado) }}</span>
                        </div>
                        <div class="info-item">
                            <span class="label">Fecha Desembolso:</span>
                            <span class="value">{{ \Carbon\Carbon::parse($credito->fecha_inicio ?? $credito->fecha_desembolso)->format('d/m/Y') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Totales -->
        <div class="totales-section">
            <div class="totales-grid">
                <div class="totales-row">
                    <div class="total-box primary">
                        <div class="amount">Q {{ number_format($totales['total_pagado'], 2) }}</div>
                        <div class="label">Total Pagado</div>
                    </div>
                    <div class="total-box success">
                        <div class="amount">Q {{ number_format($totales['capital_pagado'], 2) }}</div>
                        <div class="label">Capital Pagado</div>
                    </div>
                    <div class="total-box warning">
                        <div class="amount">Q {{ number_format($totales['interes_pagado'], 2) }}</div>
                        <div class="label">Interés Pagado</div>
                    </div>
                    <div class="total-box danger">
                        <div class="amount">Q {{ number_format($totales['mora_pagada'], 2) }}</div>
                        <div class="label">Mora Pagada</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabla de movimientos -->
        <div class="movimientos-section">
            <h3>Detalle de Movimientos ({{ $movimientos->count() }})</h3>

            @if($movimientos->count() > 0)
            <table class="movimientos-table">
                <thead>
                    <tr>
                        <th style="width: 12%;">Fecha</th>
                        <th style="width: 14%;">Tipo</th>
                        <th style="width: 10%;">Capital</th>
                        <th style="width: 10%;">Interés</th>
                        <th style="width: 10%;">Mora</th>
                        <th style="width: 12%;">Total</th>
                        <th style="width: 18%;">Observación</th>
                        <th style="width: 14%;">Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($movimientos as $mov)
                    <tr>
                        <td>{{ \Carbon\Carbon::parse($mov->fecha_movimiento)->format('d/m/Y') }}</td>
                        <td>
                            @php
                                $badgeClass = 'badge-' . str_replace('_', '-', $mov->tipo_movimiento);
                                $tipoLabel = match($mov->tipo_movimiento) {
                                    'desembolso' => 'Desembolso',
                                    'pago_interes' => 'Pago Interés',
                                    'abono' => 'Abono Capital',
                                    'liquidacion' => 'Liquidación',
                                    'renovacion' => 'Renovación',
                                    'mora' => 'Pago Mora',
                                    'interes_adelantado' => 'Int. Adelantado',
                                    default => ucfirst(str_replace('_', ' ', $mov->tipo_movimiento))
                                };
                            @endphp
                            <span class="badge {{ $badgeClass }}">{{ $tipoLabel }}</span>
                        </td>
                        <td class="monto">
                            @if($mov->capital > 0)
                                Q {{ number_format($mov->capital, 2) }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="monto">
                            @if($mov->interes > 0)
                                Q {{ number_format($mov->interes, 2) }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="monto">
                            @if($mov->mora > 0)
                                Q {{ number_format($mov->mora, 2) }}
                            @else
                                -
                            @endif
                        </td>
                        <td class="monto" style="font-weight: bold;">
                            Q {{ number_format($mov->monto_total, 2) }}
                        </td>
                        <td style="font-size: 9px;">{{ $mov->observacion ?? '-' }}</td>
                        <td style="font-size: 9px;">{{ $mov->usuario->name ?? $mov->usuario_id ?? 'Sistema' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @else
            <p style="text-align: center; color: #6b7280; padding: 20px;">No hay movimientos registrados para este crédito.</p>
            @endif
        </div>

        <!-- Resumen Final -->
        <div class="resumen-final">
            <h4>Resumen del Estado del Crédito</h4>
            <div class="resumen-final-grid">
                <div class="resumen-final-row">
                    <div class="resumen-final-item">
                        <div class="label">Capital Original</div>
                        <div class="value">Q {{ number_format($credito->monto_prestamo ?? $credito->capital, 2) }}</div>
                    </div>
                    <div class="resumen-final-item">
                        <div class="label">Capital Pagado</div>
                        <div class="value" style="color: #059669;">Q {{ number_format($totales['capital_pagado'], 2) }}</div>
                    </div>
                    <div class="resumen-final-item">
                        <div class="label">Capital Pendiente</div>
                        <div class="value" style="color: #dc2626;">Q {{ number_format(($credito->monto_prestamo ?? $credito->capital) - $totales['capital_pagado'], 2) }}</div>
                    </div>
                    <div class="resumen-final-item">
                        <div class="label">% Pagado</div>
                        @php
                            $capitalOriginal = $credito->monto_prestamo ?? $credito->capital;
                            $porcentaje = $capitalOriginal > 0 ? ($totales['capital_pagado'] / $capitalOriginal) * 100 : 0;
                        @endphp
                        <div class="value">{{ number_format($porcentaje, 1) }}%</div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <div class="generated">Documento generado el {{ $fechaGeneracion }}</div>
            <div>Este documento es un resumen del historial de pagos y no tiene valor fiscal.</div>
        </div>
    </div>
</body>
</html>
