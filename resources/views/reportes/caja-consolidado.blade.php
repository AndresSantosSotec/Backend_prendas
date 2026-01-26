<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Consolidado de Cajas</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            margin: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
        }
        .header h1 {
            margin: 0;
            color: #333;
        }
        .estadisticas {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            text-align: center;
        }
        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 12px;
            color: #666;
        }
        .stat-card .value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background: #4A5568;
            color: white;
            padding: 8px;
            text-align: left;
            font-size: 10px;
        }
        td {
            border: 1px solid #ddd;
            padding: 6px;
            font-size: 10px;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9px;
            color: #666;
        }
        .estado-abierta {
            background: #4caf50;
            color: white;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 9px;
        }
        .estado-cerrada {
            background: #757575;
            color: white;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 9px;
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

    <div class="estadisticas">
        <div class="stat-card">
            <h3>Total Cajas</h3>
            <div class="value">{{ $estadisticas['total_cajas'] }}</div>
        </div>
        <div class="stat-card">
            <h3>Cajas Abiertas</h3>
            <div class="value" style="color: #4caf50;">{{ $estadisticas['cajas_abiertas'] }}</div>
        </div>
        <div class="stat-card">
            <h3>Cajas Cerradas</h3>
            <div class="value" style="color: #757575;">{{ $estadisticas['cajas_cerradas'] }}</div>
        </div>
        <div class="stat-card">
            <h3>Total Saldo Inicial</h3>
            <div class="value" style="font-size: 18px;">Q {{ number_format($estadisticas['total_saldo_inicial'], 2) }}</div>
        </div>
        <div class="stat-card">
            <h3>Total Saldo Final</h3>
            <div class="value" style="font-size: 18px;">Q {{ number_format($estadisticas['total_saldo_final'], 2) }}</div>
        </div>
        <div class="stat-card">
            <h3>Diferencia Total</h3>
            <div class="value" style="font-size: 18px; color: {{ $estadisticas['total_diferencia'] >= 0 ? '#4caf50' : '#f44336' }};">
                Q {{ number_format($estadisticas['total_diferencia'], 2) }}
            </div>
        </div>
    </div>

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
