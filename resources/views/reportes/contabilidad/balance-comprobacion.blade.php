<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Balance de Comprobación</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 10px;
            line-height: 1.3;
            color: #000;
            padding: 15px;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .header h1 {
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 3px;
        }
        .header h2 {
            font-size: 12px;
            font-weight: normal;
            margin-bottom: 3px;
        }
        .header .periodo {
            font-size: 10px;
        }
        .meta-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 9px;
        }
        .balance-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .balance-table th,
        .balance-table td {
            border: 1px solid #000;
            padding: 5px;
            font-size: 9px;
        }
        .balance-table th {
            background-color: #333;
            color: #fff;
            font-weight: bold;
            text-align: center;
        }
        .balance-table .cuenta {
            width: 12%;
        }
        .balance-table .nombre {
            width: 36%;
        }
        .balance-table .tipo {
            width: 12%;
            text-align: center;
        }
        .balance-table .monto {
            width: 13%;
            text-align: right;
        }
        .balance-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .balance-table .totales-row {
            background-color: #e0e0e0;
            font-weight: bold;
        }
        .balance-table .saldo-deudor {
            color: #0066cc;
        }
        .balance-table .saldo-acreedor {
            color: #cc0066;
        }
        .resumen-box {
            border: 2px solid #000;
            padding: 15px;
            margin-top: 20px;
        }
        .resumen-box h3 {
            font-size: 12px;
            margin-bottom: 10px;
            text-align: center;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
        }
        .resumen-row {
            display: flex;
            justify-content: space-between;
            padding: 4px 0;
            border-bottom: 1px dotted #ccc;
        }
        .resumen-row:last-child {
            border-bottom: none;
        }
        .resumen-total {
            font-weight: bold;
            font-size: 11px;
            background-color: #f0f0f0;
            padding: 5px;
            margin-top: 10px;
        }
        .cuadrado {
            color: green;
            font-weight: bold;
        }
        .no-cuadrado {
            color: red;
            font-weight: bold;
        }
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #000;
            font-size: 8px;
            text-align: center;
        }
        .text-right {
            text-align: right;
        }
        .text-center {
            text-align: center;
        }
        .tipo-activo { color: #006600; }
        .tipo-pasivo { color: #660000; }
        .tipo-patrimonio { color: #000066; }
        .tipo-ingreso { color: #006666; }
        .tipo-gasto { color: #666600; }
        .tipo-costos { color: #660066; }
    </style>
</head>
<body>
    <div class="header">
        <h1>BALANCE DE COMPROBACIÓN</h1>
        <h2>Sistema de Gestión de Empeños</h2>
        <div class="periodo">
            Del {{ \Carbon\Carbon::parse($fechaInicio)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($fechaFin)->format('d/m/Y') }}
        </div>
    </div>

    <div class="meta-info">
        <span>Generado por: {{ $generado_por }}</span>
        <span>Fecha: {{ $fecha_generacion }}</span>
    </div>

    <table class="balance-table">
        <thead>
            <tr>
                <th class="cuenta">CÓDIGO</th>
                <th class="nombre">NOMBRE DE LA CUENTA</th>
                <th class="tipo">TIPO</th>
                <th class="monto">DEBE</th>
                <th class="monto">HABER</th>
                <th class="monto">SALDO</th>
            </tr>
        </thead>
        <tbody>
            @php
                $totalDeudor = 0;
                $totalAcreedor = 0;
            @endphp

            @foreach($cuentas as $cuenta)
            @php
                $saldo = $cuenta->total_debe - $cuenta->total_haber;
                if ($saldo > 0) {
                    $totalDeudor += $saldo;
                } else {
                    $totalAcreedor += abs($saldo);
                }
            @endphp
            <tr>
                <td>{{ $cuenta->codigo_cuenta }}</td>
                <td>{{ $cuenta->nombre_cuenta }}</td>
                <td class="text-center tipo-{{ $cuenta->tipo }}">{{ ucfirst($cuenta->tipo) }}</td>
                <td class="text-right">{{ number_format($cuenta->total_debe, 2) }}</td>
                <td class="text-right">{{ number_format($cuenta->total_haber, 2) }}</td>
                <td class="text-right {{ $saldo >= 0 ? 'saldo-deudor' : 'saldo-acreedor' }}">
                    {{ number_format(abs($saldo), 2) }} {{ $saldo >= 0 ? 'D' : 'A' }}
                </td>
            </tr>
            @endforeach

            <tr class="totales-row">
                <td colspan="3" class="text-right">TOTALES:</td>
                <td class="text-right">Q {{ number_format($totales['debe'], 2) }}</td>
                <td class="text-right">Q {{ number_format($totales['haber'], 2) }}</td>
                <td class="text-center {{ $totales['diferencia'] < 0.01 ? 'cuadrado' : 'no-cuadrado' }}">
                    {{ $totales['diferencia'] < 0.01 ? 'CUADRA' : 'NO CUADRA' }}
                </td>
            </tr>
        </tbody>
    </table>

    <div class="resumen-box">
        <h3>RESUMEN DEL BALANCE</h3>

        <div class="resumen-row">
            <span>Total de cuentas con movimiento:</span>
            <strong>{{ count($cuentas) }}</strong>
        </div>

        <div class="resumen-row">
            <span>Total movimientos DEBE:</span>
            <strong>Q {{ number_format($totales['debe'], 2) }}</strong>
        </div>

        <div class="resumen-row">
            <span>Total movimientos HABER:</span>
            <strong>Q {{ number_format($totales['haber'], 2) }}</strong>
        </div>

        <div class="resumen-row">
            <span>Diferencia:</span>
            <strong class="{{ $totales['diferencia'] < 0.01 ? 'cuadrado' : 'no-cuadrado' }}">
                Q {{ number_format($totales['diferencia'], 2) }}
            </strong>
        </div>

        <div class="resumen-total text-center">
            @if($totales['diferencia'] < 0.01)
                ✓ EL BALANCE CUADRA CORRECTAMENTE
            @else
                ✗ ATENCIÓN: EL BALANCE NO CUADRA - REVISAR ASIENTOS
            @endif
        </div>
    </div>

    <div class="footer">
        <p>Documento generado automáticamente - Sistema de Gestión de Empeños</p>
        <p>D = Saldo Deudor | A = Saldo Acreedor</p>
        <p>Este documento es válido sin firma ni sello</p>
    </div>
</body>
</html>
