<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte de Movimientos de Caja</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
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
        .info-caja {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th {
            background: #4A5568;
            color: white;
            padding: 10px;
            text-align: left;
        }
        td {
            border: 1px solid #ddd;
            padding: 8px;
        }
        tr:nth-child(even) {
            background: #f9f9f9;
        }
        .resumen {
            margin-top: 30px;
            background: #e8f5e9;
            padding: 15px;
            border-radius: 5px;
        }
        .resumen-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 10px;
            color: #666;
        }
        .ingreso {
            color: #2e7d32;
            font-weight: bold;
        }
        .egreso {
            color: #c62828;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>Reporte de Movimientos de Caja</h1>
        <p>{{ $caja->id }} - {{ \Carbon\Carbon::parse($caja->fecha_apertura)->format('d/m/Y') }}</p>
    </div>

    <div class="info-caja">
        <div class="info-row">
            <span><strong>Usuario:</strong> {{ $caja->user->name ?? 'N/A' }}</span>
            <span><strong>Estado:</strong> {{ ucfirst($caja->estado) }}</span>
        </div>
        <div class="info-row">
            <span><strong>Fecha Apertura:</strong> {{ \Carbon\Carbon::parse($caja->fecha_apertura)->format('d/m/Y H:i') }}</span>
            @if($caja->fecha_cierre)
            <span><strong>Fecha Cierre:</strong> {{ \Carbon\Carbon::parse($caja->fecha_cierre)->format('d/m/Y H:i') }}</span>
            @endif
        </div>
        <div class="info-row">
            <span><strong>Saldo Inicial:</strong> Q {{ number_format($caja->saldo_inicial, 2) }}</span>
            @if($caja->saldo_final)
            <span><strong>Saldo Final:</strong> Q {{ number_format($caja->saldo_final, 2) }}</span>
            @endif
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 80px;">Hora</th>
                <th>Concepto</th>
                <th style="width: 120px;">Tipo</th>
                <th style="width: 100px; text-align: right;">Ingresos</th>
                <th style="width: 100px; text-align: right;">Egresos</th>
            </tr>
        </thead>
        <tbody>
            @foreach($movimientos as $mov)
            <tr>
                <td>{{ \Carbon\Carbon::parse($mov->created_at)->format('H:i:s') }}</td>
                <td>{{ $mov->concepto }}</td>
                <td>
                    @if(in_array($mov->tipo, ['incremento', 'ingreso_pago']))
                        Ingreso
                    @else
                        Egreso
                    @endif
                </td>
                <td style="text-align: right;">
                    @if(in_array($mov->tipo, ['incremento', 'ingreso_pago']))
                        <span class="ingreso">Q {{ number_format($mov->monto, 2) }}</span>
                    @endif
                </td>
                <td style="text-align: right;">
                    @if(in_array($mov->tipo, ['decremento', 'egreso_desembolso']))
                        <span class="egreso">Q {{ number_format($mov->monto, 2) }}</span>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="resumen">
        <div class="resumen-row">
            <span>Total Ingresos:</span>
            <span class="ingreso">Q {{ number_format($totalIngresos, 2) }}</span>
        </div>
        <div class="resumen-row">
            <span>Total Egresos:</span>
            <span class="egreso">Q {{ number_format($totalEgresos, 2) }}</span>
        </div>
        <div class="resumen-row" style="border-top: 2px solid #333; padding-top: 10px; margin-top: 10px;">
            <span>Saldo Neto:</span>
            <span>Q {{ number_format($saldoNeto, 2) }}</span>
        </div>
    </div>

    <div class="footer">
        <p>Generado el {{ $fecha_generacion }}</p>
    </div>
</body>
</html>
