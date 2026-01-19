<?php

namespace App\Enums;

enum EstadoCredito: string
{
    case SOLICITADO = 'solicitado';
    case EN_ANALISIS = 'en_analisis';
    case APROBADO = 'aprobado';
    case RECHAZADO = 'rechazado';
    case VIGENTE = 'vigente';
    case EN_MORA = 'en_mora';
    case VENCIDO = 'vencido';
    case RESCATADO = 'rescatado';
    case REMATADO = 'rematado';
    case VENDIDO = 'vendido';
    case EN_INVENTARIO = 'en_inventario';
    case ANULADO = 'anulado';
    case RENOVADO = 'renovado';

    /**
     * Obtener todos los valores como array
     */
    public static function valores(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * Verificar si un estado es válido
     */
    public static function esValido(string $estado): bool
    {
        return in_array($estado, self::valores());
    }

    /**
     * Obtener transiciones válidas desde un estado
     */
    public static function transicionesDesde(string $estado): array
    {
        $transiciones = [
            self::SOLICITADO->value => [
                self::EN_ANALISIS->value,
                self::APROBADO->value,
                self::RECHAZADO->value,
                self::ANULADO->value,
            ],
            self::EN_ANALISIS->value => [
                self::APROBADO->value,
                self::RECHAZADO->value,
                self::ANULADO->value,
            ],
            self::APROBADO->value => [
                self::VIGENTE->value,
                self::ANULADO->value,
            ],
            self::VIGENTE->value => [
                self::EN_MORA->value,
                self::VENCIDO->value,
                self::RESCATADO->value,
                self::RENOVADO->value,
                self::ANULADO->value,
            ],
            self::EN_MORA->value => [
                self::VIGENTE->value,
                self::VENCIDO->value,
                self::RESCATADO->value,
                self::RENOVADO->value,
                self::REMATADO->value,
            ],
            self::VENCIDO->value => [
                self::RESCATADO->value,
                self::RENOVADO->value,
                self::REMATADO->value,
                self::VENDIDO->value,
                self::EN_INVENTARIO->value,
            ],
            self::REMATADO->value => [
                self::VENDIDO->value,
                self::EN_INVENTARIO->value,
            ],
            self::EN_INVENTARIO->value => [
                self::VENDIDO->value,
            ],
        ];

        return $transiciones[$estado] ?? [];
    }

    /**
     * Verificar si una transición es válida
     */
    public static function transicionValida(string $estadoActual, string $estadoNuevo): bool
    {
        $transiciones = self::transicionesDesde($estadoActual);
        return in_array($estadoNuevo, $transiciones);
    }

    /**
     * Obtener etiquetas en español
     */
    public function etiqueta(): string
    {
        return match($this) {
            self::SOLICITADO => 'Solicitado',
            self::EN_ANALISIS => 'En Análisis',
            self::APROBADO => 'Aprobado',
            self::RECHAZADO => 'Rechazado',
            self::VIGENTE => 'Vigente',
            self::EN_MORA => 'En Mora',
            self::VENCIDO => 'Vencido',
            self::RESCATADO => 'Rescatado',
            self::REMATADO => 'Rematado',
            self::VENDIDO => 'Vendido',
            self::EN_INVENTARIO => 'En Inventario',
            self::ANULADO => 'Anulado',
            self::RENOVADO => 'Renovado',
        };
    }

    /**
     * Obtener colores para UI
     */
    public function color(): string
    {
        return match($this) {
            self::SOLICITADO => 'gray',
            self::EN_ANALISIS => 'yellow',
            self::APROBADO => 'green',
            self::RECHAZADO => 'red',
            self::VIGENTE => 'blue',
            self::EN_MORA => 'orange',
            self::VENCIDO => 'red',
            self::RESCATADO => 'green',
            self::REMATADO => 'purple',
            self::VENDIDO => 'gray',
            self::EN_INVENTARIO => 'indigo',
            self::ANULADO => 'gray',
            self::RENOVADO => 'blue',
        };
    }
}
