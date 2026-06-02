<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Balance General</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 10px;
            line-height: 1.4;
            color: #000;
            padding: 20px;
        }
        .header {
            text-align: center;
            margin-bottom: 18px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .header h1 { font-size: 16px; font-weight: bold; margin-bottom: 3px; }
        .header h2 { font-size: 12px; font-weight: normal; margin-bottom: 3px; }
        .header .periodo { font-size: 10px; }
        .meta-info {
            display: flex;
            justify-content: space-between;
            margin-bottom: 15px;
            font-size: 9px;
        }
        .two-col {
            display: table;
            width: 100%;
            table-layout: fixed;
        }
        .col-left, .col-right {
            display: table-cell;
            width: 49%;
            vertical-align: top;
        }
        .col-left { padding-right: 8px; }
        .col-right { padding-left: 8px; }
        .section-title {
            background-color: #333;
            color: #fff;
            font-weight: bold;
            padding: 5px 8px;
            font-size: 11px;
            margin-top: 10px;
            margin-bottom: 0;
        }
        .section-title-right {
            background-color: #555;
            color: #fff;
            font-weight: bold;
            padding: 5px 8px;
            font-size: 11px;
            margin-top: 10px;
            margin-bottom: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th, table td {
            border: 1px solid #ccc;
            padding: 4px 5px;
            font-size: 9px;
        }
        table th {
            background-color: #555;
            color: #fff;
            font-weight: bold;
        }
        table tr:nth-child(even) { background-color: #f5f5f5; }
        .codigo { width: 28%; font-family: monospace; }
        .nombre { width: 48%; }
        .monto  { width: 24%; text-align: right; }
        .subtotal-row {
            background-color: #ddd !important;
            font-weight: bold;
        }
        .subtotal-row td { border-top: 2px solid #999; }
        .grand-total-row {
            background-color: #333 !important;
            color: #fff;
            font-weight: bold;
            font-size: 10px;
        }
        .grand-total-row td { border-top: 2px solid #000; }
        .balance-check {
            margin-top: 20px;
            border: 2px solid #333;
            padding: 10px 14px;
            text-align: center;
        }
        .balance-check .label { font-size: 11px; font-weight: bold; margin-bottom: 4px; }
        .balance-check .ok { color: #1a6b1a; font-size: 13px; font-weight: bold; }
        .balance-check .error { color: #aa1a1a; font-size: 13px; font-weight: bold; }
        .balance-table {
            margin-top: 20px;
            width: 100%;
            border-collapse: collapse;
        }
        .balance-table td {
            padding: 6px 10px;
            font-size: 10px;
            border: 1px solid #ccc;
        }
        .balance-table .lbl { width: 60%; font-weight: bold; }
        .balance-table .val { width: 40%; text-align: right; }
        .footer {
            margin-top: 25px;
            font-size: 9px;
            text-align: right;
            color: #555;
            border-top: 1px solid #ccc;
            padding-top: 6px;
        }
    </style>
</head>
<body>

    <div class="header" style="border:none; padding-bottom: 10px; border-bottom: 1px solid #ccc; margin-bottom: 15px;">
        <table width="100%">
            <tr>
                <td width="25%" style="text-align: left; vertical-align: middle;">
                    <img src="data:image/png;base64,{{ base64_encode(file_get_contents(resource_path('logos/avanza_logo.png'))) }}" alt="Logo" style="height: 80px;">
                </td>
                <td width="50%" style="text-align: center; vertical-align: middle;">
                    <h1>BALANCE GENERAL</h1>
    <h2>Al {{ \Carbon\Carbon::parse($fechaCorte)->format('d/m/Y') }}</h2>
    <div class="periodo">Estado de Situación Financiera
                </td>
                <td width="25%"></td>
            </tr>
        </table>
    </div>
</div>

<div class="meta-info">
    <span>Generado por: <strong>{{ $generado_por }}</strong></span>
    <span>Fecha de generación: <strong>{{ $fecha_generacion }}</strong></span>
</div>

<div class="two-col">

    {{-- COLUMNA IZQUIERDA: ACTIVOS --}}
    <div class="col-left">
        <div class="section-title">ACTIVOS</div>
        <table>
            <thead>
                <tr>
                    <th class="codigo">Código</th>
                    <th class="nombre">Cuenta</th>
                    <th class="monto">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @forelse($activos as $cuenta)
                <tr>
                    <td class="codigo">{{ $cuenta['codigo_cuenta'] }}</td>
                    <td class="nombre">{{ $cuenta['nombre_cuenta'] }}</td>
                    <td class="monto">{{ number_format($cuenta['saldo'], 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" style="text-align:center;color:#777">Sin cuentas</td></tr>
                @endforelse
                <tr class="subtotal-row">
                    <td colspan="2">TOTAL ACTIVOS</td>
                    <td class="monto">{{ number_format($totales['total_activos'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

    {{-- COLUMNA DERECHA: PASIVOS + PATRIMONIO --}}
    <div class="col-right">
        <div class="section-title-right">PASIVOS</div>
        <table>
            <thead>
                <tr>
                    <th class="codigo">Código</th>
                    <th class="nombre">Cuenta</th>
                    <th class="monto">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @forelse($pasivos as $cuenta)
                <tr>
                    <td class="codigo">{{ $cuenta['codigo_cuenta'] }}</td>
                    <td class="nombre">{{ $cuenta['nombre_cuenta'] }}</td>
                    <td class="monto">{{ number_format($cuenta['saldo'], 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" style="text-align:center;color:#777">Sin cuentas</td></tr>
                @endforelse
                <tr class="subtotal-row">
                    <td colspan="2">TOTAL PASIVOS</td>
                    <td class="monto">{{ number_format($totales['total_pasivos'], 2) }}</td>
                </tr>
            </tbody>
        </table>

        <div class="section-title-right" style="margin-top:14px">PATRIMONIO</div>
        <table>
            <thead>
                <tr>
                    <th class="codigo">Código</th>
                    <th class="nombre">Cuenta</th>
                    <th class="monto">Saldo</th>
                </tr>
            </thead>
            <tbody>
                @forelse($patrimonio as $cuenta)
                <tr>
                    <td class="codigo">{{ $cuenta['codigo_cuenta'] }}</td>
                    <td class="nombre">{{ $cuenta['nombre_cuenta'] }}</td>
                    <td class="monto">{{ number_format($cuenta['saldo'], 2) }}</td>
                </tr>
                @empty
                <tr><td colspan="3" style="text-align:center;color:#777">Sin cuentas</td></tr>
                @endforelse
                <tr class="subtotal-row">
                    <td colspan="2">TOTAL PATRIMONIO</td>
                    <td class="monto">{{ number_format($totales['total_patrimonio'], 2) }}</td>
                </tr>
                <tr class="grand-total-row">
                    <td colspan="2">TOTAL PASIVO + PATRIMONIO</td>
                    <td class="monto">{{ number_format($totales['total_pasivo_y_patrimonio'], 2) }}</td>
                </tr>
            </tbody>
        </table>
    </div>

</div>

{{-- VERIFICACIÓN DE BALANCE --}}
<div class="balance-check">
    <div class="label">VERIFICACIÓN DE BALANCE</div>
    @if($totales['cuadrado'])
        <div class="ok">✓ BALANCE CUADRADO</div>
        <div style="font-size:9px;color:#555;margin-top:4px">
            Total Activos = Total Pasivo + Patrimonio = {{ number_format($totales['total_activos'], 2) }}
        </div>
    @else
        <div class="error">✗ BALANCE DESCUADRADO</div>
        <div style="font-size:9px;color:#aa1a1a;margin-top:4px">
            Diferencia: {{ number_format(abs($totales['diferencia']), 2) }}
        </div>
    @endif
</div>

<div class="footer">
    Documento generado automáticamente — {{ $fecha_generacion }}
</div>

</body>
</html>
