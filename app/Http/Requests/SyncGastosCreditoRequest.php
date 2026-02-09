<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Request para sincronizar gastos de un crédito
 */
class SyncGastosCreditoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'gas_ids' => 'required|array',
            'gas_ids.*' => 'integer|exists:gastos,id_gasto',
        ];
    }

    public function messages(): array
    {
        return [
            'gas_ids.required' => 'Debe proporcionar la lista de gastos',
            'gas_ids.array' => 'La lista de gastos debe ser un array',
            'gas_ids.*.integer' => 'Cada ID de gasto debe ser un número entero',
            'gas_ids.*.exists' => 'Uno o más gastos no existen',
        ];
    }
}
