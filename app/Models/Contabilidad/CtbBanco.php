<?php

namespace App\Models\Contabilidad;

use App\Models\Sucursal;
use App\Models\Moneda;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CtbBanco extends Model
{
    use SoftDeletes;

    protected $table = 'ctb_bancos';

    protected $fillable = [
        'banco_id',
        'cuenta_contable_id',
        'sucursal_id',
        'moneda_id',
        'numero_cuenta',
        'tipo_cuenta',
        'saldo_inicial',
        'saldo_actual',
        'fecha_apertura',
        'estado',
        'permite_sobregiros',
        'limite_sobregiro',
        'observaciones',
    ];

    protected $casts = [
        'saldo_inicial' => 'decimal:2',
        'saldo_actual' => 'decimal:2',
        'limite_sobregiro' => 'decimal:2',
        'permite_sobregiros' => 'boolean',
        'fecha_apertura' => 'date',
    ];

    /**
     * Banco al que pertenece
     */
    public function banco(): BelongsTo
    {
        return $this->belongsTo(TbBanco::class, 'banco_id');
    }

    /**
     * Cuenta contable asociada
     */
    public function cuentaContable(): BelongsTo
    {
        return $this->belongsTo(CtbNomenclatura::class, 'cuenta_contable_id');
    }

    /**
     * Sucursal
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * Moneda
     */
    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class);
    }

    /**
     * Movimientos contables de este banco
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(CtbMovimiento::class, 'banco_ctb_id');
    }

    /**
     * Scope para cuentas activas
     */
    public function scopeActivas($query)
    {
        return $query->where('estado', 'activa');
    }

    /**
     * Scope por sucursal
     */
    public function scopePorSucursal($query, $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    /**
     * Scope por banco
     */
    public function scopePorBanco($query, $bancoId)
    {
        return $query->where('banco_id', $bancoId);
    }

    /**
     * Actualizar saldo
     */
    public function actualizarSaldo($monto)
    {
        $this->saldo_actual += $monto;
        $this->save();
    }

    /**
     * Verificar si tiene fondos suficientes
     */
    public function tieneFondos($monto)
    {
        $disponible = $this->saldo_actual;

        if ($this->permite_sobregiros) {
            $disponible += $this->limite_sobregiro;
        }

        return $disponible >= $monto;
    }

    /**
     * Obtener saldo disponible
     */
    public function getSaldoDisponibleAttribute()
    {
        $disponible = $this->saldo_actual;

        if ($this->permite_sobregiros) {
            $disponible += $this->limite_sobregiro;
        }

        return $disponible;
    }
}
