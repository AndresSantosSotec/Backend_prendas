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
            font-family: Arial, sans-serif;
            font-size: 9pt;
            color: #333;
        }
        .header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #D32F2F;
        }
        .header h1 {
            color: #D32F2F;
            font-size: 18pt;
            margin-bottom: 5px;
        }
        .header h2 {
            color: #555;
            font-size: 12pt;
            font-weight: normal;
        }
        .info-section {
            margin-bottom: 15px;
            padding: 10px;
            background-color: #f5f5f5;
            border-radius: 4px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
        }
        .info-label {
            font-weight: bold;
            color: #666;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        thead {
            background-color: #D32F2F;
            color: white;
        }
        th {
            padding: 8px 5px;
            text-align: left;
            font-size: 8pt;
            border: 1px solid #ddd;
        }
        td {
            padding: 6px 5px;
            border: 1px solid #ddd;
            font-size: 8pt;
        }
        tbody tr:nth-child(even) {
            background-color: #f9f9f9;
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
            border-top: 1px solid #ddd;
            text-align: center;
            font-size: 8pt;
            color: #777;
        }
        .summary {
            margin-top: 15px;
            padding: 15px;
            background-color: #ffebee;
            border-left: 4px solid #D32F2F;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }
        .summary-item {
            padding: 8px;
            background-color: white;
            border-radius: 4px;
        }
        .summary-label {
            font-size: 9pt;
            color: #666;
            margin-bottom: 3px;
        }
        .summary-value {
            font-size: 14pt;
            font-weight: bold;
            color: #D32F2F;
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
        <h3 style="margin-bottom: 15px; color: #D32F2F;">Resumen General</h3>
        <div class="summary-grid">
            <div class="summary-item">
                <div class="summary-label">Total Bóvedas Activas</div>
                <div class="summary-value">{{ $totales['total_bovedas'] }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Saldo Total Consolidado</div>
                <div class="summary-value">Q{{ number_format($totales['saldo_consolidado'], 2) }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Total de Entradas</div>
                <div class="summary-value">Q{{ number_format($totales['total_entradas'], 2) }}</div>
            </div>
            <div class="summary-item">
                <div class="summary-label">Total de Salidas</div>
                <div class="summary-value">Q{{ number_format($totales['total_salidas'], 2) }}</div>
            </div>
        </div>
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
