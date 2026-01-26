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
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class BovedasExport implements FromCollection, WithHeadings, WithMapping, WithStyles, WithTitle
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
        $query = Boveda::with(['sucursal', 'responsable']);

        // Filtros por permisos
        if ($this->userRole !== 'administrador' && $this->userRole !== 'gerente' && isset($this->filters['sucursal_id'])) {
            $query->where('sucursal_id', $this->filters['sucursal_id']);
        }

        // Filtros adicionales
        if (isset($this->filters['sucursal_id'])) {
            $query->where('sucursal_id', $this->filters['sucursal_id']);
        }

        if (isset($this->filters['activa'])) {
            $query->where('activa', $this->filters['activa']);
        }

        if (isset($this->filters['tipo'])) {
            $query->where('tipo', $this->filters['tipo']);
        }

        return $query->orderBy('sucursal_id')->orderBy('codigo')->get();
    }

    /**
     * @return array
     */
    public function headings(): array
    {
        return [
            'Código',
            'Nombre',
            'Sucursal',
            'Tipo',
            'Saldo Actual',
            'Saldo Mínimo',
            'Saldo Máximo',
            'Responsable',
            'Estado',
            'Última Apertura',
            'Creada',
        ];
    }

    /**
     * @param Boveda $boveda
     * @return array
     */
    public function map($boveda): array
    {
        return [
            $boveda->codigo,
            $boveda->nombre,
            $boveda->sucursal->nombre ?? 'N/A',
            ucfirst($boveda->tipo),
            'Q' . number_format($boveda->saldo_actual, 2),
            'Q' . number_format($boveda->saldo_minimo, 2),
            $boveda->saldo_maximo ? 'Q' . number_format($boveda->saldo_maximo, 2) : 'N/A',
            $boveda->responsable->name ?? 'N/A',
            $boveda->activa ? 'Activa' : 'Inactiva',
            $boveda->ultima_apertura ? $boveda->ultima_apertura->format('d/m/Y H:i') : 'N/A',
            $boveda->created_at->format('d/m/Y H:i'),
        ];
    }

    /**
     * @param Worksheet $sheet
     * @return void
     */
    public function styles(Worksheet $sheet)
    {
        // Estilo para la cabecera
        $sheet->getStyle('A1:K1')->applyFromArray([
            'font' => [
                'bold' => true,
                'color' => ['rgb' => 'FFFFFF'],
            ],
            'fill' => [
                'fillType' => Fill::FILL_SOLID,
                'startColor' => ['rgb' => '4472C4'],
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
        $sheet->getColumnDimension('E')->setWidth(15);
        $sheet->getColumnDimension('F')->setWidth(15);
        $sheet->getColumnDimension('G')->setWidth(15);
        $sheet->getColumnDimension('H')->setWidth(20);
        $sheet->getColumnDimension('I')->setWidth(12);
        $sheet->getColumnDimension('J')->setWidth(18);
        $sheet->getColumnDimension('K')->setWidth(18);

        return [];
    }

    /**
     * @return string
     */
    public function title(): string
    {
        return 'Bóvedas';
    }
}
