<?php

namespace App\Documents\Renderers;

use App\Documents\Contracts\DocumentRenderer;
use App\Documents\DTOs\DocumentResult;
use App\Models\TbDoc;
use Barryvdh\DomPDF\Facade\Pdf;

final class ReciboTicketRenderer implements DocumentRenderer
{
    public function render(array $data, ?TbDoc $config = null): DocumentResult
    {
        $vista = !empty($config?->vista_pdf) && view()->exists($config->vista_pdf)
            ? $config->vista_pdf
            : (view()->exists('creditos.recibo_pago') ? 'creditos.recibo_pago' : 'creditos.recibo');

        $pdf = Pdf::loadView($vista, $data);
        // Ancho 80mm = ~226.77 pt; alto flexible según contenido
        $pdf->setPaper([0, 0, 226.77, 650], 'portrait');

        $numeroRecibo = $data['recibo']['numero_recibo'] ?? ($data['recibo']->numero_recibo ?? 'TICKET-' . time());
        $filename = 'Ticket_' . preg_replace('/[^A-Za-z0-9_\-]/', '_', (string) $numeroRecibo) . '.pdf';

        return new DocumentResult(
            content: $pdf->output(),
            filename: $filename,
            mimeType: 'application/pdf',
            meta: [
                'formato' => 'ticket_80mm',
                'tipo_documento' => 'recibo_pago',
            ]
        );
    }
}
