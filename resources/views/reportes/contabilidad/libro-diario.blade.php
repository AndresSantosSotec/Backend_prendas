<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Libro Diario</title>
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
            margin-bottom: 10px;
            font-size: 9px;
        }
        .asiento {
            margin-bottom: 15px;
            page-break-inside: avoid;
        }
        .asiento-header {
            background-color: #f0f0f0;
            padding: 5px;
            border: 1px solid #000;
            border-bottom: none;
        }
        .asiento-header-row {
            display: flex;
            justify-content: space-between;
        }
        .asiento-header strong {
            font-size: 10px;
        }
        .movimientos-table {
            width: 100%;
            border-collapse: collapse;
            border: 1px solid #000;
        }
        .movimientos-table th,
        .movimientos-table td {
            border: 1px solid #000;
            padding: 3px 5px;
            text-align: left;
            font-size: 9px;
        }
        .movimientos-table th {
            background-color: #e0e0e0;
            font-weight: bold;
        }
        .movimientos-table .cuenta {
            width: 15%;
        }
        .movimientos-table .descripcion {
            width: 45%;
        }
        .movimientos-table .monto {
            width: 20%;
            text-align: right;
        }
        .asiento-totales {
            background-color: #f5f5f5;
            font-weight: bold;
        }
        .totales-generales {
            margin-top: 20px;
            border: 2px solid #000;
            padding: 10px;
        }
        .totales-generales h3 {
            font-size: 12px;
            margin-bottom: 8px;
        }
        .totales-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
        }
        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 1px solid #000;
            font-size: 8px;
            text-align: center;
        }
        .page-break {
            page-break-before: always;
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
        <h1>LIBRO DIARIO GENERAL</h1>
        <h2>DigiPrenda</h2>
        <div class="periodo">
            Del {{ \Carbon\Carbon::parse($fechaInicio)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($fechaFin)->format('d/m/Y') }}
        </div>
    </div>

    <div class="meta-info">
        <span>Generado por: {{ $generado_por }}</span>
        <span>Fecha: {{ $fecha_generacion }}</span>
    </div>

    @foreach($asientos as $asiento)
    <div class="asiento">
        <div class="asiento-header">
            <div class="asiento-header-row">
                <strong>Comprobante: {{ $asiento->numero_comprobante }}</strong>
                <strong>Tipo: {{ $asiento->tipoPoliza->nombre ?? 'N/A' }}</strong>
                <strong>Fecha: {{ \Carbon\Carbon::parse($asiento->fecha_contabilizacion)->format('d/m/Y') }}</strong>
            </div>
            <div style="margin-top: 3px; font-size: 9px;">
                <strong>Glosa:</strong> {{ $asiento->glosa ?? 'Sin descripción' }}
            </div>
        </div>

        <table class="movimientos-table">
            <thead>
                <tr>
                    <th class="cuenta">Cuenta</th>
                    <th class="descripcion">Descripción</th>
                    <th class="monto">Debe</th>
                    <th class="monto">Haber</th>
                </tr>
            </thead>
            <tbody>
                @foreach($asiento->movimientos as $mov)
                <tr>
                    <td>{{ $mov->cuentaContable->codigo_cuenta ?? 'N/A' }}</td>
                    <td>{{ $mov->cuentaContable->nombre_cuenta ?? 'Sin nombre' }}</td>
                    <td class="text-right">{{ $mov->debe > 0 ? number_format($mov->debe, 2) : '' }}</td>
                    <td class="text-right">{{ $mov->haber > 0 ? number_format($mov->haber, 2) : '' }}</td>
                </tr>
                @endforeach
                <tr class="asiento-totales">
                    <td colspan="2" class="text-right">TOTALES ASIENTO:</td>
                    <td class="text-right">{{ number_format($asiento->total_debe, 2) }}</td>
                    <td class="text-right">{{ number_format($asiento->total_haber, 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>
    @endforeach

    <div class="totales-generales">
        <h3>TOTALES GENERALES DEL PERÍODO</h3>
        <table style="width: 100%;">
            <tr>
                <td style="width: 70%;">Total de asientos registrados:</td>
                <td class="text-right"><strong>{{ count($asientos) }}</strong></td>
            </tr>
            <tr>
                <td>Total DEBE:</td>
                <td class="text-right"><strong>Q {{ number_format($totalGeneral['debe'], 2) }}</strong></td>
            </tr>
            <tr>
                <td>Total HABER:</td>
                <td class="text-right"><strong>Q {{ number_format($totalGeneral['haber'], 2) }}</strong></td>
            </tr>
            <tr>
                <td>Diferencia:</td>
                <td class="text-right">
                    <strong style="color: {{ abs($totalGeneral['debe'] - $totalGeneral['haber']) < 0.01 ? 'green' : 'red' }}">
                        Q {{ number_format(abs($totalGeneral['debe'] - $totalGeneral['haber']), 2) }}
                    </strong>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">
        <p>Documento generado automáticamente - DigiPrenda</p>
        <p>Este documento es válido sin firma ni sello</p>
    </div>
</body>
</html>
