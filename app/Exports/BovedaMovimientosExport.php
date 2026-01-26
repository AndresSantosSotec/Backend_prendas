<?php

namespace App\Exports;

use App\Models\BovedaMovimiento;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class BovedaMovimientosExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $bovedaId;
    protected $filters;

    public function __construct($bovedaId, $filters = [])
    {
        $this->bovedaId = $bovedaId;
        $this->filters = $filters;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $query = BovedaMovimiento::with(['boveda', 'usuario', 'aprobador', 'bovedaDestino'])
            ->where('boveda_id', $this->bovedaId);

        // Filtros
        if (isset($this->filters['estado'])) {
            $query->where('estado', $this->filters['estado']);
        }

        if (isset($this->filters['tipo_movimiento'])) {
            $query->where('tipo_movimiento', $this->filters['tipo_movimiento']);
        }

        if (isset($this->filters['fecha_inicio'])) {
            $query->whereDate('created_at', '>=', $this->filters['fecha_inicio']);
        }

        if (isset($this->filters['fecha_fin'])) {
            $query->whereDate('created_at', '<=', $this->filters['fecha_fin']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Fecha',
            'Tipo Movimiento',
            'Concepto',
            'Monto',
            'Bóveda Destino',
            'Usuario',
            'Estado',
            'Aprobado Por',
            'Fecha Aprobación',
            'Referencia',
        ];
    }

    /**
     * @param BovedaMovimiento $movimiento
     * @return array
     */
    public function map($movimiento): array
    {
        $tipoMovimiento = [
            'entrada' => 'Entrada',
            'salida' => 'Salida',
            'transferencia_entrada' => 'Transferencia (Entrada)',
            'transferencia_salida' => 'Transferencia (Salida)',
        ];

        return [
            $movimiento->created_at->format('d/m/Y H:i'),
            $tipoMovimiento[$movimiento->tipo_movimiento] ?? $movimiento->tipo_movimiento,
            $movimiento->concepto,
            'Q' . number_format($movimiento->monto, 2),
            $movimiento->bovedaDestino->nombre ?? 'N/A',
            $movimiento->usuario->name ?? 'N/A',
            ucfirst($movimiento->estado),
            $movimiento->aprobador->name ?? 'N/A',
            $movimiento->fecha_aprobacion ? $movimiento->fecha_aprobacion->format('d/m/Y H:i') : 'N/A',
            $movimiento->referencia ?? 'N/A',
        ];
    }

    /**
     * @param Worksheet $sheet
     * @return void
     */
    public function styles(Worksheet $sheet)
    {
        // Estilo para la cabecera
        $sheet->getStyle('A1:J1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '2E7D32'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Ajustar ancho de columnas
        $sheet->getColumnDimension('A')->setWidth(18);
        $sheet->getColumnDimension('B')->setWidth(25);
        $sheet->getColumnDimension('C')->setWidth(35);
        $sheet->getColumnDimension('D')->setWidth(15);
        $sheet->getColumnDimension('E')->setWidth(20);
        $sheet->getColumnDimension('F')->setWidth(20);
        $sheet->getColumnDimension('G')->setWidth(12);
        $sheet->getColumnDimension('H')->setWidth(20);
        $sheet->getColumnDimension('I')->setWidth(18);
        $sheet->getColumnDimension('J')->setWidth(15);

        return [];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Movimientos Bóveda';
    }
}
