<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Prendas</title>
    <style>
        @page {
            size: letter landscape;
            margin: 0cm 0cm;
        }
        body {
            font-family: 'Courier New', monospace;
            font-size: 9px;
            color: #000;
            line-height: 1.2;
            margin-top: 2cm;
            margin-bottom: 1.5cm;
            margin-left: 1cm;
            margin-right: 1cm;
        }
        header {
            position: fixed;
            top: 0cm;
            left: 0cm;
            right: 0cm;
            height: 2.2cm;
            background-color: #fff;
            color: #000;
            text-align: center;
            border-bottom: 2px solid #000;
        }
        footer {
            position: fixed;
            bottom: 0cm;
            left: 0cm;
            right: 0cm;
            height: 1.2cm;
            background-color: #fff;
            color: #000;
            text-align: center;
            line-height: 1.2cm;
            border-top: 1px solid #000;
            font-size: 8px;
        }
        .document-title {
            text-align: center;
            font-size: 14px;
            font-weight: bold;
            color: #000;
            margin-top: 8px;
            margin-bottom: 10px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .summary {
            margin-bottom: 8px;
            font-size: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            background-color: #fff;
        }
        th {
            background-color: #000;
            color: #fff;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 7px;
            padding: 6px 4px;
            border: 1px solid #000;
            text-align: left;
        }
        td {
            padding: 5px 4px;
            border: 1px solid #000;
            color: #000;
            vertical-align: middle;
            font-size: 7px;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        tr:nth-child(even) { background-color: #f5f5f5; }
        .status-badge {
            padding: 1px 4px;
            border: 1px solid #000;
            font-size: 6px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .money {
            white-space: nowrap;
        }
    </style>
</head>
<body>
    <header style="padding-bottom: 5px;">
        <table width="100%" style="margin-top: 5px;">
            <tr>
                <td width="20%" style="text-align: left; vertical-align: middle; border: none;">
                    <img src="data:image/png;base64,{{ base64_encode(file_get_contents(resource_path('logos/avanza_logo.png'))) }}" alt="Logo" style="height: 50px; margin-left: 10px;">
                </td>
                <td width="60%" style="text-align: center; vertical-align: middle; border: none; line-height: 1.2;">
                    <div style="font-size: 16px; font-weight: bold;">Avanza</div>
                    <div style="font-size: 9px; color: #666;">Reporte de Inventario de Prendas</div>
                </td>
                <td width="20%" style="border: none; text-align: right; font-size: 8px; padding-right: 10px;">
                    Total: {{ $prendas->count() }} prendas
                </td>
            </tr>
        </table>
    </header>

    <footer>
        Generado el {{ date('d/m/Y H:i') }}
    </footer>

    <h2 class="document-title">Reporte de Prendas</h2>

    <table>
        <thead>
            <tr>
                <th width="8%">Código</th>
                <th width="8%">Categoría</th>
                <th width="14%">Descripción</th>
                <th width="6%">Marca</th>
                <th width="6%">Modelo</th>
                <th width="6%">Serie</th>
                <th width="5%">Color</th>
                <th width="6%">Estado</th>
                <th class="text-right" width="8%">Avalúo</th>
                <th class="text-right" width="8%">Préstamo</th>
                <th class="text-right" width="8%">Precio Venta</th>
                <th width="9%">Sucursal</th>
                <th width="10%">Cliente</th>
            </tr>
        </thead>
        <tbody>
            @foreach($prendas as $prenda)
                @php
                    $sucursalNombre = $prenda->creditoPrendario?->sucursal?->nombre
                        ?? $prenda->sucursal?->nombre
                        ?? 'N/A';
                    $clienteNombre = trim(
                        ($prenda->creditoPrendario?->cliente?->nombres ?? '') . ' ' .
                        ($prenda->creditoPrendario?->cliente?->apellidos ?? '')
                    ) ?: 'N/A';
                @endphp
                <tr>
                    <td>{{ $prenda->codigo_prenda }}</td>
                    <td>{{ $prenda->categoriaProducto?->nombre ?? 'N/A' }}</td>
                    <td>{{ Str::limit($prenda->descripcion, 45) }}</td>
                    <td>{{ $prenda->marca ?: '-' }}</td>
                    <td>{{ $prenda->modelo ?: '-' }}</td>
                    <td>{{ $prenda->serie ?: '-' }}</td>
                    <td>{{ $prenda->color ?: '-' }}</td>
                    <td><span class="status-badge">{{ str_replace('_', ' ', strtoupper($prenda->estado)) }}</span></td>
                    <td class="text-right money">Q{{ number_format($prenda->valor_tasacion ?? 0, 2) }}</td>
                    <td class="text-right money">Q{{ number_format($prenda->valor_prestamo ?? 0, 2) }}</td>
                    <td class="text-right money">
                        @if($prenda->precio_venta)
                            Q{{ number_format($prenda->precio_venta, 2) }}
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ Str::limit($sucursalNombre, 22) }}</td>
                    <td>{{ Str::limit($clienteNombre, 22) }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
