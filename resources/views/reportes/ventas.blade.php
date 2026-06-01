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

    <div class="header">
        <h1>REPORTE DE VENTAS</h1>
        <div class="subtitle">
            Período: {{ $fecha_desde }} — {{ $fecha_hasta }}
            &nbsp;&nbsp;|&nbsp;&nbsp;
            Generado el {{ $generado_en }} por {{ $generado_por }}
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
                <th>Código</th>
                <th>Tipo</th>
                <th>Estado</th>
                <th>Cliente</th>
                <th>Vendedor</th>
                <th class="text-right">Total</th>
                <th class="text-right">Descuento</th>
                <th class="text-right">Pagado</th>
                <th class="text-right">Saldo</th>
                <th>Método Pago</th>
                <th>Fecha</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ventas as $venta)
            <tr>
                <td>{{ $venta['codigo_venta'] }}</td>
                <td class="tipo-{{ $venta['tipo_venta'] }}">{{ ucfirst($venta['tipo_venta']) }}</td>
                <td>
                    <span class="badge badge-{{ $venta['estado'] }}">{{ ucfirst($venta['estado']) }}</span>
                </td>
                <td>{{ $venta['cliente'] }}</td>
                <td>{{ $venta['vendedor'] }}</td>
                <td class="text-right">Q{{ number_format($venta['total_final'], 2) }}</td>
                <td class="text-right">Q{{ number_format($venta['total_descuentos'], 2) }}</td>
                <td class="text-right">Q{{ number_format($venta['total_pagado'] ?? 0, 2) }}</td>
                <td class="text-right">Q{{ number_format($venta['saldo_pendiente'] ?? 0, 2) }}</td>
                <td>{{ $venta['metodo_pago'] }}</td>
                <td>{{ $venta['fecha_venta'] }}</td>
            </tr>
            @empty
            <tr>
                <td colspan="11" style="text-align:center; padding:20px; color:#666;">
                    No hay ventas para el período seleccionado
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">
        Avanza &mdash; Reporte generado automáticamente &mdash; {{ $generado_en }}
    </div>

</body>
</html>
