<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OtroGastoTipo extends Model
{
    use SoftDeletes;

    protected $table = 'otro_gasto_tipos';

    protected $fillable = [
        'nombre',
        'tipo',
        'grupo',
        'nomenclatura',
        'tipo_linea',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    public function movimientos()
    {
        return $this->hasMany(OtroGastoMovimiento::class);
    }

    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopeIngresos($query)
    {
        return $query->where('tipo', 'ingreso');
    }

    public function scopeEgresos($query)
    {
        return $query->where('tipo', 'egreso');
    }
}
