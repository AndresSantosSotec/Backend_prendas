<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
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

    public function collection()
    {
        return $this->prendas;
    }

    public function headings(): array
    {
        return [
            'ID',
            'Código Prenda',
            'Categoría',
            'Descripción',
            'Marca',
            'Modelo',
            'Serie',
            'Color',
            'Condición',
            'Estado',
            'Valor Tasación',
            'Valor Préstamo',
            'Precio Venta',
            'Sucursal',
            'Cliente',
            'Crédito',
            'Fecha Ingreso',
            'Fecha Vencimiento',
        ];
    }

    public function map($prenda): array
    {
        return [
            $prenda->id,
            $prenda->codigo_prenda ?? 'N/A',
            $prenda->categoriaProducto?->nombre ?? 'Sin categoría',
            $prenda->descripcion ?? '',
            $prenda->marca ?? '',
            $prenda->modelo ?? '',
            $prenda->serie ?? '',
            $prenda->color ?? '',
            ucfirst(str_replace('_', ' ', $prenda->condicion ?? '')),
            $this->formatEstado($prenda->estado),
            $this->formatMoney($prenda->valor_tasacion),
            $this->formatMoney($prenda->valor_prestamo),
            $this->formatMoney($prenda->precio_venta),
            $this->getSucursalNombre($prenda),
            $this->getClienteNombre($prenda),
            $prenda->creditoPrendario?->numero_credito ?? 'N/A',
            $this->formatDate($prenda->fecha_ingreso),
            $this->formatDate($prenda->creditoPrendario?->fecha_vencimiento),
        ];
    }

    public function styles(Worksheet $sheet)
    {
        $lastRow = $this->prendas->count() + 1;
        $lastColumn = 'R';

        $sheet->getStyle('A1:' . $lastColumn . '1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
                'size' => 11,
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2563EB'],
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

        $sheet->getRowDimension(1)->setRowHeight(25);

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

            $sheet->getStyle('K2:M' . $lastRow)->getNumberFormat()
                ->setFormatCode('Q #,##0.00');

            $sheet->getStyle('K2:M' . $lastRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            $sheet->getStyle('A2:A' . $lastRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('B2:B' . $lastRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('J2:J' . $lastRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle('Q2:R' . $lastRow)->getAlignment()
                ->setHorizontal(Alignment::HORIZONTAL_CENTER);

            for ($row = 2; $row <= $lastRow; $row++) {
                $estado = $sheet->getCell('J' . $row)->getValue();
                $color = $this->getEstadoColor($estado);
                if ($color) {
                    $sheet->getStyle('J' . $row)->applyFromArray([
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

        $sheet->freezePane('A2');

        return [];
    }

    public function title(): string
    {
        return 'Reporte de Prendas';
    }

    private function formatDate($date): string
    {
        if (!$date) return 'N/A';
        try {
            return Carbon::parse($date)->format('d/m/Y');
        } catch (\Exception $e) {
            return 'N/A';
        }
    }

    private function formatMoney($amount): float
    {
        return (float) ($amount ?? 0);
    }

    private function formatEstado(?string $estado): string
    {
        $estadosFormateados = [
            'en_custodia' => 'En Custodia',
            'vencida' => 'Vencida',
            'en_evaluacion' => 'En Evaluación',
            'en_venta' => 'En Venta',
            'vendida' => 'Vendida',
            'recuperada' => 'Recuperada',
            'extraviada' => 'Extraviada',
            'danada' => 'Dañada',
            'perdida' => 'Perdida',
            'empenado' => 'Empeñado',
            'empeñado' => 'Empeñado',
            'vencido' => 'Vencido',
            'vendido' => 'Vendido',
            'recuperado' => 'Recuperado',
            'cancelado' => 'Cancelado',
        ];

        return $estadosFormateados[$estado] ?? ucfirst(str_replace('_', ' ', $estado ?? ''));
    }

    private function getClienteNombre($prenda): string
    {
        $cliente = $prenda->creditoPrendario?->cliente;
        if (!$cliente) return 'N/A';
        return trim(($cliente->nombres ?? '') . ' ' . ($cliente->apellidos ?? ''));
    }

    private function getSucursalNombre($prenda): string
    {
        if ($prenda->creditoPrendario?->sucursal) {
            return $prenda->creditoPrendario->sucursal->nombre;
        }

        if ($prenda->sucursal) {
            return $prenda->sucursal->nombre;
        }

        return 'N/A';
    }

    private function getEstadoColor(string $estado): ?array
    {
        $colores = [
            'En Custodia' => ['bg' => 'DBEAFE', 'text' => '1E40AF'],
            'Empeñado' => ['bg' => 'DBEAFE', 'text' => '1E40AF'],
            'Vencida' => ['bg' => 'FEE2E2', 'text' => '991B1B'],
            'Vencido' => ['bg' => 'FEE2E2', 'text' => '991B1B'],
            'En Venta' => ['bg' => 'FEF3C7', 'text' => '92400E'],
            'Vendida' => ['bg' => 'D1FAE5', 'text' => '065F46'],
            'Vendido' => ['bg' => 'D1FAE5', 'text' => '065F46'],
            'Recuperada' => ['bg' => 'E0E7FF', 'text' => '3730A3'],
            'Recuperado' => ['bg' => 'E0E7FF', 'text' => '3730A3'],
            'Cancelado' => ['bg' => 'F3F4F6', 'text' => '4B5563'],
            'En Evaluación' => ['bg' => 'FCE7F3', 'text' => '9D174D'],
        ];

        return $colores[$estado] ?? null;
    }
}
