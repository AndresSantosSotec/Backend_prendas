<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CategoriaProducto extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'categoria_productos';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'color',
        'icono',
        'orden',
        'activa',
        'tasa_interes_default',
        'tasa_mora_default',
        'plazo_maximo_dias',
        'porcentaje_prestamo_maximo',
        'metodo_calculo_default',
        'afecta_interes_mensual',
        'permite_pago_capital_diferente',
        'campos_formulario',
        'campos_adicionales',
        'campos_dinamicos',
    ];

    protected $casts = [
        'activa' => 'boolean',
        'orden' => 'integer',
        'tasa_interes_default' => 'decimal:2',
        'tasa_mora_default' => 'decimal:2',
        'plazo_maximo_dias' => 'integer',
        'porcentaje_prestamo_maximo' => 'decimal:2',
        'afecta_interes_mensual' => 'boolean',
        'permite_pago_capital_diferente' => 'boolean',
        'campos_formulario' => 'array',
        'campos_adicionales' => 'array',
        'campos_dinamicos' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];
}
