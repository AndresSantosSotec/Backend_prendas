<?php

namespace App\Enums;

enum EstadoPrenda: string
{
    case EN_CUSTODIA = 'en_custodia';
    case RECUPERADA = 'recuperada';
    case EN_VENTA = 'en_venta';
    case VENDIDA = 'vendida';
    case PERDIDA = 'perdida';
    case DETERIORADA = 'deteriorada';
    case DEVUELTA = 'devuelta';

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
     * Obtener label legible
     */
    public function label(): string
    {
        return match($this) {
            self::EN_CUSTODIA => 'En Custodia',
            self::RECUPERADA => 'Recuperada',
            self::EN_VENTA => 'En Venta',
            self::VENDIDA => 'Vendida',
            self::PERDIDA => 'Perdida',
            self::DETERIORADA => 'Deteriorada',
            self::DEVUELTA => 'Devuelta',
        };
    }

    /**
     * Estados que permiten venta
     */
    public static function permiteVenta(): array
    {
        return [
            self::EN_VENTA->value,
        ];
    }

    /**
     * Estados finales (no permiten cambios)
     */
    public static function estadosFinales(): array
    {
        return [
            self::VENDIDA->value,
            self::PERDIDA->value,
            self::RECUPERADA->value,
        ];
    }
}
