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
    <div class="header">
        <h1>LISTADO DE VENTAS</h1>
        <p>Sistema de Empeños - Reporte generado el {{ now()->format('d/m/Y H:i:s') }}</p>
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
        <span>Total registros: <strong>{{ $ventas->count() }}</strong></span>
    </div>

    <table class="table">
        <thead>
            <tr>
                <th>CÓDIGO</th>
                <th>FECHA</th>
                <th>CLIENTE</th>
                <th>NIT</th>
                <th>SUCURSAL</th>
                <th>VENDEDOR</th>
                <th class="text-right">SUBTOTAL</th>
                <th class="text-right">DESC.</th>
                <th class="text-right">TOTAL</th>
                <th class="text-center">ESTADO</th>
            </tr>
        </thead>
        <tbody>
            @forelse($ventas as $venta)
                <tr>
                    <td>{{ $venta->codigo_venta }}</td>
                    <td>{{ \Carbon\Carbon::parse($venta->fecha_venta)->format('d/m/Y') }}</td>
                    <td>{{ Str::limit($venta->cliente_nombre, 20) }}</td>
                    <td>{{ $venta->cliente_nit ?? 'C/F' }}</td>
                    <td>{{ Str::limit($venta->sucursal->nombre ?? '-', 12) }}</td>
                    <td>{{ Str::limit($venta->vendedor->name ?? '-', 12) }}</td>
                    <td class="text-right">Q{{ number_format($venta->subtotal, 2) }}</td>
                    <td class="text-right">Q{{ number_format($venta->total_descuentos, 2) }}</td>
                    <td class="text-right"><strong>Q{{ number_format($venta->total_final, 2) }}</strong></td>
                    <td class="text-center">
                        <span class="badge badge-{{ $venta->estado }}">{{ strtoupper($venta->estado) }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="10" class="text-center">No hay ventas para mostrar</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="totals">
        <table>
            <tr>
                <td>Total Subtotal:</td>
                <td class="text-right">Q{{ number_format($totales['subtotal'], 2) }}</td>
            </tr>
            <tr>
                <td>Total Descuentos:</td>
                <td class="text-right">-Q{{ number_format($totales['descuentos'], 2) }}</td>
            </tr>
            <tr class="total-row">
                <td>TOTAL GENERAL:</td>
                <td class="text-right">Q{{ number_format($totales['total'], 2) }}</td>
            </tr>
        </table>
    </div>

    <div class="footer">
        Sistema de Gestión de Empeños - {{ now()->format('Y') }}
    </div>
</body>
</html>
