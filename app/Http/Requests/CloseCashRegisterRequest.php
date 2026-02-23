<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CloseCashRegisterRequest extends FormRequest
{
    /**
     * Autorizar la petición.
     */
    public function authorize(): bool
    {
        // La autorización fina se maneja en el controlador (propietario de la caja / admin)
        return true;
    }

    /**
     * Reglas de validación para el cierre de caja con opción de envío a bóveda.
     *
     * Campos clave:
     *  - monto_total_efectivo: total contado en caja
     *  - desglose_denominaciones: arreglo {denominación => cantidad}
     *  - enviar_a_boveda: booleano para indicar si se transfiere a bóveda
     *  - boveda_destino_id: bóveda seleccionada (requerida si enviar_a_boveda = true)
     *  - cajero_id: id explícito del cajero (opcional, normalmente se toma de la caja)
     */
    public function rules(): array
    {
        return [
            'monto_total_efectivo' => ['required', 'numeric', 'min:0'],
            'desglose_denominaciones' => ['nullable', 'array'],
            'desglose_denominaciones.*' => ['integer', 'min:0'],

            'enviar_a_boveda' => ['nullable', 'boolean'],
            'boveda_destino_id' => ['nullable', 'required_if:enviar_a_boveda,1', 'exists:bovedas,id'],

            'cajero_id' => ['nullable', 'integer', 'exists:users,id'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'monto_total_efectivo' => 'monto total en efectivo',
            'desglose_denominaciones' => 'desglose por denominaciones',
            'boveda_destino_id' => 'bóveda destino',
            'cajero_id' => 'cajero',
        ];
    }
}

