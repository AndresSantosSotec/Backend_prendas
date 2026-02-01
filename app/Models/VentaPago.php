<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VentaPago extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'venta_id',
        'metodo_pago_id',
        'monto',
        'cambio',
        'referencia',
        'banco',
        'numero_autorizacion',
        'observaciones',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'cambio' => 'decimal:2',
    ];

    // Relaciones
    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function metodoPago()
    {
        return $this->belongsTo(MetodoPago::class);
    }

    // Accessors
    public function getMetodoNombreAttribute()
    {
        return $this->metodoPago?->nombre ?? 'Desconocido';
    }

    // Scopes
    public function scopePorMetodo($query, $metodoPagoId)
    {
        return $query->where('metodo_pago_id', $metodoPagoId);
    }

    public function scopeEntreFechas($query, $desde, $hasta)
    {
        return $query->whereBetween('fecha_pago', [$desde, $hasta]);
    }
}
