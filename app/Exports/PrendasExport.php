<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use Illuminate\Support\Collection;
use Carbon\Carbon;

class PrendasExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle, ShouldAutoSize
{
    protected $prendas;
    protected $filtros;

    public function __construct(Collection $prendas, array $filtros = [])
    {
        $this->prendas = $prendas;
        $this->filtros = $filtros;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        return $this->prendas;
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'ID',
            'Código Prenda',
            'Categoría',
            'Descripción',
            'Marca',
            'Modelo',
            'Estado',
            'Valor Tasación',
            'Valor Préstamo',
            'Valor Venta',
            'Ubicación Física',
            'Cliente',
            'Crédito',
            'Fecha Ingreso',
            'Fecha Vencimiento',
        ];
    }

    /**
     * @param mixed $prenda
     * @return array
     */
    public function map($prenda): array
    {
        $estadosFormateados = [
            'empenado' => 'Empeñado',
            'empeñado' => 'Empeñado',
            'vencido' => 'Vencido',
            'en_venta' => 'En Venta',
            'vendido' => 'Vendido',
            'recuperado' => 'Recuperado',
            'cancelado' => 'Cancelado',
        ];

        return [
            $prenda->id,
            $prenda->codigo_prenda ?? 'N/A',
            $prenda->categoriaProducto?->nombre ?? 'Sin categoría',
            $prenda->descripcion ?? '',
            $prenda->marca ?? '',
            $prenda->modelo ?? '',
            $estadosFormateados[$prenda->estado] ?? ucfirst($prenda->estado ?? ''),
            $this->formatMoney($prenda->valor_tasacion),
            $this->formatMoney($prenda->valor_prestamo),
            $this->formatMoney($prenda->valor_venta),
            $prenda->ubicacion_fisica ?? 'N/A',
            $this->getClienteNombre($prenda),
            $prenda->creditoPrendario?->codigo_credito ?? 'N/A',
            $this->formatDate($prenda->fecha_ingreso),
            $this->formatDate($prenda->creditoPrendario?->fecha_vencimiento),
        ];
    }

    /**
     * @param Worksheet $sheet
     * @return array
     */
    public function styles(Worksheet $sheet)
    {
        $lastRow = $this->prendas->count() + 1;
        $lastColumn = 'O';

        // Estilo para encabezados
        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB'], // Azul
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['rgb' => '000000'],
                ],
            ],
        ]);

        // Altura de fila de encabezado
        $sheet->getRowDimension(1)->setRowHeight(25);

        // Estilo para datos
        if ($lastRow > 1) {
            $sheet->getStyle('A2:' . $lastColumn . $lastRow)->applyFromArray([
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['rgb' => 'CCCCCC'],
                    ],
                ],
                'alignment' => [
                    'vertical' => Alignment::VERTICAL_CENTER,
                ],
            ]);

            // Alternar colores de fila (zebra striping)
            for ($row = 2; $row <= $lastRow; $row++) {
                if ($row % 2 == 0) {
                    $sheet->getStyle('A' . $row . ':' . $lastColumn . $row)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => 'F3F4F6'],
                        ],
                    ]);
                }
            }

            // Formato de moneda para columnas de valores
            $sheet->getStyle('H2:J' . $lastRow)->getNumberFormat()
                ->setFormatCode('Q #,##0.00');

            // Alineación derecha para valores monetarios
            $sheet->getStyle('H2:J' . $lastRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // Centrar columnas específicas
            $sheet->getStyle('A2:A' . $lastRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B2:B' . $lastRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('G2:G' . $lastRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('N2:O' . $lastRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Colorear estados
            for ($row = 2; $row <= $lastRow; $row++) {
                $estado = $sheet->getCell('G' . $row)->getValue();
                $color = $this->getEstadoColor($estado);
                if ($color) {
                    $sheet->getStyle('G' . $row)->applyFromArray([
                        'fill' => [
                            'fillType' => Fill::FILL_SOLID,
                            'startColor' => ['rgb' => $color['bg']],
                        ],
                        'font' => [
                            'color' => ['rgb' => $color['text']],
                            'bold' => true,
                        ],
                    ]);
                }
            }
        }

        // Freeze panes (congelar primera fila)
        $sheet->freezePane('A2');

        return [];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Reporte de Prendas';
    }

    /**
     * Formatear fecha
     */
    private function formatDate($date): string
    {
        if (!$date) return 'N/A';
        try {
            return Carbon::parse($date)->format('d/m/Y');
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    /**
     * Formatear dinero
     */
    private function formatMoney($amount): float
    {
        return (float) ($amount ?? 0);
    }

    /**
     * Obtener nombre del cliente
     */
    private function getClienteNombre($prenda): string
    {
        $cliente = $prenda->creditoPrendario?->cliente;
        if (!$cliente) return 'N/A';
        return trim(($cliente->nombres ?? '') . ' ' . ($cliente->apellidos ?? ''));
    }

    /**
     * Obtener color según estado
     */
    private function getEstadoColor(string $estado): ?array
    {
        $colores = [
            'Empeñado' => ['bg' => 'DBEAFE', 'text' => '1E40AF'],
            'Vencido' => ['bg' => 'FEE2E2', 'text' => '991B1B'],
            'En Venta' => ['bg' => 'FEF3C7', 'text' => '92400E'],
            'Vendido' => ['bg' => 'D1FAE5', 'text' => '065F46'],
            'Recuperado' => ['bg' => 'E0E7FF', 'text' => '3730A3'],
            'Cancelado' => ['bg' => 'F3F4F6', 'text' => '4B5563'],
        ];

        return $colores[$estado] ?? null;
    }
}
