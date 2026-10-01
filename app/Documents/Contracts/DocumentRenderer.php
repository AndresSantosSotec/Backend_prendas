<?php

namespace App\Documents\Contracts;

use App\Documents\DTOs\DocumentResult;
use App\Models\TbDoc;

interface DocumentRenderer
{
    /**
     * Renderizar el documento con los datos provistos y la configuración de plantilla activa.
     *
     * @param array $data Datos preparados del modelo/negocio
     * @param TbDoc|null $config Configuración institucional (logos, textos, encabezados, firmantes)
     * @return DocumentResult
     */
    public function render(array $data, ?TbDoc $config = null): DocumentResult;
}
