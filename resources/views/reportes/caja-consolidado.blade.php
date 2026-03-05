<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Consolidado de Cajas</title>
    <style>
        body {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            margin: 15px;
            color: #000;
            line-height: 1.3;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
            border: 2px solid #000;
            padding: 10px;
        }
        .header h1 {
            margin: 0;
            color: #000;
            font-size: 18px;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .header p {
            margin-top: 5px;
            font-size: 10px;
        }
        .stat-card {
            background: #f5f5f5;
            padding: 12px;
            border: 2px solid #000;
            text-align: center;
        }
        .stat-card h3 {
            margin: 0 0 8px 0;
            font-size: 9px;
            color: #000;
            font-weight: bold;
            text-transform: uppercase;
        }
        .stat-card .value {
            font-size: 12px;
            font-weight: bold;
            color: #000;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 15px;
        }
        th {
            background: #000;
            color: white;
            padding: 8px;
            text-align: left;
            font-size: 10px;
            border: 1px solid #000;
        }
        td {
            border: 1px solid #000;
            padding: 6px;
            font-size: 10px;
        }
        tr:nth-child(even) {
            background: #f5f5f5;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 9px;
            color: #000;
            border-top: 1px solid #000;
            padding-top: 8px;
        }
        .estado-abierta {
            background: #f5f5f5;
            color: #000;
            padding: 3px 8px;
            border: 1px solid #000;
            font-size: 9px;
            font-weight: bold;
        }
        .estado-cerrada {
            background: #e0e0e0;
            color: #000;
            padding: 3px 8px;
            border: 1px solid #000;
            font-size: 9px;
            font-weight: bold;
        }
        .page-break { page-break-before: always; }
        .caja-detail-header {
            background: #333;
            color: white;
            padding: 8px 12px;
            margin-top: 20px;
            margin-bottom: 0;
            font-size: 11px;
            font-weight: bold;
        }
        .caja-summary {
            background: #f0f0f0;
            border: 1px solid #000;
            border-top: none;
            padding: 8px 12px;
            margin-bottom: 5px;
        }
        .caja-summary span { margin-right: 20px; font-size: 10px; }
        .mov-table { margin-top: 0; }
        .mov-table th { background: #555; font-size: 9px; padding: 5px; }
        .mov-table td { font-size: 9px; padding: 4px; }
        .tipo-incremento { color: #2e7d32; font-weight: bold; }
        .tipo-decremento { color: #c62828; font-weight: bold; }
        .tipo-ingreso_pago { color: #1565c0; font-weight: bold; }
        .tipo-egreso_desembolso { color: #e65100; font-weight: bold; }
        .separator { border-top: 2px dashed #999; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="header">
        <h1>CONSOLIDADO DE CAJAS</h1>
        @if(($fecha_inicio ?? null) || ($fecha_fin ?? null))
        <p>
            @if(($fecha_inicio ?? null) && ($fecha_fin ?? null))
                Período: {{ \Carbon\Carbon::parse($fecha_inicio)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($fecha_fin)->format('d/m/Y') }}
            @elseif($fecha_inicio ?? null)
                Desde: {{ \Carbon\Carbon::parse($fecha_inicio)->format('d/m/Y') }}
            @else
                Hasta: {{ \Carbon\Carbon::parse($fecha_fin)->format('d/m/Y') }}
            @endif
        </p>
        @else
        <p>Todas las cajas</p>
        @endif
    </div>

    {{-- Estadísticas principales --}}
    <table style="width:100%; border-collapse: collapse; margin-bottom:15px;">
        <tr>
            <td style="width:25%; padding:5px;">
                <div class="stat-card">
                    <h3>Total Cajas</h3>
                    <div class="value">{{ $estadisticas['total_cajas'] }}</div>
                </div>
            </td>
            <td style="width:25%; padding:5px;">
                <div class="stat-card">
                    <h3>Abiertas</h3>
                    <div class="value">{{ $estadisticas['cajas_abiertas'] }}</div>
                </div>
            </td>
            <td style="width:25%; padding:5px;">
                <div class="stat-card">
                    <h3>Cerradas</h3>
                    <div class="value">{{ $estadisticas['cajas_cerradas'] }}</div>
                </div>
            </td>
            <td style="width:25%; padding:5px;">
                <div class="stat-card">
                    <h3>Total Movimientos</h3>
                    <div class="value">{{ $estadisticas['total_movimientos'] ?? 0 }}</div>
                </div>
            </td>
        </tr>
        <tr>
            <td style="width:25%; padding:5px;">
                <div class="stat-card">
                    <h3>Total Saldo Inicial</h3>
                    <div class="value">Q {{ number_format($estadisticas['total_saldo_inicial'], 2) }}</div>
                </div>
            </td>
            <td style="width:25%; padding:5px;">
                <div class="stat-card">
                    <h3>Total Ingresos</h3>
                    <div class="value" style="color: #2e7d32;">Q {{ number_format($estadisticas['total_ingresos'] ?? 0, 2) }}</div>
                </div>
            </td>
            <td style="width:25%; padding:5px;">
                <div class="stat-card">
                    <h3>Total Egresos</h3>
                    <div class="value" style="color: #c62828;">Q {{ number_format($estadisticas['total_egresos'] ?? 0, 2) }}</div>
                </div>
            </td>
            <td style="width:25%; padding:5px;">
                <div class="stat-card">
                    <h3>Diferencia Total</h3>
                    <div class="value">Q {{ number_format($estadisticas['total_diferencia'], 2) }}</div>
                </div>
            </td>
        </tr>
    </table>

    {{-- Tabla resumen de cajas --}}
    <table>
        <thead>
            <tr>
                <th style="width: 40px;">ID</th>
                <th>Usuario</th>
                <th>Sucursal</th>
                <th style="width: 80px;">F. Apertura</th>
                <th style="width: 80px;">F. Cierre</th>
                <th style="width: 65px; text-align: right;">S. Inicial</th>
                <th style="width: 65px; text-align: right;">Ingresos</th>
                <th style="width: 65px; text-align: right;">Egresos</th>
                <th style="width: 65px; text-align: right;">Esperado</th>
                <th style="width: 65px; text-align: right;">S. Final</th>
                <th style="width: 55px; text-align: right;">Dif.</th>
                <th style="width: 55px; text-align: center;">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cajas as $caja)
            <tr>
                <td>{{ $caja->id }}</td>
                <td>{{ $caja->user->name ?? 'N/A' }}</td>
                <td>{{ $caja->sucursal->nombre ?? '-' }}</td>
                <td>{{ \Carbon\Carbon::parse($caja->fecha_apertura)->format('d/m/Y') }}</td>
                <td>{{ $caja->fecha_cierre ? \Carbon\Carbon::parse($caja->fecha_cierre)->format('d/m/Y') : '-' }}</td>
                <td style="text-align: right;">Q {{ number_format($caja->saldo_inicial, 2) }}</td>
                <td style="text-align: right; color: #2e7d32;">Q {{ number_format($caja->total_ingresos ?? 0, 2) }}</td>
                <td style="text-align: right; color: #c62828;">Q {{ number_format($caja->total_egresos ?? 0, 2) }}</td>
                <td style="text-align: right;">Q {{ number_format($caja->total_esperado ?? $caja->saldo_inicial, 2) }}</td>
                <td style="text-align: right;">{{ $caja->saldo_final ? 'Q ' . number_format($caja->saldo_final, 2) : '-' }}</td>
                <td style="text-align: right; color: {{ ($caja->diferencia ?? 0) >= 0 ? '#2e7d32' : '#c62828' }};">
                    {{ $caja->diferencia !== null ? 'Q ' . number_format($caja->diferencia, 2) : '-' }}
                </td>
                <td style="text-align: center;">
                    <span class="estado-{{ $caja->estado }}">{{ ucfirst($caja->estado) }}</span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    {{-- Detalle de movimientos por caja (solo si incluir_movimientos = true) --}}
    @if($incluir_movimientos ?? false)
        <div class="separator"></div>
        <h2 style="text-align: center; font-size: 14px; margin: 15px 0 5px;">DETALLE DE MOVIMIENTOS POR CAJA</h2>

        @foreach($cajas as $caja)
            @if($caja->movimientos && $caja->movimientos->count() > 0)
            <div class="caja-detail-header">
                CAJA #{{ $caja->id }} &mdash; {{ $caja->user->name ?? 'N/A' }}
                @if($caja->sucursal) | {{ $caja->sucursal->nombre }} @endif
                | {{ \Carbon\Carbon::parse($caja->fecha_apertura)->format('d/m/Y') }}
                | Estado: {{ ucfirst($caja->estado) }}
            </div>
            <div class="caja-summary">
                <span><strong>Saldo Inicial:</strong> Q {{ number_format($caja->saldo_inicial, 2) }}</span>
                <span><strong>Ingresos:</strong> <span style="color:#2e7d32">Q {{ number_format($caja->total_ingresos ?? 0, 2) }}</span></span>
                <span><strong>Egresos:</strong> <span style="color:#c62828">Q {{ number_format($caja->total_egresos ?? 0, 2) }}</span></span>
                <span><strong>Esperado:</strong> Q {{ number_format($caja->total_esperado ?? $caja->saldo_inicial, 2) }}</span>
                @if($caja->saldo_final)
                <span><strong>S. Final:</strong> Q {{ number_format($caja->saldo_final, 2) }}</span>
                <span><strong>Dif:</strong> Q {{ number_format($caja->diferencia ?? 0, 2) }}</span>
                @endif
            </div>
            <table class="mov-table">
                <thead>
                    <tr>
                        <th style="width: 50px;">#</th>
                        <th style="width: 70px;">Hora</th>
                        <th>Concepto</th>
                        <th style="width: 90px;">Tipo</th>
                        <th style="width: 80px; text-align: right;">Monto</th>
                        <th>Registrado por</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($caja->movimientos->sortBy('created_at') as $index => $mov)
                    <tr>
                        <td>{{ $index + 1 }}</td>
                        <td>{{ \Carbon\Carbon::parse($mov->created_at)->format('H:i:s') }}</td>
                        <td>{{ $mov->concepto }}</td>
                        <td>
                            <span class="tipo-{{ $mov->tipo }}">
                                @switch($mov->tipo)
                                    @case('incremento') + INGRESO @break
                                    @case('decremento') - EGRESO @break
                                    @case('ingreso_pago') + PAGO @break
                                    @case('egreso_desembolso') - DESEMBOLSO @break
                                    @default {{ strtoupper($mov->tipo) }}
                                @endswitch
                            </span>
                        </td>
                        <td style="text-align: right;">
                            <span class="tipo-{{ $mov->tipo }}">Q {{ number_format($mov->monto, 2) }}</span>
                        </td>
                        <td>{{ $mov->user->name ?? 'Sistema' }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
            @endif
        @endforeach
    @endif

    <div class="footer">
        <p>Generado el {{ $fecha_generacion }} | Reporte exclusivo para administradores | DigiPrenda &copy;</p>
    </div>
</body>
</html>
