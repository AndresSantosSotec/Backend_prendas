<?php

namespace App\Exports;

use App\Models\Compra;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use Illuminate\Support\Facades\DB;

class ComprasExport implements FromQuery, WithHeadings, WithMapping, WithStyles, ShouldAutoSize, WithTitle
{
    protected $filters;
    protected $rowNumber = 0;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    /**
     * Query con filtros aplicados
     */
    public function query()
    {
        $query = Compra::query()
            ->with(['cliente', 'categoriaProducto', 'sucursal', 'usuario'])
            ->select('compras.*');

        // Filtro por sucursal
        if (!empty($this->filters['sucursal_id'])) {
            $query->where('compras.sucursal_id', $this->filters['sucursal_id']);
        }

        // Filtro por estado
        if (!empty($this->filters['estado']) && $this->filters['estado'] !== 'all') {
            $query->where('compras.estado', $this->filters['estado']);
        }

        // Filtro por categoría (puede ser array para múltiple selección)
        if (!empty($this->filters['categoria_id'])) {
            $categorias = is_array($this->filters['categoria_id'])
                ? $this->filters['categoria_id']
                : [$this->filters['categoria_id']];
            $query->whereIn('compras.categoria_producto_id', $categorias);
        }

        // Filtro por rango de fechas
        if (!empty($this->filters['fecha_desde'])) {
            $query->whereDate('compras.fecha_compra', '>=', $this->filters['fecha_desde']);
        }

        if (!empty($this->filters['fecha_hasta'])) {
            $query->whereDate('compras.fecha_compra', '<=', $this->filters['fecha_hasta']);
        }

        // Filtro por búsqueda general
        if (!empty($this->filters['search'])) {
            $search = $this->filters['search'];
            $query->where(function ($q) use ($search) {
                $q->where('compras.codigo_compra', 'like', "%{$search}%")
                    ->orWhere('compras.descripcion', 'like', "%{$search}%")
                    ->orWhereHas('cliente', function ($q) use ($search) {
                        $q->where(DB::raw("CONCAT(nombres, ' ', apellidos)"), 'like', "%{$search}%")
                            ->orWhere('codigo_cliente', 'like', "%{$search}%");
                    });
            });
        }

        return $query->orderBy('compras.fecha_compra', 'desc');
    }

    /**
     * Encabezados de la tabla
     */
    public function headings(): array
    {
        return [
            'Código Compra',
            'Código Prenda',
            'Fecha Compra',
            'Cliente',
            'Documento Cliente',
            'Categoría',
            'Descripción',
            'Marca',
            'Modelo',
            'Condición',
            'Valor Tasación',
            'Monto Pagado',
            'Precio Venta Sugerido',
            'Margen %',
            'Método Pago',
            'Estado',
            'Sucursal',
            'Usuario Registro',
            'Observaciones',
        ];
    }

    /**
     * Mapeo de datos para cada fila
     */
    public function map($compra): array
    {
        $this->rowNumber++;

        return [
            $compra->codigo_compra,
            $compra->codigo_prenda_generado,
            $compra->fecha_compra->format('d/m/Y H:i'),
            $compra->cliente
                ? trim($compra->cliente->nombres . ' ' . $compra->cliente->apellidos)
                : 'N/A',
            $compra->cliente?->numero_documento ?? 'N/A',
            $compra->categoriaProducto?->nombre ?? 'N/A',
            $compra->descripcion,
            $compra->marca ?? 'N/A',
            $compra->modelo ?? 'N/A',
            $this->getCondicionTexto($compra->condicion),
            number_format($compra->valor_tasacion, 2),
            number_format($compra->monto_pagado, 2),
            number_format($compra->precio_venta_sugerido, 2),
            number_format($compra->margen_esperado, 2),
            $this->getMetodoPagoTexto($compra->metodo_pago),
            $this->getEstadoTexto($compra->estado),
            $compra->sucursal?->nombre ?? 'N/A',
            $compra->usuario?->nombre ?? 'N/A',
            $compra->observaciones ?? '',
        ];
    }

    /**
     * Estilos para el Excel
     */
    public function styles(Worksheet $sheet)
    {
        // Estilo para encabezados
        $sheet->getStyle('A1:S1')->applyFromArray([
            'font' => [
                'bold' => true,
                'size' => 12,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Bordes para todas las celdas con datos
        if ($this->rowNumber > 0) {
            $sheet->getStyle('A1:S' . ($this->rowNumber + 1))->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
            ]);
        }

        // Alinear números a la derecha
        $sheet->getStyle('K2:N' . ($this->rowNumber + 1))->getAlignment()->setHorizontal('right');

        return [];
    }

    /**
     * Título de la hoja
     */
    public function title(): string
    {
        return 'Reporte de Compras';
    }

    /**
     * Helpers para formatear datos
     */
    private function getCondicionTexto($condicion): string
    {
        $condiciones = [
            'excelente' => 'Excelente',
            'muy_buena' => 'Muy Buena',
            'buena' => 'Buena',
            'regular' => 'Regular',
            'mala' => 'Mala',
        ];

        return $condiciones[$condicion] ?? $condicion;
    }

    private function getMetodoPagoTexto($metodo): string
    {
        $metodos = [
            'efectivo' => 'Efectivo',
            'transferencia' => 'Transferencia',
            'cheque' => 'Cheque',
            'mixto' => 'Mixto',
        ];

        return $metodos[$metodo] ?? $metodo;
    }

    private function getEstadoTexto($estado): string
    {
        $estados = [
            'activa' => 'Activa',
            'vendida' => 'Vendida',
            'cancelada' => 'Cancelada',
        ];

        return $estados[$estado] ?? $estado;
    }
}
