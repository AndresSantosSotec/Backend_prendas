<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CtbMovimiento extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ctb_movimientos';

    protected $fillable = [
        'diario_id',
        'cuenta_contable_id',
        'monto_debe',
        'monto_haber',
        'concepto',
        'referencia',
        'auxiliar_tipo',
        'auxiliar_id',
        'banco_ctb_id',
    ];

    protected $casts = [
        'monto_debe' => 'decimal:2',
        'monto_haber' => 'decimal:2',
    ];

    /**
     * Asiento al que pertenece
     */
    public function diario(): BelongsTo
    {
        return $this->belongsTo(CtbDiario::class, 'diario_id');
    }

    /**
     * Cuenta contable
     */
    public function cuentaContable(): BelongsTo
    {
        return $this->belongsTo(CtbNomenclatura::class, 'cuenta_contable_id');
    }

    /**
     * Banco si es movimiento bancario
     */
    public function banco(): BelongsTo
    {
        return $this->belongsTo(CtbBanco::class, 'banco_ctb_id');
    }

    /**
     * Obtener el monto del movimiento
     */
    public function getMontoAttribute(): float
    {
        return $this->monto_debe > 0 ? $this->monto_debe : $this->monto_haber;
    }

    /**
     * Obtener el tipo de movimiento
     */
    public function getTipoMovimientoAttribute(): string
    {
        return $this->monto_debe > 0 ? 'debe' : 'haber';
    }
}
