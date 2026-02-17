<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Compras</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            color: #000;
            line-height: 1.3;
        }

        .header {
            text-align: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border: 2px solid #000;
            padding: 10px;
        }

        .header h1 {
            color: #000;
            font-size: 18px;
            margin-bottom: 5px;
            font-weight: bold;
            letter-spacing: 1px;
        }

        .header .subtitle {
            color: #000;
            font-size: 10px;
        }

        .filters-section {
            background-color: #f5f5f5;
            padding: 10px;
            margin-bottom: 12px;
            border: 1px solid #000;
        }

        .filters-section h3 {
            font-size: 11px;
            color: #000;
            margin-bottom: 8px;
            font-weight: bold;
            text-transform: uppercase;
            border-bottom: 1px solid #000;
            padding-bottom: 3px;
        }

        .filter-item {
            display: inline-block;
            margin-right: 15px;
            margin-bottom: 5px;
        }

        .filter-label {
            font-weight: bold;
            color: #000;
        }

        .stat-box {
            width: 24%;
            text-align: center;
            padding: 12px;
            background-color: #f9f9f9;
            color: #000;
            border: 2px solid #000;
            display: inline-block;
            vertical-align: top;
        }

        .stat-box.green,
        .stat-box.blue,
        .stat-box.orange {
            background-color: #f9f9f9;
        }

        .stat-label {
            font-size: 9px;
            margin-bottom: 5px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .stat-value {
            font-size: 12px;
            font-weight: bold;
        }
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            font-size: 8px;
        }

        table thead {
            background-color: #4472C4;
            color: white;
        }

        table thead th {
            padding: 8px 4px;
            text-align: left;
            font-weight: bold;
            border: 1px solid #2557a7;
        }

        table tbody td {
            padding: 6px 4px;
            border: 1px solid #ddd;
        }

        table tbody tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        table tbody tr:hover {
            background-color: #e8f0fe;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 8px;
            font-weight: bold;
        }

        .badge-activa {
            background-color: #d4edda;
            color: #155724;
        }

        .badge-vendida {
            background-color: #cce5ff;
            color: #004085;
        }

        .badge-cancelada {
            background-color: #f8d7da;
            color: #721c24;
        }

        .footer {
            margin-top: 30px;
            padding-top: 10px;
            border-top: 2px solid #ddd;
            text-align: center;
            font-size: 9px;
            color: #666;
        }

        .page-break {
            page-break-after: always;
        }

        @media print {
            .page-break {
                page-break-after: always;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="header">
        <h1>REPORTE DE COMPRAS DIRECTAS</h1>
        <div class="subtitle">
            Generado el {{ $fechaGeneracion }}
            @if($sucursal)
                | Sucursal: {{ $sucursal->nombre }}
            @endif
        </div>
    </div>

    <!-- Filtros Aplicados -->
    @if($filtrosAplicados)
    <div class="filters-section">
        <h3>Filtros Aplicados:</h3>
        @if(isset($filtrosAplicados['fecha_desde']) || isset($filtrosAplicados['fecha_hasta']))
            <div class="filter-item">
                <span class="filter-label">Período:</span>
                {{ $filtrosAplicados['fecha_desde'] ?? 'Inicio' }} - {{ $filtrosAplicados['fecha_hasta'] ?? 'Fin' }}
            </div>
        @endif
        @if(isset($filtrosAplicados['estado']) && $filtrosAplicados['estado'] !== 'all')
            <div class="filter-item">
                <span class="filter-label">Estado:</span>
                {{ ucfirst($filtrosAplicados['estado']) }}
            </div>
        @endif
        @if(isset($filtrosAplicados['categorias']) && count($filtrosAplicados['categorias']) > 0)
            <div class="filter-item">
                <span class="filter-label">Categorías:</span>
                {{ implode(', ', $filtrosAplicados['categorias']) }}
            </div>
        @endif
    </div>
    @endif

    <!-- Estadísticas -->
    <table style="width:100%; border-collapse: collapse; margin-bottom:15px;">
        <tr>
            <td style="width:25%; padding:5px;">
                <div class="stat-box">
                    <div class="stat-label">Total Compras</div>
                    <div class="stat-value">{{ $estadisticas['total_compras'] }}</div>
                </div>
            </td>
            <td style="width:25%; padding:5px;">
                <div class="stat-box green">
                    <div class="stat-label">Total Invertido</div>
                    <div class="stat-value">Q{{ number_format($estadisticas['total_invertido'], 2) }}</div>
                </div>
            </td>
            <td style="width:25%; padding:5px;">
                <div class="stat-box blue">
                    <div class="stat-label">Valor Inventario</div>
                    <div class="stat-value">Q{{ number_format($estadisticas['valor_inventario'], 2) }}</div>
                </div>
            </td>
            <td style="width:25%; padding:5px;">
                <div class="stat-box orange">
                    <div class="stat-label">Margen Promedio</div>
                    <div class="stat-value">{{ number_format($estadisticas['margen_promedio'], 2) }}%</div>
                </div>
            </td>
        </tr>
    </table>

    <!-- Tabla de Compras -->
    <table>
        <thead>
            <tr>
                <th style="width: 8%;">Código</th>
                <th style="width: 8%;">Fecha</th>
                <th style="width: 13%;">Cliente</th>
                <th style="width: 12%;">Categoría</th>
                <th style="width: 18%;">Descripción</th>
                <th style="width: 8%;" class="text-right">Pagado</th>
                <th style="width: 8%;" class="text-right">P. Venta</th>
                <th style="width: 6%;" class="text-center">Margen</th>
                <th style="width: 8%;" class="text-center">Método</th>
                <th style="width: 8%;" class="text-center">Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($compras as $compra)
            <tr>
                <td style="font-size: 7px; font-family: monospace;">{{ $compra->codigo_compra }}</td>
                <td>{{ $compra->fecha_compra->format('d/m/Y') }}</td>
                <td>
                    <strong>{{ $compra->cliente ? trim($compra->cliente->nombres . ' ' . $compra->cliente->apellidos) : 'N/A' }}</strong>
                    @if($compra->cliente && $compra->cliente->codigo_cliente)
                        <br><span style="color: #666; font-size: 7px;">{{ $compra->cliente->codigo_cliente }}</span>
                    @endif
                </td>
                <td>{{ $compra->categoriaProducto?->nombre ?? 'N/A' }}</td>
                <td>
                    <strong>{{ $compra->descripcion }}</strong>
                    @if($compra->marca)
                        <br><span style="color: #666; font-size: 7px;">{{ $compra->marca }}{{ $compra->modelo ? ' - ' . $compra->modelo : '' }}</span>
                    @endif
                </td>
                <td class="text-right">Q{{ number_format($compra->monto_pagado, 2) }}</td>
                <td class="text-right">Q{{ number_format($compra->precio_venta_sugerido, 2) }}</td>
                <td class="text-center" style="color: {{ $compra->margen_esperado >= 0 ? '#28a745' : '#dc3545' }}; font-weight: bold;">
                    {{ number_format($compra->margen_esperado, 1) }}%
                </td>
                <td class="text-center" style="font-size: 7px;">{{ ucfirst($compra->metodo_pago) }}</td>
                <td class="text-center">
                    @if($compra->estado === 'activa')
                        <span class="badge badge-activa">ACTIVA</span>
                    @elseif($compra->estado === 'vendida')
                        <span class="badge badge-vendida">VENDIDA</span>
                    @else
                        <span class="badge badge-cancelada">CANCELADA</span>
                    @endif
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="10" class="text-center" style="padding: 20px; color: #999;">
                    No se encontraron compras con los filtros aplicados
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Footer -->
    <div class="footer">
        <p>
            DigiPrenda - Reporte generado por: {{ $usuario->nombre ?? 'Sistema' }}
        </p>
        <p>Página {PAGE_NUM} de {PAGE_COUNT}</p>
    </div>
</body>
</html>
