<?php

namespace App\Models\Concerns;

use Illuminate\Database\QueryException;

trait MarcaEstadoAnulado
{
    public function estaAnulado(): bool
    {
        return in_array((string) $this->estado, ['anulado', 'rechazado'], true);
    }

    /**
     * Marca el movimiento como anulado.
     * Si MySQL aún no tiene 'anulado' en el ENUM, usa 'rechazado' para no romper el flujo:
     * el saldo de caja/bóveda solo cuenta estado 'aplicado'/'aprobado'.
     */
    public function marcarAnulado(array $extra = []): void
    {
        foreach ($extra as $key => $value) {
            $this->{$key} = $value;
        }

        $this->estado = 'anulado';

        try {
            $this->save();
        } catch (QueryException $e) {
            $this->estado = 'rechazado';
            $this->save();
        }
    }
}
