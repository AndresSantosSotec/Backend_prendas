<?php

namespace App\Models\Contabilidad;

use App\Models\Cliente;
use App\Models\Proveedor;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CtbMovimiento extends Model
{
    protected $table = 'ctb_movimientos';

    public $timestamps = false; // Solo created_at

    protected $fillable = [
        'diario_id',
        'cuenta_contable_id',
        'debe',
        'haber',
        'numero_comprobante',
        'detalle',
        'cliente_id',
        'proveedor_id',
        'banco_ctb_id',
        'centro_costo_id',
    ];

    protected $casts = [
        'debe' => 'decimal:2',
        'haber' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    /**
     * Boot del modelo para agregar created_at automáticamente
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (!$model->created_at) {
                $model->created_at = now();
            }
        });
    }

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
     * Cliente auxiliar
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class);
    }

    /**
     * Proveedor auxiliar
     * NOTA: Comentado porque la tabla proveedores no existe en el sistema
     */
    // public function proveedor(): BelongsTo
    // {
    //     return $this->belongsTo(Proveedor::class);
    // }

    /**
     * Banco si es movimiento bancario
     */
    public function bancoCTB(): BelongsTo
    {
        return $this->belongsTo(CtbBanco::class, 'banco_ctb_id');
    }

    /**
     * Scope para movimientos en el debe
     */
    public function scopeDebe($query)
    {
        return $query->where('debe', '>', 0);
    }

    /**
     * Scope para movimientos en el haber
     */
    public function scopeHaber($query)
    {
        return $query->where('haber', '>', 0);
    }

    /**
     * Scope por cuenta contable
     */
    public function scopePorCuenta($query, $cuentaId)
    {
        return $query->where('cuenta_contable_id', $cuentaId);
    }

    /**
     * Obtener el monto (sea debe o haber)
     */
    public function getMontoAttribute()
    {
        return $this->debe > 0 ? $this->debe : $this->haber;
    }

    /**
     * Obtener el tipo de movimiento (debe o haber)
     */
    public function getTipoMovimientoAttribute()
    {
        return $this->debe > 0 ? 'debe' : 'haber';
    }

    /**
     * Validar que solo tenga debe O haber, no ambos
     */
    public function validarDebeHaber()
    {
        if ($this->debe > 0 && $this->haber > 0) {
            throw new \Exception('Un movimiento no puede tener valores en debe Y haber simultáneamente');
        }

        if ($this->debe == 0 && $this->haber == 0) {
            throw new \Exception('Un movimiento debe tener valor en debe O haber');
        }

        return true;
    }

    /**
     * Antes de guardar, validar
     */
    protected static function booted()
    {
        static::creating(function ($movimiento) {
            $movimiento->validarDebeHaber();
        });

        static::updating(function ($movimiento) {
            $movimiento->validarDebeHaber();
        });
    }
}
