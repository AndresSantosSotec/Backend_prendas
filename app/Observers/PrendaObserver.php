<?php

namespace App\Observers;

use App\Models\Prenda;
use App\Models\CreditoPrendario;
use Illuminate\Support\Facades\Log;

class PrendaObserver
{
    /**
     * Handle the Prenda "updated" event.
     * Detecta cambios de estado en la prenda y actualiza el crédito asociado
     */
    public function updated(Prenda $prenda): void
    {
        // Solo procesar si hay un crédito prendario asociado
        if (!$prenda->credito_prendario_id) {
            return;
        }

        // Verificar si el estado cambió
        if (!$prenda->isDirty('estado')) {
            return;
        }

        $estadoAnterior = $prenda->getOriginal('estado');
        $estadoNuevo = $prenda->estado;

        Log::info('PrendaObserver: Cambio de estado detectado', [
            'prenda_id' => $prenda->id,
            'credito_id' => $prenda->credito_prendario_id,
            'estado_anterior' => $estadoAnterior,
            'estado_nuevo' => $estadoNuevo
        ]);

        // Cargar el crédito asociado
        $credito = CreditoPrendario::find($prenda->credito_prendario_id);

        if (!$credito) {
            Log::warning('PrendaObserver: Crédito no encontrado', [
                'credito_id' => $prenda->credito_prendario_id
            ]);
            return;
        }

        // Solo actualizar si el crédito está en estado vigente o en_mora
        if (!in_array($credito->estado, ['vigente', 'en_mora'])) {
            Log::info('PrendaObserver: Crédito no está en estado vigente/en_mora, no se actualiza', [
                'credito_estado' => $credito->estado
            ]);
            return;
        }

        // Reglas de actualización según el nuevo estado de la prenda
        $actualizarCredito = false;
        $nuevoEstadoCredito = null;
        $observacion = null;

        switch ($estadoNuevo) {
            case 'en_venta':
                // Prenda puesta en venta → Crédito incobrable
                $nuevoEstadoCredito = 'incobrable';
                $observacion = "Crédito marcado como incobrable automáticamente - Prenda puesta en venta (Código: {$prenda->codigo_prenda})";
                $actualizarCredito = true;
                break;

            case 'vendida':
                // Prenda vendida → Crédito incobrable
                $nuevoEstadoCredito = 'incobrable';
                $observacion = "Crédito marcado como incobrable automáticamente - Prenda vendida (Código: {$prenda->codigo_prenda})";
                $actualizarCredito = true;
                break;

            case 'recuperada':
                // Prenda recuperada → Crédito pagado
                $nuevoEstadoCredito = 'pagado';
                $observacion = "Crédito marcado como pagado automáticamente - Prenda recuperada por el cliente (Código: {$prenda->codigo_prenda})";
                $actualizarCredito = true;
                break;
        }

        if ($actualizarCredito && $nuevoEstadoCredito) {
            Log::info('PrendaObserver: Actualizando estado del crédito', [
                'credito_id' => $credito->id,
                'estado_anterior' => $credito->estado,
                'estado_nuevo' => $nuevoEstadoCredito
            ]);

            // Preparar datos para actualizar
            $datosActualizacion = [
                'estado' => $nuevoEstadoCredito,
            ];

            // Agregar observación (concatenar con la existente si hay)
            if ($observacion) {
                $observacionActual = $credito->observaciones ?? '';
                $datosActualizacion['observaciones'] = $observacionActual
                    ? $observacionActual . "\n" . $observacion
                    : $observacion;
            }

            // Actualizar fechas según el estado
            if ($nuevoEstadoCredito === 'incobrable') {
                $datosActualizacion['fecha_incobrable'] = now();
                $datosActualizacion['motivo_incobrable'] = "Prenda con estado: {$estadoNuevo}";
            } elseif ($nuevoEstadoCredito === 'pagado') {
                $datosActualizacion['fecha_pago_total'] = now();
                // Marcar capital como pagado completamente
                $datosActualizacion['capital_pagado'] = $credito->capital_pendiente + ($credito->capital_pagado ?? 0);
                $datosActualizacion['capital_pendiente'] = 0;
            }

            // Actualizar el crédito
            $credito->update($datosActualizacion);

            Log::info('PrendaObserver: Crédito actualizado exitosamente', [
                'credito_id' => $credito->id,
                'nuevo_estado' => $nuevoEstadoCredito
            ]);
        }
    }

    /**
     * Handle the Prenda "updating" event.
     * Validación antes de actualizar
     */
    public function updating(Prenda $prenda): bool
    {
        // Validar que no se pueda cambiar a en_venta si el crédito está pagado
        if ($prenda->isDirty('estado') && $prenda->estado === 'en_venta') {
            if ($prenda->credito_prendario_id) {
                $credito = CreditoPrendario::find($prenda->credito_prendario_id);

                if ($credito && $credito->estado === 'pagado') {
                    Log::warning('PrendaObserver: Intento de marcar prenda como en_venta con crédito pagado', [
                        'prenda_id' => $prenda->id,
                        'credito_id' => $credito->id
                    ]);

                    // No permitir el cambio
                    throw new \Exception('No se puede marcar como "en venta" una prenda cuyo crédito ya está pagado. La prenda debe estar recuperada.');
                }
            }
        }

        return true;
    }
}
