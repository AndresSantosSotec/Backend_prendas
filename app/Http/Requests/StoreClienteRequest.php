<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreClienteRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nombres' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            // DPI ahora es opcional, solo validar unique si se proporciona
            'dpi' => 'nullable|string|max:20|unique:clientes,dpi,NULL,id,eliminado,0',
            'nit' => 'nullable|string|max:20',
            'fecha_nacimiento' => 'required|date|before:today',
            'genero' => 'required|in:masculino,femenino,otro',
            'estado_civil' => 'nullable|string|max:100',
            'profesion' => 'nullable|string|max:150',
            'telefono' => 'nullable|string|max:20',
            'telefono_secundario' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'direccion' => 'required|string',
            'municipio' => 'nullable|string|max:255',
            'departamento_geoname_id' => 'nullable|integer',
            'municipio_geoname_id' => 'nullable|integer',
            'fotografia' => 'nullable|string',
            'estado' => 'nullable|in:activo,inactivo',
            'sucursal' => 'nullable|string|max:255',
            'tipo_cliente' => 'nullable|in:regular,vip',
            'notas' => 'nullable|string',
            'es_pep' => 'nullable|boolean',
            'entidad' => 'required_if:es_pep,true|string|max:255',
            'puesto' => 'required_if:es_pep,true|string|max:255',
            'paisEntidad' => 'nullable|string|max:255',
            'origen' => [
                'required_if:es_pep,true',
                 Rule::in([
                    'Bienes muebles e inmuebles por herencia',
                    'Bienes muebles e inmuebles',
                    'Negocio propio',
                    'Servicios profesionales',
                    'Préstamos bancarios',
                    'Trabajos anteriores',
                    'Trabajo actual',
                    'Otros',
                ]),
            ],
            'complemento_pep' => 'nullable|array',
            'complemento_pep.tipo' => 'required_with:complemento_pep|in:parentesco',
            'complemento_pep.parentesco' => 'required_if:complemento_pep.tipo,parentesco|string|max:255',
            'complemento_pep.motivoAsociacion' => 'nullable|string|max:255',
            'complemento_pep.detalleOtro' => 'nullable|string|max:255',
            'complemento_pep.primerNombre' => 'required_if:complemento_pep.tipo,parentesco|string|max:255',
            'complemento_pep.segundoNombre' => 'nullable|string|max:255',
            'complemento_pep.otrosNombres' => 'nullable|string|max:255',
            'complemento_pep.primerApellido' => 'required_if:complemento_pep.tipo,parentesco|string|max:255',
            'complemento_pep.segundoApellido' => 'nullable|string|max:255',
            'complemento_pep.apellidoCasada' => 'nullable|string|max:255',
            'complemento_pep.genero' => 'required_if:complemento_pep.tipo,parentesco|in:masculino,femenino',
            'complemento_pep.condicion' => 'required_if:complemento_pep.tipo,parentesco|in:nacional,extranjero',
            'complemento_pep.entidad' => 'required_if:complemento_pep.tipo,parentesco|string|max:255',
            'complemento_pep.puesto' => 'required_if:complemento_pep.tipo,parentesco|string|max:255',
            'complemento_pep.pais' => 'required_if:complemento_pep.tipo,parentesco|string|max:255',
            'complemento_pep.estado' => 'nullable|in:activo,inactivo',
            'referencias' => 'sometimes|array',
            'referencias.*.nombre' => 'required_with:referencias.*.telefono|string|max:255',
            'referencias.*.telefono' => 'required_with:referencias.*.nombre|string|max:20',
            'referencias.*.relacion' => 'nullable|string|max:100',
        ];
    }

    /**
     * Mensajes personalizados de validación
     */
    public function messages(): array
    {
        return [
            'nombres.required' => 'Los nombres son obligatorios',
            'nombres.max' => 'Los nombres no pueden exceder 255 caracteres',
            'apellidos.required' => 'Los apellidos son obligatorios',
            'apellidos.max' => 'Los apellidos no pueden exceder 255 caracteres',
            'dpi.unique' => 'Ya existe un cliente registrado con este número de DPI. Por favor, verifique el número ingresado.',
            'dpi.max' => 'El DPI no puede exceder 20 caracteres',
            'nit.max' => 'El NIT no puede exceder 20 caracteres',
            'fecha_nacimiento.required' => 'La fecha de nacimiento es obligatoria',
            'fecha_nacimiento.date' => 'La fecha de nacimiento debe ser una fecha válida',
            'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy',
            'genero.required' => 'El género es obligatorio',
            'genero.in' => 'El género debe ser: masculino, femenino u otro',
            'telefono.max' => 'El teléfono no puede exceder 20 caracteres',
            'telefono_secundario.max' => 'El teléfono secundario no puede exceder 20 caracteres',
            'email.email' => 'El correo electrónico no tiene un formato válido',
            'email.max' => 'El correo electrónico no puede exceder 255 caracteres',
            'direccion.required' => 'La dirección es obligatoria',
            'municipio.max' => 'El municipio no puede exceder 255 caracteres',
            'estado.in' => 'El estado debe ser: activo o inactivo',
            'tipo_cliente.in' => 'El tipo de cliente debe ser: regular o vip',
        ];
    }

    /**
     * Atributos personalizados para mensajes de error
     */
    public function attributes(): array
    {
        return [
            'dpi' => 'DPI',
            'nit' => 'NIT',
            'email' => 'correo electrónico',
        ];
    }
}
