<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Denominacion extends Model
{
    use HasFactory;

    protected $table = 'denominaciones';

    protected $fillable = [
        'moneda_id',
        'valor',
        'tipo',
        'descripcion',
        'orden',
        'activa',
    ];

    protected $casts = [
        'valor' => 'decimal:2',
        'orden' => 'integer',
        'activa' => 'boolean',
    ];

    /**
     * Relación con moneda
     */
    public function moneda()
    {
        return $this->belongsTo(Moneda::class);
    }

    /**
     * Scope para denominaciones activas
     */
    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }

    /**
     * Scope para billetes
     */
    public function scopeBilletes($query)
    {
        return $query->where('tipo', 'billete');
    }

    /**
     * Scope para monedas
     */
    public function scopeMonedas($query)
    {
        return $query->where('tipo', 'moneda');
    }

    /**
     * Obtener el valor formateado
     */
    public function getValorFormateadoAttribute()
    {
        return $this->moneda ? $this->moneda->simbolo . number_format($this->valor, 2) : number_format($this->valor, 2);
    }
}
