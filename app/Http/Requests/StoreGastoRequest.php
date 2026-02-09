<?php

namespace App\Http\Requests;

use App\Models\Gasto;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Request para crear un gasto
 */
class StoreGastoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'nombre' => 'required|string|max:100',
            'tipo' => ['required', Rule::in([Gasto::TIPO_FIJO, Gasto::TIPO_VARIABLE])],
            'porcentaje' => [
                'nullable',
                'numeric',
                'min:0',
                'max:100',
                Rule::requiredIf($this->tipo === Gasto::TIPO_VARIABLE),
            ],
            'monto' => [
                'nullable',
                'numeric',
                'min:0',
                Rule::requiredIf($this->tipo === Gasto::TIPO_FIJO),
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
            'tipo.required' => 'El tipo de gasto es obligatorio',
            'tipo.in' => 'El tipo debe ser FIJO o VARIABLE',
            'porcentaje.required_if' => 'El porcentaje es obligatorio para gastos variables',
            'porcentaje.min' => 'El porcentaje no puede ser negativo',
            'porcentaje.max' => 'El porcentaje no puede exceder 100',
            'monto.required_if' => 'El monto es obligatorio para gastos fijos',
            'monto.min' => 'El monto no puede ser negativo',
        ];
    }

    /**
     * Preparar datos antes de validación
     */
    protected function prepareForValidation(): void
    {
        // Limpiar campos según el tipo
        if ($this->tipo === Gasto::TIPO_FIJO) {
            $this->merge(['porcentaje' => null]);
        } elseif ($this->tipo === Gasto::TIPO_VARIABLE) {
            $this->merge(['monto' => null]);
        }
    }
}
