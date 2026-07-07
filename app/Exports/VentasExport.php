<?php

namespace App\Exports;

use App\Models\Venta;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VentasExport implements FromCollection, WithHeadings, ShouldAutoSize, WithStyles
{
    protected $filtros;

    public function __construct($filtros = [])
    {
        $this->filtros = $filtros;
    }

    public function collection()
    {
        $query = Venta::with(['cliente', 'vendedor', 'sucursal', 'detalles.prenda', 'detalles.compra', 'prenda']);

        if (!empty($this->filtros['estado']) && $this->filtros['estado'] !== 'todas') {
            $query->where('estado', $this->filtros['estado']);
        }

        if (!empty($this->filtros['fecha_desde'])) {
            $query->whereDate('fecha_venta', '>=', $this->filtros['fecha_desde']);
        }

        if (!empty($this->filtros['fecha_hasta'])) {
            $query->whereDate('fecha_venta', '<=', $this->filtros['fecha_hasta']);
        }

        if (!empty($this->filtros['busqueda'])) {
            $busqueda = $this->filtros['busqueda'];
            $query->where(function($q) use ($busqueda) {
                $q->where('codigo_venta', 'like', "%{$busqueda}%")
                  ->orWhere('cliente_nombre', 'like', "%{$busqueda}%")
                  ->orWhere('cliente_nit', 'like', "%{$busqueda}%")
                  ->orWhereHas('detalles', function($d) use ($busqueda) {
                      $d->where('descripcion', 'like', "%{$busqueda}%")
                        ->orWhere('codigo', 'like', "%{$busqueda}%");
                  });
            });
        }

        $ventas = $query->orderBy('fecha_venta', 'desc')->get();

        $rows = [];
        foreach ($ventas as $venta) {
            $detalles = $venta->detalles;
            if ($detalles && $detalles->count() > 0) {
                foreach ($detalles as $detalle) {
                    $precioCompra = 0;
                    if ($detalle->prenda_id) {
                        $precioCompra = $detalle->prenda?->valor_prestamo ?? 0;
                    } elseif ($detalle->producto_id) {
                        $precioCompra = $detalle->compra?->monto_pagado ?? 0;
                    }

                    $precioVenta = $detalle->total;
                    $diferencia = $precioVenta - $precioCompra;
                    $porcentajeDiferencia = $precioCompra > 0 ? round(($diferencia / $precioCompra) * 100, 2) : 0;

                    $rows[] = [
                        'codigo_venta' => $venta->codigo_venta,
                        'fecha' => $venta->fecha_venta ? $venta->fecha_venta->format('d/m/Y H:i:s') : '',
                        'cliente' => $venta->cliente_nombre,
                        'nit' => $venta->cliente_nit ?? 'C/F',
                        'vendedor' => $venta->vendedor->name ?? '',
                        'sucursal' => $venta->sucursal->nombre ?? '',
                        'descripcion_articulo' => $detalle->descripcion,
                        'precio_compra' => (float)$precioCompra,
                        'precio_venta' => (float)$precioVenta,
                        'diferencia' => (float)$diferencia,
                        'porcentaje_diferencia' => $porcentajeDiferencia . '%',
                        'estado' => ucfirst($venta->estado)
                    ];
                }
            } else {
                $precioCompra = 0;
                $descripcion = 'Venta de Prenda';
                if ($venta->prenda_id) {
                    $precioCompra = $venta->prenda?->valor_prestamo ?? 0;
                    $descripcion = $venta->prenda?->descripcion ?? $descripcion;
                }

                $precioVenta = $venta->precio_final;
                $diferencia = $precioVenta - $precioCompra;
                $porcentajeDiferencia = $precioCompra > 0 ? round(($diferencia / $precioCompra) * 100, 2) : 0;

                $rows[] = [
                    'codigo_venta' => $venta->codigo_venta,
                    'fecha' => $venta->fecha_venta ? $venta->fecha_venta->format('d/m/Y H:i:s') : '',
                    'cliente' => $venta->cliente_nombre,
                    'nit' => $venta->cliente_nit ?? 'C/F',
                    'vendedor' => $venta->vendedor->name ?? '',
                    'sucursal' => $venta->sucursal->nombre ?? '',
                    'descripcion_articulo' => $descripcion,
                    'precio_compra' => (float)$precioCompra,
                    'precio_venta' => (float)$precioVenta,
                    'diferencia' => (float)$diferencia,
                    'porcentaje_diferencia' => $porcentajeDiferencia . '%',
                    'estado' => ucfirst($venta->estado)
                ];
            }
        }

        return collect($rows);
    }

    public function headings(): array
    {
        return [
            'Código Venta',
            'Fecha',
            'Cliente',
            'NIT',
            'Vendedor',
            'Sucursal',
            'Descripción Artículo',
            'Precio Compra (Costo)',
            'Precio Venta',
            'Utilidad',
            '% Margen',
            'Estado'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '2563EB']]],
        ];
    }
}
