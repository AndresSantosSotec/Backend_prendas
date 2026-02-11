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
    </style>
</head>
<body>
    <div class="header">
        <h1>Consolidado de Cajas</h1>
        @if($fecha_inicio || $fecha_fin)
        <p>
            @if($fecha_inicio && $fecha_fin)
                Período: {{ \Carbon\Carbon::parse($fecha_inicio)->format('d/m/Y') }} - {{ \Carbon\Carbon::parse($fecha_fin)->format('d/m/Y') }}
            @elseif($fecha_inicio)
                Desde: {{ \Carbon\Carbon::parse($fecha_inicio)->format('d/m/Y') }}
            @else
                Hasta: {{ \Carbon\Carbon::parse($fecha_fin)->format('d/m/Y') }}
            @endif
        </p>
        @else
        <p>Todas las cajas</p>
        @endif
    </div>

    <table style="width:100%; border-collapse: collapse; margin-bottom:15px;">
        <tr>
            <td style="width:33.33%; padding:5px;">
                <div class="stat-card">
                    <h3>Total Cajas</h3>
                    <div class="value">{{ $estadisticas['total_cajas'] }}</div>
                </div>
            </td>
            <td style="width:33.33%; padding:5px;">
                <div class="stat-card">
                    <h3>Cajas Abiertas</h3>
                    <div class="value">{{ $estadisticas['cajas_abiertas'] }}</div>
                </div>
            </td>
            <td style="width:33.33%; padding:5px;">
                <div class="stat-card">
                    <h3>Cajas Cerradas</h3>
                    <div class="value">{{ $estadisticas['cajas_cerradas'] }}</div>
                </div>
            </td>
        </tr>
        <tr>
            <td style="width:33.33%; padding:5px;">
                <div class="stat-card">
                    <h3>Total Saldo Inicial</h3>
                    <div class="value" style="font-size: 14px;">Q {{ number_format($estadisticas['total_saldo_inicial'], 2) }}</div>
                </div>
            </td>
            <td style="width:33.33%; padding:5px;">
                <div class="stat-card">
                    <h3>Total Saldo Final</h3>
                    <div class="value" style="font-size: 14px;">Q {{ number_format($estadisticas['total_saldo_final'], 2) }}</div>
                </div>
            </td>
            <td style="width:33.33%; padding:5px;">
                <div class="stat-card">
                    <h3>Diferencia Total</h3>
                    <div class="value" style="font-size: 14px;">
                        Q {{ number_format($estadisticas['total_diferencia'], 2) }}
                    </div>
                </div>
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width: 60px;">ID</th>
                <th>Usuario</th>
                <th style="width: 90px;">F. Apertura</th>
                <th style="width: 90px;">F. Cierre</th>
                <th style="width: 70px; text-align: right;">S. Inicial</th>
                <th style="width: 70px; text-align: right;">S. Final</th>
                <th style="width: 70px; text-align: right;">Diferencia</th>
                <th style="width: 70px; text-align: center;">Estado</th>
            </tr>
        </thead>
        <tbody>
            @foreach($cajas as $caja)
            <tr>
                <td>{{ $caja->id }}</td>
                <td>{{ $caja->user->name ?? 'N/A' }}</td>
                <td>{{ \Carbon\Carbon::parse($caja->fecha_apertura)->format('d/m/Y H:i') }}</td>
                <td>{{ $caja->fecha_cierre ? \Carbon\Carbon::parse($caja->fecha_cierre)->format('d/m/Y H:i') : '-' }}</td>
                <td style="text-align: right;">Q {{ number_format($caja->saldo_inicial, 2) }}</td>
                <td style="text-align: right;">{{ $caja->saldo_final ? 'Q ' . number_format($caja->saldo_final, 2) : '-' }}</td>
                <td style="text-align: right; color: {{ ($caja->diferencia ?? 0) >= 0 ? '#4caf50' : '#f44336' }};">
                    {{ $caja->diferencia !== null ? 'Q ' . number_format($caja->diferencia, 2) : '-' }}
                </td>
                <td style="text-align: center;">
                    <span class="estado-{{ $caja->estado }}">{{ ucfirst($caja->estado) }}</span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="footer">
        <p>Generado el {{ $fecha_generacion }} | Este es un reporte exclusivo para administradores</p>
    </div>
</body>
</html>
