<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Modelo para créditos de ventas
 * Gestiona los créditos otorgados a clientes por ventas a crédito
 * Esta tabla es INDEPENDIENTE de créditos prendarios
 */
class VentaCredito extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected string $auditoriaModulo = 'ventas_credito';
    public static bool $auditarDeshabilitado = false;

    protected $table = 'venta_creditos';

    protected $fillable = [
        'numero_credito',
        'venta_id',
        'cliente_id',
        'sucursal_id',
        'vendedor_id',
        'aprobado_por_id',
        'estado',
        'fecha_credito',
        'fecha_aprobacion',
        'fecha_primer_pago',
        'fecha_vencimiento',
        'fecha_liquidacion',
        'fecha_ultimo_pago',
        'monto_venta',
        'enganche',
        'saldo_financiar',
        'interes_total',
        'total_credito',
        'capital_pendiente',
        'capital_pagado',
        'interes_pendiente',
        'interes_pagado',
        'mora_generada',
        'mora_pagada',
        'saldo_actual',
        'tasa_interes',
        'tasa_mora',
        'tipo_interes',
        'frecuencia_pago',
        'numero_cuotas',
        'monto_cuota',
        'dias_gracia',
        'dias_mora',
        'cuotas_vencidas',
        'cuotas_pagadas',
        'observaciones',
        'numero_contrato',
    ];

    protected $casts = [
        'fecha_credito' => 'date',
        'fecha_aprobacion' => 'date',
        'fecha_primer_pago' => 'date',
        'fecha_vencimiento' => 'date',
        'fecha_liquidacion' => 'date',
        'fecha_ultimo_pago' => 'date',
        'monto_venta' => 'decimal:2',
        'enganche' => 'decimal:2',
        'saldo_financiar' => 'decimal:2',
        'interes_total' => 'decimal:2',
        'total_credito' => 'decimal:2',
        'capital_pendiente' => 'decimal:2',
        'capital_pagado' => 'decimal:2',
        'interes_pendiente' => 'decimal:2',
        'interes_pagado' => 'decimal:2',
        'mora_generada' => 'decimal:2',
        'mora_pagada' => 'decimal:2',
        'saldo_actual' => 'decimal:2',
        'tasa_interes' => 'decimal:2',
        'tasa_mora' => 'decimal:2',
        'monto_cuota' => 'decimal:2',
    ];

    // ==================== RELACIONES ====================

    /**
     * Venta que origina este crédito
     */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    /**
     * Cliente del crédito
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Sucursal donde se originó
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * Vendedor que realizó la venta
     */
    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    /**
     * Usuario que aprobó el crédito
     */
    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por_id');
    }

    /**
     * Plan de pagos del crédito
     */
    public function planPagos(): HasMany
    {
        return $this->hasMany(VentaCreditoPlanPago::class)->orderBy('numero_cuota');
    }

    /**
     * Movimientos/pagos del crédito
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(VentaCreditoMovimiento::class)->orderBy('fecha_movimiento', 'desc');
    }

    /**
     * Gastos asociados al crédito
     */
    public function gastos(): BelongsToMany
    {
        return $this->belongsToMany(Gasto::class, 'venta_credito_gastos', 'venta_credito_id', 'gasto_id')
            ->withPivot(['valor_calculado', 'incluido_en_cuotas', 'estado'])
            ->withTimestamps();
    }

    // ==================== SCOPES ====================

    public function scopeVigentes($query)
    {
        return $query->where('estado', 'vigente');
    }

    public function scopeEnMora($query)
    {
        return $query->whereIn('estado', ['vencido', 'en_mora']);
    }

    public function scopeDelCliente($query, int $clienteId)
    {
        return $query->where('cliente_id', $clienteId);
    }

    public function scopeDeSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    // ==================== MÉTODOS ====================

    /**
     * Generar número de crédito único
     */
    public static function generarNumeroCredito(): string
    {
        $prefix = 'VC-' . date('Ym') . '-';
        $ultimo = self::whereYear('created_at', date('Y'))
            ->whereMonth('created_at', date('m'))
            ->count() + 1;
        return $prefix . str_pad($ultimo, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Obtener siguiente cuota pendiente
     */
    public function siguienteCuotaPendiente()
    {
        return $this->planPagos()
            ->whereIn('estado', ['pendiente', 'pagada_parcial', 'vencida', 'en_mora'])
            ->orderBy('numero_cuota')
            ->first();
    }

    /**
     * Verificar si está al día
     */
    public function estaAlDia(): bool
    {
        return $this->cuotas_vencidas == 0 && $this->dias_mora == 0;
    }

    /**
     * Calcular porcentaje de avance
     */
    public function porcentajeAvance(): float
    {
        if ($this->numero_cuotas == 0) return 100;
        return round(($this->cuotas_pagadas / $this->numero_cuotas) * 100, 2);
    }
}
