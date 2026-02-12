<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Auditable;

class Cotizacion extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected string $auditoriaModulo = 'cotizaciones';
    public static bool $auditarDeshabilitado = false;

    protected $table = 'cotizaciones';

    protected $fillable = [
        'numero_cotizacion',
        'fecha',
        'cliente_id',
        'cliente_nombre',
        'sucursal_id',
        'user_id',
        'productos',
        'subtotal',
        'descuento',
        'total',
        'tipo_venta',
        'plan_pagos',
        'observaciones',
        'estado',
        'venta_id',
        'fecha_conversion',
        'fecha_vencimiento',
    ];

    protected $casts = [
        'fecha' => 'date',
        'productos' => 'array',
        'plan_pagos' => 'array',
        'subtotal' => 'decimal:2',
        'descuento' => 'decimal:2',
        'total' => 'decimal:2',
        'fecha_conversion' => 'datetime',
        'fecha_vencimiento' => 'date',
    ];

    // Relaciones
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    // Scopes
    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeConvertidas($query)
    {
        return $query->where('estado', 'convertida');
    }

    public function scopeVigentes($query)
    {
        return $query->where('estado', 'pendiente')
                     ->where(function($q) {
                         $q->whereNull('fecha_vencimiento')
                           ->orWhere('fecha_vencimiento', '>=', now());
                     });
    }

    // Métodos auxiliares
    public function estaVigente(): bool
    {
        if ($this->estado !== 'pendiente') {
            return false;
        }

        if ($this->fecha_vencimiento && $this->fecha_vencimiento->isPast()) {
            return false;
        }

        return true;
    }

    public function puedeConvertirse(): bool
    {
        return $this->estado === 'pendiente' && $this->estaVigente();
    }

    public function marcarComoConvertida($ventaId)
    {
        $this->update([
            'estado' => 'convertida',
            'venta_id' => $ventaId,
            'fecha_conversion' => now(),
        ]);
    }

    public function cancelar()
    {
        $this->update(['estado' => 'cancelada']);
    }
}
