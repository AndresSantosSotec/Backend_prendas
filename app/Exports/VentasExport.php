<?php

namespace App\Exports;

use App\Models\Venta;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VentasExport implements FromCollection, WithHeadings, WithMapping, ShouldAutoSize, WithStyles
{
    protected $filtros;

    public function __construct($filtros = [])
    {
        $this->filtros = $filtros;
    }

    public function collection()
    {
        $query = Venta::with(['cliente', 'vendedor', 'sucursal']);

        if (!empty($this->filtros['estado']) && $this->filtros['estado'] !== 'todas') {
            $query->where('estado', $this->filtros['estado']);
        }

        if (!empty($this->filtros['fecha_desde'])) {
            $query->whereDate('fecha_venta', '>=', $this->filtros['fecha_desde']);
        }

        if (!empty($this->filtros['fecha_hasta'])) {
            $query->whereDate('fecha_venta', '<=', $this->filtros['fecha_hasta']);
        }

        return $query->get();
    }

    public function headings(): array
    {
        return [
            'ID',
            'Código',
            'Doc',
            'Fecha',
            'Cliente',
            'NIT',
            'Sucursal',
            'Vendedor',
            'Subtotal',
            'Descuentos',
            'Total Final',
            'Estado',
            'Certificada'
        ];
    }

    public function map($venta): array
    {
        return [
            $venta->id,
            $venta->codigo_venta,
            $venta->tipo_documento,
            $venta->fecha_venta,
            $venta->cliente_nombre,
            $venta->cliente_nit,
            $venta->sucursal->nombre ?? '',
            $venta->vendedor->name ?? '',
            $venta->subtotal,
            $venta->total_descuentos,
            $venta->total_final,
            ucfirst($venta->estado),
            $venta->certificada ? 'Sí' : 'No'
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']], 'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '2563EB']]],
        ];
    }
}
