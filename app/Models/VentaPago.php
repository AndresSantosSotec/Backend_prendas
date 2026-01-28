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
        'metodo',
        'monto',
        'cambio',
        'referencia',
        'banco',
        'autorizacion',
        'fecha_pago',
        'observaciones',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'cambio' => 'decimal:2',
        'fecha_pago' => 'datetime',
    ];

    // Relaciones
    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    // Accessors
    public function getMetodoNombreAttribute()
    {
        $metodos = [
            'efectivo' => 'Efectivo',
            'tarjeta_debito' => 'Tarjeta de Débito',
            'tarjeta_credito' => 'Tarjeta de Crédito',
            'transferencia' => 'Transferencia',
            'cheque' => 'Cheque',
            'deposito' => 'Depósito',
            'qr' => 'Código QR',
            'otro' => 'Otro',
        ];

        return $metodos[$this->metodo] ?? $this->metodo;
    }

    // Scopes
    public function scopePorMetodo($query, $metodo)
    {
        return $query->where('metodo', $metodo);
    }

    public function scopeEntreFechas($query, $desde, $hasta)
    {
        return $query->whereBetween('fecha_pago', [$desde, $hasta]);
    }
}
