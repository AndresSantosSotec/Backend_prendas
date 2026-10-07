<?php

namespace App\Documents;

use App\Documents\Contracts\DocumentRenderer;
use App\Documents\Renderers\ContabilidadReportRenderer;
use App\Documents\Renderers\ContratoStandardRenderer;
use App\Documents\Renderers\PlanPagosStandardRenderer;
use App\Documents\Renderers\ReciboStandardRenderer;
use App\Documents\Renderers\ReciboTicketRenderer;
use Illuminate\Support\Facades\App;
use InvalidArgumentException;

final class RendererRegistry
{
    /**
     * Lista blanca estricta de renderizadores permitidos en el sistema.
     * La base de datos almacena una clave (string), nunca una clase o ruta ejecutable.
     */
    private const RENDERERS = [
        // Recibos y Comprobantes de pago
        'recibo.estandar'      => ReciboStandardRenderer::class,
        'recibo.ticket_80mm'   => ReciboTicketRenderer::class,
        'recibo.compacto'      => ReciboStandardRenderer::class,

        // Contratos de empeño / compra
        'contrato.estandar'    => ContratoStandardRenderer::class,
        'contrato.prendamas'   => ContratoStandardRenderer::class,
        'contrato.cemadec'     => ContratoStandardRenderer::class,
        'contrato.compacto'    => ContratoStandardRenderer::class,
        'contrato.pruebamoi'      => ContratoStandardRenderer::class,
        'contracto.crediprendas'     => ContratoStandardRenderer::class,

        // Plan de pagos / amortización
        'plan_pagos.estandar'   => PlanPagosStandardRenderer::class,
        'plan_pagos.prendamas'  => PlanPagosStandardRenderer::class,
        'plan_pagos.ticket'     => PlanPagosStandardRenderer::class,

        // Contabilidad y Estados Financieros
        'contabilidad.balance'    => ContabilidadReportRenderer::class,
        'contabilidad.resultados' => ContabilidadReportRenderer::class,
        'contabilidad.partida'    => ContabilidadReportRenderer::class,
    ];

    /**
     * Mapeo de fallback por tipo de documento si la clave configurada no existe
     */
    private const DEFAULTS_POR_TIPO = [
        'recibo_pago'       => 'recibo.estandar',
        'recibo_compra'     => 'recibo.estandar',
        'recibo_venta'      => 'recibo.estandar',
        'contrato_credito'  => 'contrato.estandar',
        'plan_pagos'        => 'plan_pagos.estandar',
        'balance_general'   => 'contabilidad.balance',
        'estado_resultados' => 'contabilidad.resultados',
        'partida_contable'  => 'contabilidad.partida',
    ];

    /**
     * Obtener instancia del renderizador registrado por su clave
     */
    public function get(string $key): DocumentRenderer
    {
        $class = self::RENDERERS[$key] ?? null;

        if ($class === null || !class_exists($class)) {
            throw new InvalidArgumentException("Renderizador no permitido o no registrado: [{$key}].");
        }

        return App::make($class);
    }

    /**
     * Obtener renderizador por defecto para un tipo de documento
     */
    public function getPorTipo(string $tipoDocumento, ?string $variante = null): DocumentRenderer
    {
        if ($variante) {
            $key = $this->normalizarClave($tipoDocumento, $variante);
            if (isset(self::RENDERERS[$key])) {
                return $this->get($key);
            }
        }

        $defaultKey = self::DEFAULTS_POR_TIPO[$tipoDocumento] ?? 'recibo.estandar';
        return $this->get($defaultKey);
    }

    /**
     * Normalizar o mapear tipo y variante a la clave del renderizador
     */
    public function normalizarClave(string $tipoDocumento, string $variante): string
    {
        // Si ya viene con punto (ej: recibo.ticket_80mm)
        if (str_contains($variante, '.')) {
            return $variante;
        }

        $prefijo = match ($tipoDocumento) {
            'recibo_pago', 'recibo_compra', 'recibo_venta' => 'recibo',
            'contrato_credito' => 'contrato',
            'plan_pagos' => 'plan_pagos',
            'balance_general', 'estado_resultados', 'partida_contable' => 'contabilidad',
            default => 'recibo',
        };

        if ($prefijo === 'contabilidad') {
            return match ($tipoDocumento) {
                'balance_general' => 'contabilidad.balance',
                'estado_resultados' => 'contabilidad.resultados',
                default => 'contabilidad.partida',
            };
        }

        return "{$prefijo}.{$variante}";
    }

    /**
     * Catálogo de variantes legibles para la interfaz de usuario
     */
    public static function getVariantesCatalogo(): array
    {
        return [
            'estandar'       => 'Estándar Corporativo (Carta / Oficio)',
            'prendamas'      => 'Formato Notarial Prendamas (Grupo Jumerc, S.A.)',
            'ticket_80mm'    => 'Ticket Térmico (80mm / POS Ventanilla)',
            'compacto'       => 'Formato Compacto (Medio Oficio)',
            'cemadec'        => 'Formato CEMADEC (Perito Contador + Representante)',
            'moderno'        => 'Diseño Corporativo Moderno',
            'pruebamoi'      => 'Prueba 1 (Moise)',
            'crediprendas'     => 'Formato Crediprendas',
        ];
    }
}
