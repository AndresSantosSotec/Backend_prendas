<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditoPlanPago extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'credito_prendario_id',
        'numero_cuota',
        'fecha_vencimiento',
        'fecha_pago',
        'estado',
        'capital_proyectado',
        'interes_proyectado',
        'mora_proyectada',
        'otros_cargos_proyectados',
        'monto_cuota_proyectado',
        'capital_pagado',
        'interes_pagado',
        'mora_pagada',
        'otros_cargos_pagados',
        'monto_total_pagado',
        'capital_pendiente',
        'interes_pendiente',
        'mora_pendiente',
        'otros_cargos_pendientes',
        'monto_pendiente',
        'saldo_capital_credito',
        'dias_mora',
        'fecha_inicio_mora',
        'tasa_mora_aplicada',
        'ultimo_movimiento_id',
        'usuario_pago_id',
        'observaciones',
        'permite_pago_parcial',
        'es_cuota_gracia',
        'tipo_modificacion',
        'motivo_modificacion',
        'modificado_por',
        'fecha_modificacion',
        'tasa_interes_aplicada',
        'dias_cuota',
    ];

    protected $casts = [
        'fecha_vencimiento' => 'date',
        'fecha_pago' => 'date',
        'fecha_inicio_mora' => 'date',
        'fecha_modificacion' => 'datetime',
        'capital_proyectado' => 'decimal:2',
        'interes_proyectado' => 'decimal:2',
        'mora_proyectada' => 'decimal:2',
        'otros_cargos_proyectados' => 'decimal:2',
        'monto_cuota_proyectado' => 'decimal:2',
        'capital_pagado' => 'decimal:2',
        'interes_pagado' => 'decimal:2',
        'mora_pagada' => 'decimal:2',
        'otros_cargos_pagados' => 'decimal:2',
        'monto_total_pagado' => 'decimal:2',
        'capital_pendiente' => 'decimal:2',
        'interes_pendiente' => 'decimal:2',
        'mora_pendiente' => 'decimal:2',
        'otros_cargos_pendientes' => 'decimal:2',
        'monto_pendiente' => 'decimal:2',
        'saldo_capital_credito' => 'decimal:2',
        'tasa_mora_aplicada' => 'decimal:2',
        'tasa_interes_aplicada' => 'decimal:2',
        'permite_pago_parcial' => 'boolean',
        'es_cuota_gracia' => 'boolean',
    ];

    // Relaciones
    public function creditoPrendario()
    {
        return $this->belongsTo(CreditoPrendario::class, 'credito_prendario_id');
    }

    public function ultimoMovimiento()
    {
        return $this->belongsTo(CreditoMovimiento::class, 'ultimo_movimiento_id');
    }

    public function usuarioPago()
    {
        return $this->belongsTo(User::class, 'usuario_pago_id');
    }

    public function modificadoPor()
    {
        return $this->belongsTo(User::class, 'modificado_por');
    }

    public function movimientos()
    {
        return $this->hasMany(CreditoMovimiento::class, 'cuota_id');
    }

    // Scopes
    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopePagadas($query)
    {
        return $query->where('estado', 'pagada');
    }

    public function scopeVencidas($query)
    {
        return $query->where('estado', 'vencida');
    }

    public function scopeEnMora($query)
    {
        return $query->where('estado', 'en_mora');
    }

    public function scopePorCredito($query, $creditoId)
    {
        return $query->where('credito_prendario_id', $creditoId);
    }

    public function scopeVencidasEnFecha($query, $fecha)
    {
        return $query->where('fecha_vencimiento', '<=', $fecha)
                     ->whereIn('estado', ['pendiente', 'pagada_parcial']);
    }

    // Métodos auxiliares
    public function estaVencida()
    {
        return $this->fecha_vencimiento->isPast() &&
               in_array($this->estado, ['pendiente', 'pagada_parcial']);
    }

    public function estaPagadaTotalmente()
    {
        return $this->estado === 'pagada' ||
               $this->monto_pendiente <= 0;
    }

    public function tieneMora()
    {
        return $this->dias_mora > 0;
    }

    public function calcularMoraActual()
    {
        if (!$this->estaVencida()) {
            return 0;
        }

        $diasMora = now()->diffInDays($this->fecha_vencimiento);
        $tasaDiaria = $this->tasa_mora_aplicada / 30; // Convertir a diaria
        $mora = $this->monto_pendiente * ($tasaDiaria / 100) * $diasMora;

        return round($mora, 2);
    }

    public function puedeAplicarsePago()
    {
        return in_array($this->estado, ['pendiente', 'pagada_parcial', 'vencida', 'en_mora']);
    }
}
