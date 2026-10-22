<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithCustomStartCell;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class CreditosVigentesExport implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithTitle, WithCustomStartCell
{
    public function __construct(
        protected array $items,
        protected array $estadisticas,
        protected string $fechaCorte,
        protected string $generadoPor
    ) {
    }

    public function title(): string
    {
        return 'Créditos Vigentes';
    }

    public function startCell(): string
    {
        return 'A6';
    }

    public function headings(): array
    {
        return [
            'No. Crédito',
            'Cliente',
            'Sucursal',
            'Estado al Corte',
            'Fec. Desembolso',
            'Fec. Vencimiento',
            'Artículos / Garantías',
            'Monto Otorgado',
            'Capital Cobrado',
            'Recup. por Ventas',
            'Capital Pendiente',
            'Interés Generado',
            'Interés Cobrado',
            'Interés Pendiente',
            'Mora Cobrada',
            'Total Cobrado / Recup.',
        ];
    }

    public function array(): array
    {
        $rows = [];

        foreach ($this->items as $item) {
            $rows[] = [
                $item['numero_credito'],
                $item['cliente'],
                $item['sucursal'],
                ucfirst(str_replace('_', ' ', $item['estado_corte'])),
                $item['fecha_desembolso'],
                $item['fecha_vencimiento'],
                $item['articulos'],
                (float) ($item['monto_otorgado'] ?? 0),
                (float) ($item['capital_cobrado'] ?? 0),
                (float) ($item['recuperado_ventas'] ?? 0),
                (float) ($item['capital_pendiente'] ?? 0),
                (float) ($item['interes_generado'] ?? 0),
                (float) ($item['interes_cobrado'] ?? 0),
                (float) ($item['interes_pendiente'] ?? 0),
                (float) ($item['mora_cobrada'] ?? 0),
                (float) ($item['total_cobrado'] ?? 0),
            ];
        }

        // Fila de totales
        if (!empty($rows)) {
            $rows[] = [
                'TOTALES',
                '',
                '',
                '',
                '',
                '',
                count($this->items) . ' créditos',
                (float) ($this->estadisticas['total_otorgado'] ?? 0),
                (float) ($this->estadisticas['capital_cobrado'] ?? 0),
                (float) ($this->estadisticas['recuperado_ventas'] ?? 0),
                (float) ($this->estadisticas['capital_pendiente'] ?? 0),
                (float) ($this->estadisticas['interes_generado'] ?? 0),
                (float) ($this->estadisticas['interes_cobrado'] ?? 0),
                (float) ($this->estadisticas['interes_pendiente'] ?? 0),
                0,
                (float) ($this->estadisticas['total_cobrado'] ?? 0),
            ];
        }

        return $rows;
    }

    public function styles(Worksheet $sheet)
    {
        // Título del reporte
        $sheet->mergeCells('A1:P1');
        $sheet->setCellValue('A1', 'DIGIPRENDA - REPORTE DE CRÉDITOS VIGENTES Y RECUPERACIONES');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF1E3A8A'));
        $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Subtítulo con fecha de corte
        $sheet->mergeCells('A2:P2');
        $sheet->setCellValue('A2', 'Corte al: ' . $this->fechaCorte . ' | Generado el: ' . now()->format('d/m/Y H:i') . ' por: ' . $this->generadoPor);
        $sheet->getStyle('A2')->getFont()->setSize(10)->setItalic(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FF4B5563'));
        $sheet->getStyle('A2')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Bloque de resumen estadístico (Fila 4)
        $sheet->setCellValue('A4', 'Total Otorgado:');
        $sheet->setCellValue('B4', (float) ($this->estadisticas['total_otorgado'] ?? 0));
        $sheet->setCellValue('D4', 'Recup. Cobros:');
        $sheet->setCellValue('E4', (float) ($this->estadisticas['capital_cobrado'] ?? 0));
        $sheet->setCellValue('G4', 'Recup. Ventas (Op):');
        $sheet->setCellValue('H4', (float) ($this->estadisticas['recuperado_ventas'] ?? 0));
        $sheet->setCellValue('J4', 'Capital Pendiente:');
        $sheet->setCellValue('K4', (float) ($this->estadisticas['capital_pendiente'] ?? 0));
        $sheet->setCellValue('M4', 'Total Recuperado:');
        $sheet->setCellValue('N4', (float) ($this->estadisticas['total_cobrado'] ?? 0));

        $sheet->getStyle('A4:N4')->getFont()->setBold(true)->setSize(9);
        $sheet->getStyle('B4')->getNumberFormat()->setFormatCode('Q #,##0.00');
        $sheet->getStyle('E4')->getNumberFormat()->setFormatCode('Q #,##0.00');
        $sheet->getStyle('H4')->getNumberFormat()->setFormatCode('Q #,##0.00');
        $sheet->getStyle('K4')->getNumberFormat()->setFormatCode('Q #,##0.00');
        $sheet->getStyle('N4')->getNumberFormat()->setFormatCode('Q #,##0.00');

        // Encabezados de la tabla (Fila 6)
        $headerRange = 'A6:P6';
        $sheet->getStyle($headerRange)->getFont()->setBold(true)->setColor(new \PhpOffice\PhpSpreadsheet\Style\Color('FFFFFFFF'))->setSize(10);
        $sheet->getStyle($headerRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FF1E40AF');
        $sheet->getStyle($headerRange)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getRowDimension(6)->setRowHeight(25);

        // Formato para filas de datos
        $totalRows = count($this->items);
        if ($totalRows > 0) {
            $dataStart = 7;
            $dataEnd = 6 + $totalRows;
            $totalsRow = $dataEnd + 1;

            // Formato de moneda para columnas H a P
            $sheet->getStyle("H{$dataStart}:P{$totalsRow}")->getNumberFormat()->setFormatCode('Q #,##0.00');

            // Alineaciones
            $sheet->getStyle("A{$dataStart}:A{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("D{$dataStart}:F{$dataEnd}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("H{$dataStart}:P{$totalsRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);

            // Bordes en la tabla
            $tableRange = "A6:P{$totalsRow}";
            $sheet->getStyle($tableRange)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setARGB('FFE5E7EB');

            // Fila de totales
            $sheet->getStyle("A{$totalsRow}:P{$totalsRow}")->getFont()->setBold(true)->setSize(10);
            $sheet->getStyle("A{$totalsRow}:P{$totalsRow}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFF3F4F6');
            $sheet->getStyle("A{$totalsRow}:P{$totalsRow}")->getBorders()->getTop()->setBorderStyle(Border::BORDER_DOUBLE)->getColor()->setARGB('FF1E40AF');
        }

        return [];
    }
}
