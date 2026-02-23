<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CashRegisterClosureResource extends JsonResource
{
    /**
     * Transforma la respuesta de cierre de caja para consumo del frontend.
     *
     * Espera un arreglo con:
     *  - 'caja' => instancia de CajaAperturaCierre
     *  - 'boveda_movimiento' => instancia de BovedaMovimiento|null
     */
    public function toArray(Request $request): array
    {
        $caja = $this->resource['caja'] ?? null;
        $movimiento = $this->resource['boveda_movimiento'] ?? null;

        return [
            'caja' => $caja ? [
                'id' => $caja->id,
                'estado' => $caja->estado,
                'fecha_apertura' => $caja->fecha_apertura,
                'hora_apertura' => $caja->hora_apertura,
                'fecha_cierre' => $caja->fecha_cierre,
                'saldo_inicial' => (float) $caja->saldo_inicial,
                'saldo_final' => (float) $caja->saldo_final,
                'diferencia' => (float) ($caja->diferencia ?? 0),
                'resultado_arqueo' => $caja->resultado_arqueo,
                'detalles_arqueo' => $caja->detalles_arqueo,
                'user' => $caja->user ? [
                    'id' => $caja->user->id,
                    'name' => $caja->user->name,
                    'rol' => $caja->user->rol ?? null,
                ] : null,
                'sucursal' => $caja->sucursal ? [
                    'id' => $caja->sucursal->id,
                    'nombre' => $caja->sucursal->nombre,
                ] : null,
                'boveda_destino' => $caja->bovedaDestino ? [
                    'id' => $caja->bovedaDestino->id,
                    'codigo' => $caja->bovedaDestino->codigo,
                    'nombre' => $caja->bovedaDestino->nombre,
                ] : null,
            ] : null,

            'vault_transfer' => $movimiento ? [
                'id' => $movimiento->id,
                'boveda_id' => $movimiento->boveda_id,
                'tipo_movimiento' => $movimiento->tipo_movimiento,
                'tipo_movimiento_label' => $movimiento->tipo_movimiento_label,
                'monto' => (float) $movimiento->monto,
                'concepto' => $movimiento->concepto,
                'estado' => $movimiento->estado,
                'fecha_aprobacion' => $movimiento->fecha_aprobacion,
                'desglose_denominaciones' => $movimiento->desglose_denominaciones,
            ] : null,

            'meta' => [
                'message' => $movimiento
                    ? 'Caja cerrada y saldo transferido a bóveda correctamente.'
                    : 'Caja cerrada correctamente (sin transferencia a bóveda).',
            ],
        ];
    }
}

