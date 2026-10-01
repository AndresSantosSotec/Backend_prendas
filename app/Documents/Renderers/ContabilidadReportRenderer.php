<?php

namespace App\Documents\Renderers;

use App\Documents\Contracts\DocumentRenderer;
use App\Documents\DTOs\DocumentResult;
use App\Models\TbDoc;
use Barryvdh\DomPDF\Facade\Pdf;

final class ContabilidadReportRenderer implements DocumentRenderer
{
    public function render(array $data, ?TbDoc $config = null): DocumentResult
    {
        $tipo = $config?->tipo_documento ?? ($data['tipo_documento'] ?? 'reporte_contable');

        $defaultView = match ($tipo) {
            'balance_general' => 'reportes.contabilidad.balance-general',
            'estado_resultados' => 'reportes.contabilidad.estado-resultados',
            'partida_contable' => 'reportes.contabilidad.partida-contable',
            default => 'reportes.contabilidad.partida-contable',
        };

        $vista = !empty($config?->vista_pdf) && view()->exists($config->vista_pdf)
            ? $config->vista_pdf
            : $defaultView;

        $pdf = Pdf::loadView($vista, $data);
        
        // Orientación según tipo
        if ($tipo === 'balance_general' || ($data['orientacion'] ?? '') === 'landscape') {
            $pdf->setPaper('letter', 'landscape');
        } else {
            $pdf->setPaper('letter', 'portrait');
        }

        $filename = ucfirst($tipo) . '_' . date('Ymd_His') . '.pdf';

        return new DocumentResult(
            content: $pdf->output(),
            filename: $filename,
            mimeType: 'application/pdf',
            meta: [
                'tipo_documento' => $tipo,
            ]
        );
    }
}
