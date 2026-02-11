<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reporte de Prendas</title>
    <style>
        @page {
            margin: 0cm 0cm;
        }
        body {
            font-family: 'Courier New', monospace;
            font-size: 11px;
            color: #000;
            line-height: 1.3;
            margin-top: 2cm;
            margin-bottom: 2cm;
            margin-left: 2cm;
            margin-right: 2cm;
        }
        header {
            position: fixed;
            top: 0cm;
            left: 0cm;
            right: 0cm;
            height: 2.5cm;
            background-color: #fff;
            color: #000;
            text-align: center;
            line-height: 30px;
            border-bottom: 2px solid #000;
        }
        footer {
            position: fixed;
            bottom: 0cm;
            left: 0cm;
            right: 0cm;
            height: 1.5cm;
            background-color: #fff;
            color: #000;
            text-align: center;
            line-height: 1.5cm;
            border-top: 1px solid #000;
            font-size: 9px;
        }
        .document-title {
            text-align: center;
            font-size: 18px;
            font-weight: bold;
            color: #000;
            margin-top: 10px;
            margin-bottom: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            background-color: #fff;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        th {
            background-color: #000;
            color: #fff;
            font-weight: bold;
            text-transform: uppercase;
            font-size: 10px;
            padding: 10px 8px;
            border: 1px solid #000;
            text-align: left;
        }
        td {
            padding: 8px;
            border: 1px solid #000;
            color: #000;
            vertical-align: middle;
            font-size: 10px;
        }
        .text-right { text-align: right; }
        .text-center { text-align: center; }

        tr:nth-child(even) { background-color: #f5f5f5; }

        .status-badge {
            padding: 2px 6px;
            border: 1px solid #000;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <header>
        <div style="font-size: 18px; font-weight: bold; padding-top: 15px;">Casa de Empeño</div>
        <div style="font-size: 10px; color: #666;">Reporte de Inventario de Prendas</div>
    </header>

    <footer>
        Generado el {{ date('d/m/Y H:i') }}
    </footer>

    <h2 class="document-title">Reporte de Prendas</h2>

    <table>
        <thead>
            <tr>
                <th width="12%">Código</th>
                <th width="15%">Categoría</th>
                <th width="25%">Descripción</th>
                <th width="10%">Estado</th>
                <th class="text-right" width="12%">Avalúo</th>
                <th class="text-right" width="12%">Préstamo</th>
                <th class="text-right" width="12%">Venta</th>
            </tr>
        </thead>
        <tbody>
            @foreach($prendas as $prenda)
                <tr>
                    <td>{{ $prenda->codigo_prenda }}</td>
                    <td>{{ $prenda->categoriaProducto?->nombre ?? 'N/A' }}</td>
                    <td>{{ Str::limit($prenda->descripcion, 50) }}</td>
                    <td><span class="status-badge">{{ str_replace('_', ' ', strtoupper($prenda->estado)) }}</span></td>
                    <td class="text-right">Q{{ number_format($prenda->valor_tasacion, 2) }}</td>
                    <td class="text-right">Q{{ number_format($prenda->valor_prestamo, 2) }}</td>
                    <td class="text-right">
                        @if($prenda->valor_venta)
                           Q{{ number_format($prenda->valor_venta, 2) }}
                        @else
                           -
                        @endif
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
