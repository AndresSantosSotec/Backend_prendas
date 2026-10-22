<?php

namespace App\Documents\Renderers;

use App\Documents\Contracts\DocumentRenderer;
use App\Documents\DTOs\DocumentResult;
use App\Models\TbDoc;
use Barryvdh\DomPDF\Facade\Pdf;

final class ReciboStandardRenderer implements DocumentRenderer
{
    public function render(array $data, ?TbDoc $config = null): DocumentResult
    {
        $vista = !empty($config?->vista_pdf) && view()->exists($config->vista_pdf)
            ? $config->vista_pdf
            : (view()->exists('creditos.recibo') ? 'creditos.recibo' : 'creditos.recibo_pago');

        $pdf = Pdf::loadView($vista, $data);
        $pdf->setPaper('letter', 'portrait');

        $numeroRecibo = $data['recibo']['numero_recibo'] ?? ($data['recibo']->numero_recibo ?? 'REC-' . time());
        $filename = 'Recibo_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $numeroRecibo) . '.pdf';

        return new DocumentResult(
            content: $pdf->output(),
            filename: $filename,
            mimeType: 'application/pdf',
            meta: [
                'formato' => 'estandar',
                'tipo_documento' => 'recibo_pago',
            ]
        );
    }
}
