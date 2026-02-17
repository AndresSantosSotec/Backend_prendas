<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Movimientos de Bóveda - {{ $boveda->codigo }}</title>
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
            font-size: 12px;
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
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border: 1px solid #000;
            font-size: 9px;
            font-weight: bold;
        }
        .badge-entrada,
        .badge-salida,
        .badge-aprobado,
        .badge-pendiente {
            background-color: #f5f5f5;
            color: #000;
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
            padding: 10px;
            background-color: #f5f5f5;
            border: 2px solid #000;
        }
        .summary-row {
            overflow: hidden;
            margin-bottom: 5px;
            padding: 4px 0;
            font-size: 10px;
        }
        .summary-row strong:first-child {
            float: left;
        }
        .summary-row strong:last-child {
            float: right;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>REPORTE DE MOVIMIENTOS DE BÓVEDA</h1>
        <h2>{{ $boveda->nombre }} ({{ $boveda->codigo }})</h2>
    </div>

    <div class="info-section">
        <div class="info-row">
            <span><span class="info-label">Sucursal:</span> {{ $boveda->sucursal->nombre ?? 'N/A' }}</span>
            <span><span class="info-label">Saldo Actual:</span> Q{{ number_format($boveda->saldo_actual, 2) }}</span>
        </div>
        @if($periodo['inicio'] || $periodo['fin'])
        <div class="info-row">
            <span><span class="info-label">Período:</span>
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
            </span>
            <span><span class="info-label">Fecha de Generación:</span> {{ $fecha_generacion }}</span>
        </div>
        @endif
        <div class="info-row">
            <span><span class="info-label">Generado por:</span> {{ $usuario }}</span>
            <span><span class="info-label">Total Movimientos:</span> {{ $movimientos->count() }}</span>
        </div>
    </div>

    @if($movimientos->count() > 0)
    <table>
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Concepto</th>
                <th class="text-right">Monto</th>
                <th>Usuario</th>
                <th class="text-center">Estado</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalEntradas = 0;
                $totalSalidas = 0;
            @endphp
            @foreach($movimientos as $mov)
            @php
                $esEntrada = in_array($mov->tipo_movimiento, ['entrada', 'transferencia_entrada']);
                if($esEntrada) {
                    $totalEntradas += $mov->monto;
                } else {
                    $totalSalidas += $mov->monto;
                }
            @endphp
            <tr>
                <td>{{ $mov->created_at->format('d/m/Y H:i') }}</td>
                <td>
                    <span class="badge {{ $esEntrada ? 'badge-entrada' : 'badge-salida' }}">
                        {{ $esEntrada ? 'Entrada' : 'Salida' }}
                    </span>
                </td>
                <td>{{ $mov->concepto }}</td>
                <td class="text-right">Q{{ number_format($mov->monto, 2) }}</td>
                <td>{{ $mov->usuario->name ?? 'N/A' }}</td>
                <td class="text-center">
                    <span class="badge badge-{{ $mov->estado }}">
                        {{ ucfirst($mov->estado) }}
                    </span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        <div class="summary-row">
            <strong>Total Entradas:</strong>
            <strong>Q{{ number_format($totalEntradas, 2) }}</strong>
        </div>
        <div class="summary-row">
            <strong>Total Salidas:</strong>
            <strong>Q{{ number_format($totalSalidas, 2) }}</strong>
        </div>
        <div class="summary-row">
            <strong>Movimiento Neto:</strong>
            <strong>Q{{ number_format($totalEntradas - $totalSalidas, 2) }}</strong>
        </div>
    </div>
    @else
    <p style="text-align: center; padding: 40px; color: #999;">No hay movimientos registrados en este período</p>
    @endif

    <div class="footer">
        <p>DigiPrenda - Sistema de Gestión de Empeños</p>
        <p>Documento generado el {{ $fecha_generacion }}</p>
    </div>
</body>
</html>
