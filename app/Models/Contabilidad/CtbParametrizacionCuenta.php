<?php

namespace App\Models\Contabilidad;

use App\Models\Sucursal;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CtbParametrizacionCuenta extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ctb_parametrizacion_cuentas';

    protected $fillable = [
        'tipo_operacion',
        'tipo_movimiento',
        'cuenta_contable_id',
        'tipo_poliza_id',
        'descripcion',
        'activo',
        'orden',
        'sucursal_id',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'orden' => 'integer',
    ];

    /**
     * Cuenta contable asociada
     */
    public function cuentaContable(): BelongsTo
    {
        return $this->belongsTo(CtbNomenclatura::class, 'cuenta_contable_id');
    }

    /**
     * Tipo de póliza
     */
    public function tipoPoliza(): BelongsTo
    {
        return $this->belongsTo(CtbTipoPoliza::class, 'tipo_poliza_id');
    }

    /**
     * Sucursal (si aplica a una específica)
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * Scope para filtrar por tipo de operación
     */
    public function scopeTipoOperacion($query, $tipo)
    {
        return $query->where('tipo_operacion', $tipo);
    }

    /**
     * Scope para solo activos
     */
    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para sucursal específica o global
     */
    public function scopeParaSucursal($query, $sucursalId)
    {
        return $query->where(function ($q) use ($sucursalId) {
            $q->whereNull('sucursal_id')
              ->orWhere('sucursal_id', $sucursalId);
        });
    }
}
