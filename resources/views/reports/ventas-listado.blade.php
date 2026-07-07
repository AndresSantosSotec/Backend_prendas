<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Listado de Ventas</title>
    <style>
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 9px; color: #333; margin: 0; padding: 20px; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 2px solid #000; padding-bottom: 10px; }
        .header h1 { margin: 0; font-size: 18px; font-weight: bold; }
        .header p { margin: 5px 0; font-size: 10px; color: #666; }
        .filters { background: #f5f5f5; padding: 10px; margin-bottom: 15px; font-size: 9px; border: 1px solid #ddd; }
        .filters span { margin-right: 15px; }
        .table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        .table th { background: #333; color: white; padding: 6px 4px; text-align: left; font-size: 8px; font-weight: bold; }
        .table td { border-bottom: 1px solid #ddd; padding: 5px 4px; font-size: 8px; }
        .table tr:nth-child(even) { background: #f9f9f9; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .badge { padding: 2px 5px; font-size: 7px; border-radius: 3px; display: inline-block; font-weight: bold; }
        .badge-pagada { background: #d4edda; color: #155724; }
        .badge-pendiente { background: #fff3cd; color: #856404; }
        .badge-apartado { background: #cce5ff; color: #004085; }
        .badge-plan_pagos { background: #e2d4f0; color: #563d7c; }
        .badge-cancelada { background: #f8d7da; color: #721c24; }
        .badge-devuelta { background: #e2e3e5; color: #383d41; }
        .totals { margin-top: 20px; text-align: right; border-top: 2px solid #000; padding-top: 10px; }
        .totals table { display: inline-table; width: 250px; }
        .totals td { padding: 4px 8px; font-size: 10px; }
        .totals .total-row { font-weight: bold; font-size: 12px; background: #f5f5f5; }
        .footer { position: fixed; bottom: 0; width: 100%; text-align: center; font-size: 8px; color: #666; border-top: 1px solid #ddd; padding-top: 5px; }
        .page-break { page-break-after: always; }
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
                    <h1>LISTADO DE VENTAS</h1>
        <p>Avanza - Reporte generado el {{ now()->format('d/m/Y H:i:s') }}</p>
                </td>
                <td width="25%"></td>
            </tr>
        </table>
    </div>

    <div class="filters">
        <strong>Filtros aplicados:</strong>
        @if(!empty($filtros['estado']) && $filtros['estado'] !== 'todas')
            <span>Estado: <strong>{{ ucfirst($filtros['estado']) }}</strong></span>
        @else
            <span>Estado: <strong>Todos</strong></span>
        @endif
        @if(!empty($filtros['busqueda']))
            <span>Búsqueda: <strong>{{ $filtros['busqueda'] }}</strong></span>
        @endif
        <span>Total registros: <strong>{{ count($items) }}</strong></span>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>CÓDIGO VENTA</th>
                <th>FECHA</th>
                <th>CLIENTE</th>
                <th>ARTÍCULO (DESCRIPCIÓN)</th>
                <th class="text-right">P. COMPRA (COSTO)</th>
                <th class="text-right">P. VENTA</th>
                <th class="text-right">UTILIDAD</th>
                <th class="text-right">% MARGEN</th>
                <th class="text-center">ESTADO</th>
            </tr>
        </thead>
        <tbody>
            @forelse($items as $item)
                <tr>
                    <td>{{ $item->codigo_venta }}</td>
                    <td>{{ $item->fecha_venta ? \Carbon\Carbon::parse($item->fecha_venta)->format('d/m/Y H:i') : '' }}</td>
                    <td>{{ Str::limit($item->cliente_nombre, 18) }}</td>
                    <td>{{ Str::limit($item->descripcion, 32) }}</td>
                    <td class="text-right">Q{{ number_format($item->precio_compra, 2) }}</td>
                    <td class="text-right">Q{{ number_format($item->precio_venta, 2) }}</td>
                    <td class="text-right" style="color: {{ $item->utilidad >= 0 ? '#155724' : '#721c24' }}">
                        <strong>Q{{ number_format($item->utilidad, 2) }}</strong>
                    </td>
                    <td class="text-right" style="color: {{ $item->margen >= 0 ? '#155724' : '#721c24' }}">
                        <strong>{{ number_format($item->margen, 2) }}%</strong>
                    </td>
                    <td class="text-center">
                        <span class="badge badge-{{ $item->estado }}">{{ strtoupper($item->estado) }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" class="text-center">No hay artículos vendidos para mostrar</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr>
                <td>Total Costo (Compra):</td>
                <td class="text-right">Q{{ number_format($totales['costo'], 2) }}</td>
            </tr>
            <tr>
                <td>Total Venta:</td>
                <td class="text-right">Q{{ number_format($totales['venta'], 2) }}</td>
            </tr>
            <tr class="total-row">
                <td>UTILIDAD TOTAL:</td>
                <td class="text-right" style="color: {{ $totales['utilidad'] >= 0 ? '#155724' : '#721c24' }}">Q{{ number_format($totales['utilidad'], 2) }}</td>
            </tr>
            <tr class="total-row">
                <td>MARGEN GENERAL:</td>
                <td class="text-right" style="color: {{ $totales['margen'] >= 0 ? '#155724' : '#721c24' }}">{{ number_format($totales['margen'], 2) }}%</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Avanza - {{ now()->format('Y') }}
    </div>
</body>
</html>
