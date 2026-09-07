<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Estado de Resultados</title>
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
        .section-title {
            background-color: #333;
            color: #fff;
            font-weight: bold;
            padding: 5px 8px;
            font-size: 11px;
            margin-top: 14px;
            margin-bottom: 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        table th, table td {
            border: 1px solid #ccc;
            padding: 4px 6px;
            font-size: 9px;
        }
        table th {
            background-color: #555;
            color: #fff;
            font-weight: bold;
        }
        table tr:nth-child(even) { background-color: #f5f5f5; }
        .codigo { width: 12%; font-family: monospace; }
        .nombre { width: 52%; }
        .monto  { width: 18%; text-align: right; }
        .subtotal-row {
            background-color: #ddd !important;
            font-weight: bold;
        }
        .subtotal-row td { border-top: 2px solid #999; }
        .total-row {
            background-color: #bbb !important;
            font-weight: bold;
            font-size: 10px;
        }
        .total-row td { border-top: 2px solid #555; }
        .resultados-box {
            margin-top: 20px;
            border: 2px solid #333;
            padding: 10px 14px;
        }
        .resultados-box table { width: 100%; }
        .resultados-box td { padding: 5px 6px; font-size: 10px; border: none; }
        .resultados-box .label { font-weight: bold; width: 60%; }
        .resultados-box .valor { text-align: right; width: 40%; font-size: 11px; }
        .utilidad-positiva { color: #1a6b1a; }
        .utilidad-negativa { color: #aa1a1a; }
        .footer {
            margin-top: 30px;
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
                    @if(!empty($logoBase64))
                    <img src="{{ $logoBase64 }}" alt="Logo" style="max-height: 65px; max-width: 160px; object-fit: contain;">
                    @endif
                </td>
                <td width="50%" style="text-align: center; vertical-align: middle;">
                    <h1>{{ $empresa['nombre'] ?? 'Microsystem Plus' }}</h1>
                    <h2>{{ $tituloDocumento ?? 'ESTADO DE RESULTADOS' }}</h2>
                    <div class="periodo">Del {{ \Carbon\Carbon::parse($fechaInicio)->format('d/m/Y') }} al {{ \Carbon\Carbon::parse($fechaFin)->format('d/m/Y') }}</div>
                </td>
                <td width="25%"></td>
            </tr>
        </table>
    </div>

<div class="meta-info">
    <span>Generado por: <strong>{{ $generado_por }}</strong></span>
    <span>Fecha de generación: <strong>{{ $fecha_generacion }}</strong></span>
</div>

{{-- INGRESOS --}}
<div class="section-title">INGRESOS</div>
<table>
    <thead>
        <tr>
            <th class="codigo">Código</th>
            <th class="nombre">Nombre de Cuenta</th>
            <th class="monto">Saldo</th>
        </tr>
    </thead>
    <tbody>
        @forelse($ingresos as $cuenta)
        <tr>
            <td class="codigo">{{ $cuenta['codigo_cuenta'] }}</td>
            <td class="nombre">{{ $cuenta['nombre_cuenta'] }}</td>
            <td class="monto">{{ number_format($cuenta['saldo'], 2) }}</td>
        </tr>
        @empty
        <tr><td colspan="3" style="text-align:center;color:#777">Sin cuentas de ingresos en el período</td></tr>
        @endforelse
        <tr class="subtotal-row">
            <td colspan="2">TOTAL INGRESOS</td>
            <td class="monto">{{ number_format($totales['total_ingresos'], 2) }}</td>
        </tr>
    </tbody>
</table>

{{-- COSTOS --}}
@if(count($costos) > 0)
<div class="section-title">COSTOS DE VENTAS / SERVICIOS</div>
<table>
    <thead>
        <tr>
            <th class="codigo">Código</th>
            <th class="nombre">Nombre de Cuenta</th>
            <th class="monto">Saldo</th>
        </tr>
    </thead>
    <tbody>
        @foreach($costos as $cuenta)
        <tr>
            <td class="codigo">{{ $cuenta['codigo_cuenta'] }}</td>
            <td class="nombre">{{ $cuenta['nombre_cuenta'] }}</td>
            <td class="monto">{{ number_format($cuenta['saldo'], 2) }}</td>
        </tr>
        @endforeach
        <tr class="subtotal-row">
            <td colspan="2">TOTAL COSTOS</td>
            <td class="monto">{{ number_format($totales['total_costos'], 2) }}</td>
        </tr>
    </tbody>
</table>
@endif

{{-- GASTOS --}}
@if(count($gastos) > 0)
<div class="section-title">GASTOS OPERATIVOS</div>
<table>
    <thead>
        <tr>
            <th class="codigo">Código</th>
            <th class="nombre">Nombre de Cuenta</th>
            <th class="monto">Saldo</th>
        </tr>
    </thead>
    <tbody>
        @foreach($gastos as $cuenta)
        <tr>
            <td class="codigo">{{ $cuenta['codigo_cuenta'] }}</td>
            <td class="nombre">{{ $cuenta['nombre_cuenta'] }}</td>
            <td class="monto">{{ number_format($cuenta['saldo'], 2) }}</td>
        </tr>
        @endforeach
        <tr class="subtotal-row">
            <td colspan="2">TOTAL GASTOS</td>
            <td class="monto">{{ number_format($totales['total_gastos'], 2) }}</td>
        </tr>
    </tbody>
</table>
@endif

{{-- RESUMEN FINAL --}}
<div class="resultados-box">
    <table>
        <tr>
            <td class="label">INGRESOS TOTALES</td>
            <td class="valor">{{ number_format($totales['total_ingresos'], 2) }}</td>
        </tr>
        @if($totales['total_costos'] > 0)
        <tr>
            <td class="label">(-) COSTOS TOTALES</td>
            <td class="valor">{{ number_format($totales['total_costos'], 2) }}</td>
        </tr>
        <tr>
            <td class="label" style="border-top:1px solid #999;padding-top:6px">UTILIDAD BRUTA</td>
            <td class="valor" style="border-top:1px solid #999;padding-top:6px">{{ number_format($totales['utilidad_bruta'], 2) }}</td>
        </tr>
        @endif
        @if($totales['total_gastos'] > 0)
        <tr>
            <td class="label">(-) GASTOS OPERATIVOS</td>
            <td class="valor">{{ number_format($totales['total_gastos'], 2) }}</td>
        </tr>
        @endif
        <tr>
            <td class="label" style="border-top:2px solid #333;padding-top:8px;font-size:12px">
                UTILIDAD NETA DEL PERÍODO
            </td>
            <td class="valor {{ $totales['utilidad_neta'] >= 0 ? 'utilidad-positiva' : 'utilidad-negativa' }}"
                style="border-top:2px solid #333;padding-top:8px;font-size:13px">
                {{ $totales['utilidad_neta'] >= 0 ? '' : '(' }}{{ number_format(abs($totales['utilidad_neta']), 2) }}{{ $totales['utilidad_neta'] >= 0 ? '' : ')' }}
            </td>
</div>

{{-- CERTIFICACIÓN Y FIRMAS --}}
@if(!empty($mostrarFirmas))
<div style="margin-top: 25px; font-size: 9px; text-align: justify; line-height: 1.5; color: #111;">
    EL INFRASCRITO PERITO CONTADOR {{ strtoupper($peritoContadorNombre ?? 'ROSAURA MARISOL MENDOZA YOJCOM') }} CON NÚMERO DE REGISTRO {{ $peritoContadorRegistro ?? '115855092' }} AUTORIZADO POR LA SUPERINTENDENCIA DE ADMINISTRACIÓN TRIBUTARIA (SAT), CERTIFICA: QUE EL RESULTADO DE LAS OPERACIONES DE LA ENTIDAD {{ strtoupper($empresa['nombre'] ?? 'MICROSYSTEM PLUS') }} CORRESPONDIENTE AL PERÍODO DEL {{ \Carbon\Carbon::parse($fechaInicio)->format('d/m/Y') }} AL {{ \Carbon\Carbon::parse($fechaFin)->format('d/m/Y') }}, ASCIENDE A: Q. {{ number_format(abs($totales['utilidad_neta']), 2) }} {{ $totales['utilidad_neta'] >= 0 ? '(UTILIDAD NETA)' : '(DÉFICIT / PÉRDIDA)' }}.
</div>

<table width="100%" style="margin-top: 45px; font-size: 10px; text-align: center;">
    <tr>
        <td width="45%" style="border-top: 1px solid #000; padding-top: 5px;">
            <strong>{{ $firmante1Nombre ?? 'ROSAURA MARISOL MENDOZA YOJCOM' }}</strong><br>
            <span>{{ $firmante1Titulo ?? 'PERITO CONTADOR' }}</span>
        </td>
        <td width="10%"></td>
        <td width="45%" style="border-top: 1px solid #000; padding-top: 5px;">
            <strong>{{ $firmante2Nombre ?? 'REPRESENTANTE LEGAL' }}</strong><br>
            <span>{{ $firmante2Titulo ?? 'REPRESENTANTE LEGAL' }}</span>
        </td>
    </tr>
</table>
@endif

<div class="footer">
    Documento generado automáticamente — {{ $fecha_generacion }}
</div>

</body>
</html>
