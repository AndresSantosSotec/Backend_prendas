<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MetodoPago extends Model
{
    use HasFactory;

    protected $table = 'metodos_pago';

    protected $fillable = [
        'nombre',
        'codigo',
        'descripcion',
        'tipo',
        'requiere_referencia',
        'requiere_autorizacion',
        'activo',
        'comision_porcentaje',
        'comision_fija',
    ];

    protected $casts = [
        'requiere_referencia' => 'boolean',
        'requiere_autorizacion' => 'boolean',
        'activo' => 'boolean',
        'comision_porcentaje' => 'decimal:2',
        'comision_fija' => 'decimal:2',
    ];

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    // Métodos
    public function calcularComision($monto)
    {
        $comisionFija = $this->comision_fija ?? 0;
        $comisionPorcentaje = ($monto * ($this->comision_porcentaje ?? 0)) / 100;

        return $comisionFija + $comisionPorcentaje;
    }
}
