<?php

namespace App\Http\Requests;

use App\Models\Gasto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request para actualizar un gasto
 */
class UpdateGastoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'sometimes|required|string|max:100',
            'tipo' => ['sometimes', 'required', Rule::in([Gasto::TIPO_FIJO, Gasto::TIPO_VARIABLE])],
            'porcentaje' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
            ],
            'monto' => [
                'nullable',
                'numeric',
                'min:0',
            ],
            'descripcion' => 'nullable|string|max:500',
            'activo' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'nombre.required' => 'El nombre del gasto es obligatorio',
            'nombre.max' => 'El nombre no puede exceder 100 caracteres',
            'tipo.in' => 'El tipo debe ser FIJO o VARIABLE',
            'porcentaje.min' => 'El porcentaje no puede ser negativo',
            'porcentaje.max' => 'El porcentaje no puede exceder 100',
            'monto.min' => 'El monto no puede ser negativo',
        ];
    }

    /**
     * Validación adicional después de las reglas básicas
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tipo = $this->tipo ?? $this->route('gasto')?->tipo;

            if ($tipo === Gasto::TIPO_FIJO && $this->has('monto') && empty($this->monto)) {
                $validator->errors()->add('monto', 'El monto es obligatorio para gastos fijos');
            }

            if ($tipo === Gasto::TIPO_VARIABLE && $this->has('porcentaje') && empty($this->porcentaje)) {
                $validator->errors()->add('porcentaje', 'El porcentaje es obligatorio para gastos variables');
            }
        });
    }
}
