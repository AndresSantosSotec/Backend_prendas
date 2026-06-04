<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plan de Pagos - {{ $credito->codigo_credito ?? $credito->numero_credito }}</title>
    <style>
        @page {
            margin: 0cm 0cm;
        }
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10px;
            color: #444;
            line-height: 1.5;
            margin-top: 3cm;
            margin-bottom: 2cm;
            margin-left: 2cm;
            margin-right: 2cm;
        }
        header {
            position: fixed;
            top: 0cm;
            left: 0cm;
            right: 0cm;
            height: 2.5cm;
            background-color: #f8f9fa;
            color: #333;
            text-align: center;
            line-height: 30px;
            border-bottom: 3px solid #2563eb; /* Azul moderno */
        }
        footer {
            position: fixed;
            bottom: 0cm;
            left: 0cm;
            right: 0cm;
            height: 1.5cm;
            background-color: #f8f9fa;
            color: #666;
            text-align: center;
            line-height: 1.5cm;
            border-top: 1px solid #ddd;
            font-size: 9px;
        }
        .brand-title {
            font-size: 20px;
            font-weight: bold;
            color: #1e293b;
            padding-top: 15px;
            text-transform: uppercase;
        }
        .brand-subtitle {
            font-size: 12px;
            color: #64748b;
        }
        .document-title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            color: #2563eb;
            margin-top: 10px;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .info-grid {
            display: table;
            width: 100%;
            margin-bottom: 25px;
            background-color: #fff;
            border-radius: 8px;
        }
        .info-col {
            display: table-cell;
            width: 50%;
            vertical-align: top;
            padding: 10px;
        }
        .info-item {
            margin-bottom: 8px;
        }
        .info-label {
            font-weight: bold;
            color: #64748b;
            font-size: 9px;
            text-transform: uppercase;
            display: block;
        }
        .info-value {
            color: #1e293b;
            font-weight: 500;
            font-size: 11px;
        }
        .amount-value {
            color: #2563eb;
            font-weight: bold;
            font-size: 12px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background-color: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        th {
            background-color: #f1f5f9;
            color: #475569;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 9px;
            padding: 10px 8px;
            border-bottom: 2px solid #e2e8f0;
            text-align: left;
        }
        td {
            padding: 8px;
            border-bottom: 1px solid #e2e8f0;
            color: #334155;
            vertical-align: middle;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        tr:nth-child(even) { background-color: #f8fafc; }

        .status-badge {
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 8px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .status-paid { background-color: #dcfce7; color: #166534; }
        .status-pending { background-color: #f1f5f9; color: #475569; }
        .status-overdue { background-color: #fee2e2; color: #991b1b; }

        .total-row td {
            background-color: #e2e8f0;
            font-weight: bold;
            color: #1e293b;
            border-top: 2px solid #cbd5e1;
        }

        .disclaimer {
            margin-top: 30px;
            font-size: 8px;
            color: #94a3b8;
            text-align: justify;
            font-style: italic;
        }
    </style>
</head>
<body>
    <header style="padding-bottom: 10px; border-bottom: 1px solid #ccc; margin-bottom: 15px;">
        <table width="100%">
            <tr>
                <td width="25%" style="text-align: left; vertical-align: middle;">
                    <img src="data:image/png;base64,{{ base64_encode(file_get_contents(resource_path('logos/avanza_logo.png'))) }}" alt="Logo" style="height: 80px;">
                </td>
                <td width="50%" style="text-align: center; vertical-align: middle;">
                    <div class="brand-title">{{ $sucursal->nombre ?? 'Avanza' }}</div>
                    <div class="brand-subtitle">{{ $sucursal->direccion ?? 'Sistema de Gestión de Créditos' }}</div>
                </td>
                <td width="25%"></td>
            </tr>
        </table>
    </header>

    <footer>
        Página <span class="pagenum"></span> - Generado el {{ $fechaGeneracion }}
    </footer>

    <h2 class="document-title">Plan de Pagos</h2>

    <div class="info-grid">
        <div class="info-col">
            <div class="info-item">
                <span class="info-label">Crédito No.</span>
                <span class="info-value">{{ $credito->codigo_credito ?? $credito->numero_credito }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Cliente</span>
                <span class="info-value">{{ $cliente->nombres ?? '' }} {{ $cliente->apellidos ?? '' }}</span>
            </div>
            @if(isset($cliente->dpi) && $cliente->dpi)
            <div class="info-item">
                <span class="info-label">Documento ID (DPI)</span>
                <span class="info-value">{{ $cliente->dpi }}</span>
            </div>
            @elseif(isset($cliente->numero_documento) && $cliente->numero_documento)
            <div class="info-item">
                <span class="info-label">Documento ID</span>
                <span class="info-value">{{ $cliente->numero_documento }}</span>
            </div>
            @endif
        </div>
        <div class="info-col">
             <div class="info-item">
                <span class="info-label">Monto Aprobado</span>
                <span class="info-value amount-value">Q {{ number_format($credito->monto_aprobado ?? $credito->monto_solicitado, 2, '.', ',') }}</span>
            </div>
            <div class="info-item">
                <span class="info-label">Tasa de Interés</span>
                <span class="info-value">{{ number_format($credito->tasa_interes ?? 0, 2) }}% Mensual</span>
            </div>
            @if(isset($credito->tipo_interes) && $credito->tipo_interes !== 'mensual')
            <div class="info-item">
                <span class="info-label">Frecuencia de Pago</span>
                <span class="info-value">{{ ucfirst($credito->tipo_interes ?? 'mensual') }}</span>
            </div>
            @endif
            <div class="info-item">
                <div style="display: inline-block; width: 45%;">
                    <span class="info-label">Plazo</span>
                    <span class="info-value">{{ $credito->numero_cuotas ?? 1 }} cuotas</span>
                </div>
                <div style="display: inline-block; width: 45%;">
                    @if($credito->fecha_desembolso)
                        <span class="info-label">Desembolso</span>
                        <span class="info-value">{{ \Carbon\Carbon::parse($credito->fecha_desembolso)->format('d/m/Y') }}</span>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="text-center" width="7%">No.</th>
                <th width="13%">Vencimiento</th>
                <th class="text-right" width="12%">Capital</th>
                <th class="text-right" width="12%">Interés</th>
                <th class="text-right" width="12%">Mora</th>
                <th class="text-right" width="12%">Otros</th>
                <th class="text-right" width="12%">Total</th>
                <th class="text-right" width="12%">Saldo</th>
                <th class="text-center" width="8%">Estado</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalCapital = 0;
                $totalInteres = 0;
                $totalMora = 0;
                $totalOtros = 0;
                $totalGeneral = 0;
                $saldoPendiente = $credito->monto_aprobado ?? $credito->monto_solicitado;
            @endphp
            @foreach($planPagos as $cuota)
                @php
                    $capital = $cuota->capital_proyectado ?? 0;
                    $interes = $cuota->interes_proyectado ?? 0;
                    $mora = $cuota->mora_proyectada ?? 0;
                    // Soportar tanto créditos reales (otros_cargos_proyectados) como simulaciones (otros_proyectados)
                    $otros = $cuota->otros_cargos_proyectados ?? $cuota->otros_proyectados ?? 0;
                    $totalCuota = $cuota->monto_cuota_proyectado ?? ($capital + $interes + $mora + $otros);

                    $totalCapital += $capital;
                    $totalInteres += $interes;
                    $totalMora += $mora;
                    $totalOtros += $otros;
                    $totalGeneral += $totalCuota;

                    // Calcular saldo restante después de esta cuota
                    $saldoDespuesCuota = $saldoPendiente - $capital;

                    $estadoClass = match($cuota->estado) {
                        'pagada' => 'status-paid',
                        'vencida' => 'status-overdue',
                        default => 'status-pending'
                    };
                @endphp
                <tr>
                    <td class="text-center">{{ $cuota->numero_cuota }}</td>
                    <td>{{ \Carbon\Carbon::parse($cuota->fecha_vencimiento)->format('d/m/Y') }}</td>
                    <td class="text-right">Q {{ number_format($capital, 2, '.', ',') }}</td>
                    <td class="text-right">Q {{ number_format($interes, 2, '.', ',') }}</td>
                    <td class="text-right">Q {{ number_format($mora, 2, '.', ',') }}</td>
                    <td class="text-right">Q {{ number_format($otros, 2, '.', ',') }}</td>
                    <td class="text-right amount-value" style="font-size: 11px;">Q {{ number_format($totalCuota, 2, '.', ',') }}</td>
                    <td class="text-right" style="font-weight: bold; color: {{ $saldoDespuesCuota <= 0 ? '#166534' : '#1e293b' }};">
                        Q {{ number_format($saldoDespuesCuota, 2, '.', ',') }}
                    </td>
                    <td class="text-center">
                        <span class="status-badge {{ $estadoClass }}">
                            {{ $cuota->estado }}
                        </span>
                    </td>
                </tr>
                @php
                    // Actualizar saldo para la siguiente iteración
                    $saldoPendiente = $saldoDespuesCuota;
                @endphp
            @endforeach
            <tr class="total-row">
                <td colspan="2" class="text-right">TOTALES</td>
                <td class="text-right">Q {{ number_format($totalCapital, 2, '.', ',') }}</td>
                <td class="text-right">Q {{ number_format($totalInteres, 2, '.', ',') }}</td>
                <td class="text-right">Q {{ number_format($totalMora, 2, '.', ',') }}</td>
                <td class="text-right">Q {{ number_format($totalOtros, 2, '.', ',') }}</td>
                <td class="text-right">Q {{ number_format($totalGeneral, 2, '.', ',') }}</td>
                <td class="text-right" style="color: #166534;">Q 0.00</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="disclaimer">
        <strong>Importante:</strong>
        <ul style="margin: 5px 0 0 20px; padding: 0;">
            <li><strong>Tasa de Interés:</strong> {{ number_format($credito->tasa_interes ?? 0, 2) }}% mensual aplicada {{ $credito->tipo_interes ?? 'mensualmente' }}. Los intereses se calculan sobre el monto total del crédito.</li>
            @if(isset($totalGastos) && $totalGastos > 0)
            <li><strong>Otros Cargos:</strong> Incluye gastos administrativos (Q {{ number_format($totalGastos, 2, '.', ',') }} total) distribuidos proporcionalmente en las cuotas.</li>
            @else
            <li><strong>Otros Cargos:</strong> No se aplicaron gastos adicionales a este crédito.</li>
            @endif
            <li><strong>Mora:</strong> Este documento es un plan proyectado. Los montos de mora pueden variar según la fecha real de pago.</li>
            <li>El pago puntual evita recargos adicionales y mantiene su buen historial crediticio.</li>
        </ul>
    </div>
</body>
</html>
