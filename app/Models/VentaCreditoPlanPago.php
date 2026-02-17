<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Modelo para plan de pagos de créditos de ventas
 * Define las cuotas del crédito de venta
 */
class VentaCreditoPlanPago extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected string $auditoriaModulo = 'ventas_credito';
    public static bool $auditarDeshabilitado = false;

    protected $table = 'venta_credito_plan_pagos';

    protected $fillable = [
        'venta_credito_id',
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
        'tipo_modificacion',
        'modificado_por_id',
        'fecha_modificacion',
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
        'permite_pago_parcial' => 'boolean',
    ];

    // ==================== RELACIONES ====================

    /**
     * Crédito de venta al que pertenece
     */
    public function ventaCredito(): BelongsTo
    {
        return $this->belongsTo(VentaCredito::class);
    }

    /**
     * Usuario que registró el pago
     */
    public function usuarioPago(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_pago_id');
    }

    /**
     * Usuario que modificó la cuota
     */
    public function modificadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'modificado_por_id');
    }

    /**
     * Último movimiento aplicado
     */
    public function ultimoMovimiento(): BelongsTo
    {
        return $this->belongsTo(VentaCreditoMovimiento::class, 'ultimo_movimiento_id');
    }

    /**
     * Movimientos que afectan esta cuota
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(VentaCreditoMovimiento::class, 'cuota_id');
    }

    // ==================== SCOPES ====================

    public function scopePendientes($query)
    {
        return $query->whereIn('estado', ['pendiente', 'pagada_parcial']);
    }

    public function scopeVencidas($query)
    {
        return $query->where('estado', 'vencida')
            ->orWhere(function($q) {
                $q->where('estado', 'pendiente')
                  ->where('fecha_vencimiento', '<', now());
            });
    }

    public function scopePagadas($query)
    {
        return $query->where('estado', 'pagada');
    }

    // ==================== MÉTODOS ====================

    /**
     * Verificar si la cuota está vencida
     */
    public function estaVencida(): bool
    {
        return $this->fecha_vencimiento < now() && !in_array($this->estado, ['pagada', 'cancelada', 'condonada']);
    }

    /**
     * Calcular días de mora actuales
     */
    public function calcularDiasMora(): int
    {
        if (!$this->estaVencida()) {
            return 0;
        }
        return now()->diffInDays($this->fecha_vencimiento);
    }

    /**
     * Obtener monto total a pagar (incluyendo mora si aplica)
     */
    public function montoTotalAPagar(): float
    {
        return $this->monto_pendiente + $this->mora_pendiente;
    }

    /**
     * Verificar si tiene saldo pendiente
     */
    public function tieneSaldoPendiente(): bool
    {
        return $this->monto_pendiente > 0;
    }

    /**
     * Obtener porcentaje pagado de la cuota
     */
    public function porcentajePagado(): float
    {
        if ($this->monto_cuota_proyectado == 0) return 100;
        return round(($this->monto_total_pagado / $this->monto_cuota_proyectado) * 100, 2);
    }
}
