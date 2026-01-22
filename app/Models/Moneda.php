<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Moneda extends Model
{
    use HasFactory;

    protected $table = 'monedas';

    protected $fillable = [
        'codigo',
        'nombre',
        'simbolo',
        'tipo_cambio',
        'es_moneda_base',
        'activa',
    ];

    protected $casts = [
        'tipo_cambio' => 'decimal:4',
        'es_moneda_base' => 'boolean',
        'activa' => 'boolean',
    ];

    /**
     * Relación con denominaciones
     */
    public function denominaciones()
    {
        return $this->hasMany(Denominacion::class)->orderBy('orden');
    }

    /**
     * Obtener solo los billetes
     */
    public function billetes()
    {
        return $this->hasMany(Denominacion::class)
            ->where('tipo', 'billete')
            ->where('activa', true)
            ->orderBy('orden');
    }

    /**
     * Obtener solo las monedas
     */
    public function monedas()
    {
        return $this->hasMany(Denominacion::class)
            ->where('tipo', 'moneda')
            ->where('activa', true)
            ->orderBy('orden');
    }

    /**
     * Obtener la moneda base del sistema
     */
    public static function monedaBase()
    {
        return static::where('es_moneda_base', true)->first();
    }

    /**
     * Scope para monedas activas
     */
    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }
}
