<?php

namespace App\Enums;

enum EstadoVenta: string
{
    case PENDIENTE = 'pendiente';
    case PAGADA = 'pagada';
    case APARTADO = 'apartado';
    case PLAN_PAGOS = 'plan_pagos';
    case CANCELADA = 'cancelada';
    case DEVUELTA = 'devuelta';
    case ANULADA = 'anulada';

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
            self::PENDIENTE => 'Pendiente',
            self::PAGADA => 'Pagada',
            self::APARTADO => 'Apartado',
            self::PLAN_PAGOS => 'Plan de Pagos',
            self::CANCELADA => 'Cancelada',
            self::DEVUELTA => 'Devuelta',
            self::ANULADA => 'Anulada',
        };
    }

    /**
     * Estados que requieren pago
     */
    public static function requierenPago(): array
    {
        return [
            self::PENDIENTE->value,
            self::APARTADO->value,
            self::PLAN_PAGOS->value,
        ];
    }

    /**
     * Estados completados
     */
    public static function completados(): array
    {
        return [
            self::PAGADA->value,
        ];
    }

    /**
     * Estados cancelados/anulados
     */
    public static function inactivos(): array
    {
        return [
            self::CANCELADA->value,
            self::DEVUELTA->value,
            self::ANULADA->value,
        ];
    }
}
