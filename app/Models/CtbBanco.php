<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CtbBanco extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ctb_bancos';

    protected $fillable = [
        'codigo_banco',
        'nombre_banco',
        'cuenta_contable_id',
        'numero_cuenta',
        'tipo_cuenta',
        'moneda_id',
        'saldo_inicial',
        'saldo_actual',
        'fecha_apertura',
        'estado',
        'sucursal_id',
    ];

    protected $casts = [
        'saldo_inicial' => 'decimal:2',
        'saldo_actual' => 'decimal:2',
        'fecha_apertura' => 'date',
        'estado' => 'boolean',
    ];

    /**
     * Cuenta contable asociada
     */
    public function cuentaContable(): BelongsTo
    {
        return $this->belongsTo(CtbNomenclatura::class, 'cuenta_contable_id');
    }

    /**
     * Moneda
     */
    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class);
    }

    /**
     * Sucursal
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }
}
