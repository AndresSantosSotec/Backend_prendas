<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Consolidación de Bóvedas</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            color: #000;
            line-height: 1.3;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border: 2px solid #000;
            padding: 10px;
        }
        .header h1 {
            color: #000;
            font-size: 18px;
            margin-bottom: 5px;
            font-weight: bold;
            letter-spacing: 1px;
        }
        .header h2 {
            color: #000;
            font-size: 11px;
            font-weight: normal;
        }
        .info-section {
            margin-bottom: 12px;
            padding: 10px;
            background-color: #f5f5f5;
            border: 2px solid #000;
        }
        .info-row {
            overflow: hidden;
            margin-bottom: 5px;
        }
        .info-row span {
            display: inline-block;
            margin-right: 20px;
        }
        .info-label {
            font-weight: bold;
            color: #000;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        thead {
            background-color: #000;
            color: white;
        }
        th {
            padding: 8px 5px;
            text-align: left;
            font-size: 10px;
            border: 1px solid #000;
        }
        td {
            padding: 6px 5px;
            border: 1px solid #000;
            font-size: 10px;
        }
        tbody tr:nth-child(even) {
            background-color: #f5f5f5;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .footer {
            margin-top: 20px;
            padding-top: 10px;
            border-top: 1px solid #000;
            text-align: center;
            font-size: 9px;
            color: #000;
        }
        .summary {
            margin-top: 15px;
            padding: 15px;
            background-color: #f5f5f5;
            border: 2px solid #000;
        }
        .summary h3 {
            margin-bottom: 15px;
            color: #000;
            font-size: 12px;
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
        }
        .summary-item {
            padding: 10px;
            background-color: white;
            border: 1px solid #000;
            text-align: center;
        }
        .summary-label {
            font-size: 9px;
            color: #000;
            margin-bottom: 5px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .summary-value {
            font-size: 11px;
            font-weight: bold;
            color: #000;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>CONSOLIDACIÓN DE BÓVEDAS</h1>
        @if($periodo['inicio'] || $periodo['fin'])
        <h2>
            Período:
            @if($periodo['inicio'])
                {{ \Carbon\Carbon::parse($periodo['inicio'])->format('d/m/Y') }}
            @else
                Inicio
            @endif
            al
            @if($periodo['fin'])
                {{ \Carbon\Carbon::parse($periodo['fin'])->format('d/m/Y') }}
            @else
                {{ now()->format('d/m/Y') }}
            @endif
        </h2>
        @endif
    </div>

    <div class="info-section">
        <div class="info-row">
            <span><span class="info-label">Generado por:</span> {{ $usuario }}</span>
            <span><span class="info-label">Fecha de Generación:</span> {{ $fecha_generacion }}</span>
        </div>
        <div class="info-row">
            <span><span class="info-label">Total de Bóvedas:</span> {{ $totales['total_bovedas'] }}</span>
            <span><span class="info-label">Saldo Consolidado:</span> Q{{ number_format($totales['saldo_consolidado'], 2) }}</span>
        </div>
    </div>

    @if($consolidacion->count() > 0)
    <table>
        <thead>
            <tr>
                <th>Código</th>
                <th>Bóveda</th>
                <th>Sucursal</th>
                <th>Tipo</th>
                <th class="text-right">Entradas</th>
                <th class="text-right">Salidas</th>
                <th class="text-center">Movs.</th>
                <th class="text-right">Saldo Actual</th>
            </tr>
        </thead>
        <tbody>
            @foreach($consolidacion as $item)
            <tr>
                <td>{{ $item['boveda']->codigo }}</td>
                <td>{{ $item['boveda']->nombre }}</td>
                <td>{{ $item['boveda']->sucursal->nombre ?? 'N/A' }}</td>
                <td>{{ ucfirst($item['boveda']->tipo) }}</td>
                <td class="text-right">Q{{ number_format($item['total_entradas'], 2) }}</td>
                <td class="text-right">Q{{ number_format($item['total_salidas'], 2) }}</td>
                <td class="text-center">{{ $item['numero_movimientos'] }}</td>
                <td class="text-right"><strong>Q{{ number_format($item['saldo_actual'], 2) }}</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        <h3>Resumen General</h3>
        <table style="width:100%; border-collapse: collapse;">
            <tr>
                <td style="width:50%; padding:5px;">
                    <div class="summary-item">
                        <div class="summary-label">Total Bovedas Activas</div>
                        <div class="summary-value">{{ $totales['total_bovedas'] }}</div>
                    </div>
                </td>
                <td style="width:50%; padding:5px;">
                    <div class="summary-item">
                        <div class="summary-label">Saldo Total Consolidado</div>
                        <div class="summary-value">Q{{ number_format($totales['saldo_consolidado'], 2) }}</div>
                    </div>
                </td>
            </tr>
            <tr>
                <td style="width:50%; padding:5px;">
                    <div class="summary-item">
                        <div class="summary-label">Total de Entradas</div>
                        <div class="summary-value">Q{{ number_format($totales['total_entradas'], 2) }}</div>
                    </div>
                </td>
                <td style="width:50%; padding:5px;">
                    <div class="summary-item">
                        <div class="summary-label">Total de Salidas</div>
                        <div class="summary-value">Q{{ number_format($totales['total_salidas'], 2) }}</div>
                    </div>
                </td>
            </tr>
        </table>
    </div>
    @else
    <p style="text-align: center; padding: 40px; color: #999;">No hay bóvedas registradas</p>
    @endif

    <div class="footer">
        <p>MicroSystem Plus - Sistema de Gestión de Empeños</p>
        <p>Documento generado el {{ $fecha_generacion }}</p>
    </div>
</body>
</html>
