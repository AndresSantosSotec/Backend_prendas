<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte de Ventas</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Courier New', monospace; font-size: 10px; color: #000; line-height: 1.3; }

        .header { text-align: center; margin-bottom: 14px; border: 2px solid #000; padding: 10px; }
        .header h1 { font-size: 17px; font-weight: bold; letter-spacing: 1px; margin-bottom: 4px; }
        .header .subtitle { font-size: 9px; color: #333; }

        .stats-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .stat-box { text-align: center; padding: 10px 6px; background: #f5f5f5; border: 1px solid #ccc; }
        .stat-label { font-size: 8px; font-weight: bold; text-transform: uppercase; margin-bottom: 4px; }
        .stat-value { font-size: 12px; font-weight: bold; }

        .sub-stats { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .sub-box { text-align: center; padding: 7px 4px; border: 1px solid #ccc; background: #fafafa; }
        .sub-label { font-size: 8px; text-transform: uppercase; margin-bottom: 2px; }
        .sub-value { font-size: 11px; font-weight: bold; }

        table.data { width: 100%; border-collapse: collapse; font-size: 8px; }
        table.data thead { background-color: #1565c0; color: white; }
        table.data thead th { padding: 6px 4px; text-align: left; border: 1px solid #0d47a1; font-weight: bold; }
        table.data tbody td { padding: 5px 4px; border: 1px solid #ddd; }
        table.data tbody tr:nth-child(even) { background-color: #f5f5f5; }

        .badge { display: inline-block; padding: 2px 7px; border-radius: 10px; font-size: 8px; font-weight: bold; }
        .badge-pagada, .badge-completada { background: #d4edda; color: #155724; }
        .badge-pendiente { background: #fff3cd; color: #856404; }
        .badge-cancelada { background: #f8d7da; color: #721c24; }

        .tipo-contado { color: #155724; font-weight: bold; }
        .tipo-credito  { color: #004085; font-weight: bold; }
        .tipo-apartado { color: #856404; font-weight: bold; }

        .text-right { text-align: right; }
        .footer { margin-top: 20px; border-top: 1px solid #aaa; padding-top: 8px; text-align: center; font-size: 8px; color: #666; }
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
                    <h1>REPORTE DE VENTAS</h1>
        <div class="subtitle">
            Período: {{ $fecha_desde }} — {{ $fecha_hasta }}
            &nbsp;&nbsp;|&nbsp;&nbsp;
            Generado el {{ $generado_en }} por {{ $generado_por }}
                </td>
                <td width="25%"></td>
            </tr>
        </table>
    </div>
    </div>

    <!-- Estadísticas principales -->
    <table class="stats-table">
        <tr>
            <td style="width:25%; padding:4px;">
                <div class="stat-box">
                    <div class="stat-label">Total Ventas</div>
                    <div class="stat-value">{{ $estadisticas['total_ventas'] }}</div>
                </div>
            </td>
            <td style="width:25%; padding:4px;">
                <div class="stat-box">
                    <div class="stat-label">Total Ingresos</div>
                    <div class="stat-value">Q{{ number_format($estadisticas['total_ingresos'], 2) }}</div>
                </div>
            </td>
            <td style="width:25%; padding:4px;">
                <div class="stat-box">
                    <div class="stat-label">Total Descuentos</div>
                    <div class="stat-value">Q{{ number_format($estadisticas['total_descuentos'], 2) }}</div>
                </div>
            </td>
            <td style="width:25%; padding:4px;">
                <div class="stat-box">
                    <div class="stat-label">Ticket Promedio</div>
                    <div class="stat-value">Q{{ number_format($estadisticas['ticket_promedio'], 2) }}</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Sub-estadísticas por tipo -->
    <table class="sub-stats">
        <tr>
            <td style="width:33.3%; padding:4px;">
                <div class="sub-box">
                    <div class="sub-label">Contado</div>
                    <div class="sub-value">{{ $estadisticas['ventas_contado'] }}</div>
                </div>
            </td>
            <td style="width:33.3%; padding:4px;">
                <div class="sub-box">
                    <div class="sub-label">Crédito</div>
                    <div class="sub-value">{{ $estadisticas['ventas_credito'] }}</div>
                </div>
            </td>
            <td style="width:33.3%; padding:4px;">
                <div class="sub-box">
                    <div class="sub-label">Apartado</div>
                    <div class="sub-value">{{ $estadisticas['ventas_apartado'] }}</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Tabla de ventas -->
    <table class="data">
        <thead>
            <tr>
                <th>Código Venta</th>
                <th>Fecha</th>
                <th>Cliente</th>
                <th>Artículo (Descripción)</th>
                <th class="text-right">P. Compra (Costo)</th>
                <th class="text-right">P. Venta</th>
                <th class="text-right">Utilidad</th>
                <th class="text-right">% Margen</th>
                <th class="text-center">Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ventas as $venta)
            <tr>
                <td>{{ $venta['codigo_venta'] }}</td>
                <td>{{ $venta['fecha_venta'] }}</td>
                <td>{{ Str::limit($venta['cliente'], 18) }}</td>
                <td>{{ Str::limit($venta['descripcion'], 32) }}</td>
                <td class="text-right">Q{{ number_format($venta['precio_compra'], 2) }}</td>
                <td class="text-right"><strong>Q{{ number_format($venta['precio_venta'], 2) }}</strong></td>
                <td class="text-right" style="color: {{ $venta['utilidad'] >= 0 ? '#155724' : '#721c24' }}">Q{{ number_format($venta['utilidad'], 2) }}</td>
                <td class="text-right" style="color: {{ $venta['margen'] >= 0 ? '#155724' : '#721c24' }}">{{ number_format($venta['margen'], 2) }}%</td>
                <td class="text-center">
                    <span class="badge badge-{{ $venta['estado'] }}">{{ strtoupper($venta['estado']) }}</span>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="9" style="text-align:center; padding:20px; color:#666;">
                    No hay artículos vendidos para el período seleccionado
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    @if(isset($totales))
    <div style="margin-top: 15px; text-align: right;">
        <table style="display: inline-table; width: 250px; border-collapse: collapse; font-size: 9px; border: 1px solid #ccc;">
            <tr>
                <td style="padding: 4px; border: 1px solid #ccc; text-align: left;">Total Costo (Compra):</td>
                <td style="padding: 4px; border: 1px solid #ccc; text-align: right;">Q{{ number_format($totales['costo'], 2) }}</td>
            </tr>
            <tr>
                <td style="padding: 4px; border: 1px solid #ccc; text-align: left;">Total Venta:</td>
                <td style="padding: 4px; border: 1px solid #ccc; text-align: right;">Q{{ number_format($totales['venta'], 2) }}</td>
            </tr>
            <tr style="font-weight: bold; background: #f5f5f5;">
                <td style="padding: 4px; border: 1px solid #ccc; text-align: left;">UTILIDAD TOTAL:</td>
                <td style="padding: 4px; border: 1px solid #ccc; text-align: right; color: {{ $totales['utilidad'] >= 0 ? '#155724' : '#721c24' }}">Q{{ number_format($totales['utilidad'], 2) }}</td>
            </tr>
            <tr style="font-weight: bold; background: #f5f5f5;">
                <td style="padding: 4px; border: 1px solid #ccc; text-align: left;">MARGEN GENERAL:</td>
                <td style="padding: 4px; border: 1px solid #ccc; text-align: right; color: {{ $totales['margen'] >= 0 ? '#155724' : '#721c24' }}">{{ number_format($totales['margen'], 2) }}%</td>
            </tr>
        </table>
    </div>
    @endif

    <div class="footer">
        Avanza &mdash; Reporte generado automáticamente &mdash; {{ $generado_en }}
    </div>

</body>
</html>
