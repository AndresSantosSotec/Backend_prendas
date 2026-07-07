<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Créditos Vigentes</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; font-size: 9px; color: #000; line-height: 1.3; }
        .header { text-align: center; margin-bottom: 14px; border-bottom: 1px solid #ccc; padding-bottom: 10px; }
        .header h1 { font-size: 16px; margin-bottom: 4px; }
        .subtitle { font-size: 9px; color: #333; }
        .stats-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .stat-box { text-align: center; padding: 8px 5px; background: #f5f5f5; border: 1px solid #ccc; }
        .stat-label { font-size: 8px; font-weight: bold; text-transform: uppercase; margin-bottom: 3px; }
        .stat-value { font-size: 11px; font-weight: bold; }
        table.data { width: 100%; border-collapse: collapse; font-size: 7.5px; }
        table.data thead { background-color: #1e40af; color: #fff; }
        table.data thead th { padding: 5px 4px; text-align: left; border: 1px solid #1d4ed8; }
        table.data tbody td { padding: 4px; border: 1px solid #ddd; }
        table.data tbody tr:nth-child(even) { background: #f9fafb; }
        .text-right { text-align: right; }
        .footer { margin-top: 16px; border-top: 1px solid #aaa; padding-top: 8px; text-align: center; font-size: 8px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <h1>REPORTE DE CRÉDITOS VIGENTES</h1>
        <div class="subtitle">
            Corte al {{ $fecha_corte }} | Generado el {{ $generado_en }} por {{ $generado_por }}
        </div>
    </div>

    <table class="stats-table">
        <tr>
            <td style="width:25%; padding:4px;"><div class="stat-box"><div class="stat-label">Créditos Vigentes</div><div class="stat-value">{{ $estadisticas['total_creditos'] }}</div></div></td>
            <td style="width:25%; padding:4px;"><div class="stat-box"><div class="stat-label">Total Otorgado</div><div class="stat-value">Q{{ number_format($estadisticas['total_otorgado'], 2) }}</div></div></td>
            <td style="width:25%; padding:4px;"><div class="stat-box"><div class="stat-label">Capital Cobrado</div><div class="stat-value">Q{{ number_format($estadisticas['capital_cobrado'], 2) }}</div></div></td>
            <td style="width:25%; padding:4px;"><div class="stat-box"><div class="stat-label">Capital Pendiente</div><div class="stat-value">Q{{ number_format($estadisticas['capital_pendiente'], 2) }}</div></div></td>
        </tr>
        <tr>
            <td style="width:25%; padding:4px;"><div class="stat-box"><div class="stat-label">Interés Generado</div><div class="stat-value">Q{{ number_format($estadisticas['interes_generado'], 2) }}</div></div></td>
            <td style="width:25%; padding:4px;"><div class="stat-box"><div class="stat-label">Interés Cobrado</div><div class="stat-value">Q{{ number_format($estadisticas['interes_cobrado'], 2) }}</div></div></td>
            <td style="width:25%; padding:4px;"><div class="stat-box"><div class="stat-label">Interés Pendiente</div><div class="stat-value">Q{{ number_format($estadisticas['interes_pendiente'], 2) }}</div></div></td>
            <td style="width:25%; padding:4px;"><div class="stat-box"><div class="stat-label">Total Cobrado</div><div class="stat-value">Q{{ number_format($estadisticas['total_cobrado'], 2) }}</div></div></td>
        </tr>
    </table>

    <table class="data">
        <thead>
            <tr>
                <th>No. Crédito</th>
                <th>Cliente</th>
                <th>Sucursal</th>
                <th>Estado Corte</th>
                <th>Desembolso</th>
                <th>Vencimiento</th>
                <th>Artículos</th>
                <th class="text-right">Otorgado</th>
                <th class="text-right">Cap. Cobrado</th>
                <th class="text-right">Cap. Pendiente</th>
                <th class="text-right">Int. Generado</th>
                <th class="text-right">Int. Cobrado</th>
                <th class="text-right">Int. Pendiente</th>
            </tr>
        </thead>
        <tbody>
            @forelse($creditos as $credito)
                <tr>
                    <td>{{ $credito['numero_credito'] }}</td>
                    <td>{{ Str::limit($credito['cliente'], 22) }}</td>
                    <td>{{ Str::limit($credito['sucursal'], 16) }}</td>
                    <td>{{ strtoupper(str_replace('_', ' ', $credito['estado_corte'])) }}</td>
                    <td>{{ $credito['fecha_desembolso'] }}</td>
                    <td>{{ $credito['fecha_vencimiento'] }}</td>
                    <td>{{ Str::limit($credito['articulos'], 26) }}</td>
                    <td class="text-right">Q{{ number_format($credito['monto_otorgado'], 2) }}</td>
                    <td class="text-right">Q{{ number_format($credito['capital_cobrado'], 2) }}</td>
                    <td class="text-right">Q{{ number_format($credito['capital_pendiente'], 2) }}</td>
                    <td class="text-right">Q{{ number_format($credito['interes_generado'], 2) }}</td>
                    <td class="text-right">Q{{ number_format($credito['interes_cobrado'], 2) }}</td>
                    <td class="text-right">Q{{ number_format($credito['interes_pendiente'], 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="13" style="text-align:center; padding:20px; color:#666;">No hay créditos vigentes para la fecha de corte seleccionada</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Digiprenda | Reporte generado automáticamente | {{ $generado_en }}
    </div>
</body>
</html>