<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Reporte de Movimientos de Caja</title>
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
        .info-caja {
            background: #f5f5f5;
            padding: 12px;
            border: 2px solid #000;
            margin-bottom: 15px;
        }
        .info-row {
            overflow: hidden;
            margin-bottom: 8px;
        }
        .info-row span {
            display: inline-block;
            margin-right: 20px;
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
            border: 1px solid #000;
            font-size: 10px;
        }
        td {
            border: 1px solid #000;
            padding: 8px;
            font-size: 10px;
        }
        tr:nth-child(even) {
            background: #f5f5f5;
        }
        .resumen {
            margin-top: 20px;
            background: #f5f5f5;
            padding: 12px;
            border: 2px solid #000;
        }
        .resumen-row {
            overflow: hidden;
            margin-bottom: 8px;
            padding: 4px 0;
            font-size: 10px;
        }
        .resumen-row span:first-child {
            float: left;
            font-weight: bold;
        }
        .resumen-row span:last-child {
            float: right;
            font-weight: bold;
        }
        .resumen-row.total {
            border-top: 2px solid #000;
            padding-top: 10px;
            margin-top: 10px;
            font-size: 11px;
        }
        .footer {
            margin-top: 20px;
            text-align: center;
            font-size: 9px;
            color: #000;
            border-top: 1px solid #000;
            padding-top: 8px;
        }
        .ingreso {
            color: #000;
            font-weight: bold;
        }
        .egreso {
            color: #000;
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
        <div class="resumen-row total">
            <span>Saldo Neto:</span>
            <span>Q {{ number_format($saldoNeto, 2) }}</span>
        </div>
    </div>

    <div class="footer">
        <p>Generado el {{ $fecha_generacion }}</p>
    </div>
</body>
</html>
