<?php

namespace App\Exports;

use App\Models\Boveda;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

class BovedaConsolidacionExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
{
    protected $filters;
    protected $userId;
    protected $userRole;

    public function __construct($filters = [], $userId = null, $userRole = 'usuario')
    {
        $this->filters = $filters;
        $this->userId = $userId;
        $this->userRole = $userRole;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function collection()
    {
        $query = Boveda::with(['sucursal', 'movimientosAprobados']);

        // Filtrar por sucursal si no es admin
        if ($this->userRole !== 'administrador' && isset($this->filters['sucursal_id'])) {
            $query->where('sucursal_id', $this->filters['sucursal_id']);
        }

        if (isset($this->filters['sucursal_id'])) {
            $query->where('sucursal_id', $this->filters['sucursal_id']);
        }

        $bovedas = $query->activas()->get();

        return $bovedas->map(function ($boveda) {
            $movimientosQuery = $boveda->movimientosAprobados();

            if (isset($this->filters['fecha_inicio'])) {
                $movimientosQuery->whereDate('created_at', '>=', $this->filters['fecha_inicio']);
            }

            if (isset($this->filters['fecha_fin'])) {
                $movimientosQuery->whereDate('created_at', '<=', $this->filters['fecha_fin']);
            }

            $movimientos = $movimientosQuery->get();
            $entradas = $movimientos->filter(fn($m) => in_array($m->tipo_movimiento, ['entrada', 'transferencia_entrada']));
            $salidas = $movimientos->filter(fn($m) => in_array($m->tipo_movimiento, ['salida', 'transferencia_salida']));

            return (object)[
                'codigo' => $boveda->codigo,
                'nombre' => $boveda->nombre,
                'sucursal' => $boveda->sucursal->nombre ?? 'N/A',
                'tipo' => $boveda->tipo,
                'total_entradas' => $entradas->sum('monto'),
                'total_salidas' => $salidas->sum('monto'),
                'numero_movimientos' => $movimientos->count(),
                'saldo_actual' => $boveda->saldo_actual,
            ];
        });
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Código',
            'Bóveda',
            'Sucursal',
            'Tipo',
            'Total Entradas',
            'Total Salidas',
            'Movimientos',
            'Saldo Actual',
        ];
    }

    /**
     * @param object $data
     * @return array
     */
    public function map($data): array
    {
        return [
            $data->codigo,
            $data->nombre,
            $data->sucursal,
            ucfirst($data->tipo),
            'Q' . number_format($data->total_entradas, 2),
            'Q' . number_format($data->total_salidas, 2),
            $data->numero_movimientos,
            'Q' . number_format($data->saldo_actual, 2),
        ];
    }

    /**
     * @param Worksheet $sheet
     * @return void
     */
    public function styles(Worksheet $sheet)
    {
        // Estilo para la cabecera
        $sheet->getStyle('A1:H1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => 'D32F2F'],
            ],
            'alignment' => [
                'horizontal' => Alignment::HORIZONTAL_CENTER,
                'vertical' => Alignment::VERTICAL_CENTER,
            ],
        ]);

        // Ajustar ancho de columnas
        $sheet->getColumnDimension('A')->setWidth(15);
        $sheet->getColumnDimension('B')->setWidth(25);
        $sheet->getColumnDimension('C')->setWidth(20);
        $sheet->getColumnDimension('D')->setWidth(12);
        $sheet->getColumnDimension('E')->setWidth(18);
        $sheet->getColumnDimension('F')->setWidth(18);
        $sheet->getColumnDimension('G')->setWidth(15);
        $sheet->getColumnDimension('H')->setWidth(18);

        return [];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Consolidación Bóvedas';
    }
}
