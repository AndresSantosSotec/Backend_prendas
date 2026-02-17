<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Libro Mayor</title>
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
        .cuenta-info {
            background-color: #f0f0f0;
            border: 2px solid #000;
            padding: 10px;
            margin-bottom: 15px;
        }
        .cuenta-info h3 {
            font-size: 14px;
            margin-bottom: 5px;
        }
        .cuenta-info .codigo {
            font-size: 12px;
            color: #333;
        }
        .cuenta-info .detalles {
            font-size: 9px;
            margin-top: 5px;
        }
        .meta-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10px;
            font-size: 9px;
        }
        .saldo-inicial {
            background-color: #e8f4e8;
            border: 1px solid #4a4;
            padding: 8px;
            margin-bottom: 10px;
        }
        .saldo-inicial strong {
            color: #060;
        }
        .mayor-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .mayor-table th,
        .mayor-table td {
            border: 1px solid #000;
            padding: 4px 6px;
            font-size: 9px;
        }
        .mayor-table th {
            background-color: #333;
            color: #fff;
            font-weight: bold;
            text-align: center;
        }
        .mayor-table .fecha {
            width: 10%;
        }
        .mayor-table .comprobante {
            width: 12%;
        }
        .mayor-table .documento {
            width: 12%;
        }
        .mayor-table .descripcion {
            width: 28%;
        }
        .mayor-table .monto {
            width: 12%;
            text-align: right;
        }
        .mayor-table .saldo {
            width: 14%;
            text-align: right;
            font-weight: bold;
        }
        .mayor-table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }
        .mayor-table .totales-row {
            background-color: #e0e0e0;
            font-weight: bold;
        }
        .saldo-deudor {
            color: #0066cc;
        }
        .saldo-acreedor {
            color: #cc0066;
        }
        .resumen-box {
            border: 2px solid #000;
            padding: 12px;
            margin-top: 15px;
        }
        .resumen-box h3 {
            font-size: 12px;
            margin-bottom: 8px;
            text-align: center;
            border-bottom: 1px solid #000;
            padding-bottom: 5px;
        }
        .resumen-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
            border-bottom: 1px dotted #ccc;
        }
        .resumen-row:last-child {
            border-bottom: none;
        }
        .resumen-total {
            font-weight: bold;
            font-size: 11px;
            background-color: #f0f0f0;
            padding: 8px;
            margin-top: 10px;
            text-align: center;
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
    </style>
</head>
<body>
    <div class="header">
        <h1>LIBRO MAYOR</h1>
        <h2>DigiPrenda</h2>
        <div class="periodo">
            Del {{ \Carbon\Carbon::parse($fechaInicio)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($fechaFin)->format('d/m/Y') }}
        </div>
    </div>

    <div class="cuenta-info">
        <h3>{{ $cuenta->nombre_cuenta }}</h3>
        <div class="codigo">Código: {{ $cuenta->codigo_cuenta }}</div>
        <div class="detalles">
            Tipo: {{ ucfirst($cuenta->tipo) }} |
            Naturaleza: {{ ucfirst($cuenta->naturaleza) }} |
            Nivel: {{ $cuenta->nivel }}
        </div>
    </div>

    <div class="meta-info">
        <span>Generado por: {{ $generado_por }}</span>
        <span>Fecha: {{ $fecha_generacion }}</span>
    </div>

    <div class="saldo-inicial">
        <strong>SALDO INICIAL (al {{ \Carbon\Carbon::parse($fechaInicio)->subDay()->format('d/m/Y') }}):</strong>
        Q {{ number_format(abs($saldoInicial), 2) }}
        <span class="{{ $saldoInicial >= 0 ? 'saldo-deudor' : 'saldo-acreedor' }}">
            ({{ $saldoInicial >= 0 ? 'Deudor' : 'Acreedor' }})
        </span>
    </div>

    <table class="mayor-table">
        <thead>
            <tr>
                <th class="fecha">FECHA</th>
                <th class="comprobante">COMPROBANTE</th>
                <th class="documento">DOCUMENTO</th>
                <th class="descripcion">DESCRIPCIÓN</th>
                <th class="monto">DEBE</th>
                <th class="monto">HABER</th>
                <th class="saldo">SALDO</th>
            </tr>
        </thead>
        <tbody>
            @forelse($movimientos as $mov)
            <tr>
                <td>{{ \Carbon\Carbon::parse($mov->fecha)->format('d/m/Y') }}</td>
                <td>{{ $mov->numero_comprobante }}</td>
                <td>{{ $mov->numero_documento ?? '-' }}</td>
                <td>{{ $mov->detalle ?? $mov->glosa ?? '-' }}</td>
                <td class="text-right">{{ $mov->debe > 0 ? number_format($mov->debe, 2) : '' }}</td>
                <td class="text-right">{{ $mov->haber > 0 ? number_format($mov->haber, 2) : '' }}</td>
                <td class="saldo {{ $mov->saldo >= 0 ? 'saldo-deudor' : 'saldo-acreedor' }}">
                    {{ number_format(abs($mov->saldo), 2) }} {{ $mov->saldo >= 0 ? 'D' : 'A' }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="7" class="text-center">No hay movimientos en el período seleccionado</td>
            </tr>
            @endforelse

            <tr class="totales-row">
                <td colspan="4" class="text-right">TOTALES DEL PERÍODO:</td>
                <td class="text-right">Q {{ number_format($totales['debe'], 2) }}</td>
                <td class="text-right">Q {{ number_format($totales['haber'], 2) }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <div class="resumen-box">
        <h3>RESUMEN DE LA CUENTA</h3>

        <div class="resumen-row">
            <span>Saldo inicial:</span>
            <strong>Q {{ number_format(abs($saldoInicial), 2) }} {{ $saldoInicial >= 0 ? '(D)' : '(A)' }}</strong>
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
            <span>Movimiento neto del período:</span>
            <strong>Q {{ number_format(abs($totales['debe'] - $totales['haber']), 2) }} {{ $totales['debe'] >= $totales['haber'] ? '(D)' : '(A)' }}</strong>
        </div>

        <div class="resumen-total">
            SALDO FINAL: Q {{ number_format(abs($saldoFinal), 2) }}
            <span class="{{ $saldoFinal >= 0 ? 'saldo-deudor' : 'saldo-acreedor' }}">
                ({{ $saldoFinal >= 0 ? 'DEUDOR' : 'ACREEDOR' }})
            </span>
        </div>
    </div>

    <div class="footer">
        <p>Documento generado automáticamente - DigiPrenda</p>
        <p>D = Saldo Deudor | A = Saldo Acreedor</p>
        <p>Este documento es válido sin firma ni sello</p>
    </div>
</body>
</html>
