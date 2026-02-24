<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PagarMultipleCuotasRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'cuota_ids' => 'required|array',
            'cuota_ids.*' => 'required|integer|exists:venta_credito_plan_pagos,id',
            'caja_id' => 'required|integer|exists:caja_apertura_cierres,id',
            'observacion' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'cuota_ids.required' => 'Debe seleccionar al menos una cuota.',
            'caja_id.required' => 'Debe indicar la caja para registrar el pago.',
        ];
    }
}
