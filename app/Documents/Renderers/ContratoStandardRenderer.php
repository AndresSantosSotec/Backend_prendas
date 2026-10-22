<?php

namespace App\Documents\Renderers;

use App\Documents\Contracts\DocumentRenderer;
use App\Documents\DTOs\DocumentResult;
use App\Models\TbDoc;
use Barryvdh\DomPDF\Facade\Pdf;

final class ContratoStandardRenderer implements DocumentRenderer
{
    public function render(array $data, ?TbDoc $config = null): DocumentResult
    {
        $vista = !empty($config?->vista_pdf) && view()->exists($config->vista_pdf)
            ? $config->vista_pdf
            : 'creditos.contrato';

        $pdf = Pdf::loadView($vista, $data);
        $pdf->setPaper('letter', 'portrait');

        $credito = $data['credito'] ?? null;
        $codigoCredito = is_object($credito)
            ? ($credito->codigo_credito ?? $credito->numero_credito ?? $credito->id)
            : ($credito['codigo_credito'] ?? $credito['numero_credito'] ?? time());

        $filename = 'Contrato_Credito_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $codigoCredito) . '.pdf';

        return new DocumentResult(
            content: $pdf->output(),
            filename: $filename,
            mimeType: 'application/pdf',
            meta: [
                'formato' => 'estandar',
                'tipo_documento' => 'contrato_credito',
            ]
        );
    }
}
