<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CtbNomenclatura extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ctb_nomenclatura';

    protected $fillable = [
        'codigo_cuenta',
        'nombre_cuenta',
        'tipo',
        'naturaleza',
        'nivel',
        'cuenta_padre_id',
        'acepta_movimientos',
        'requiere_auxiliar',
        'categoria_flujo',
        'estado',
    ];

    protected $casts = [
        'acepta_movimientos' => 'boolean',
        'requiere_auxiliar' => 'boolean',
        'estado' => 'boolean',
        'nivel' => 'integer',
    ];

    /**
     * Cuenta padre en la jerarquía
     */
    public function cuentaPadre(): BelongsTo
    {
        return $this->belongsTo(CtbNomenclatura::class, 'cuenta_padre_id');
    }

    /**
     * Cuentas hijas
     */
    public function cuentasHijas(): HasMany
    {
        return $this->hasMany(CtbNomenclatura::class, 'cuenta_padre_id');
    }

    /**
     * Movimientos contables
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(CtbMovimiento::class, 'cuenta_contable_id');
    }

    /**
     * Parametrizaciones
     */
    public function parametrizaciones(): HasMany
    {
        return $this->hasMany(CtbParametrizacionCuenta::class, 'cuenta_contable_id');
    }

    /**
     * Obtener código completo de la cuenta
     */
    public function getCodigoCompletoAttribute(): string
    {
        return $this->codigo_cuenta . ' - ' . $this->nombre_cuenta;
    }

    /**
     * Verificar si puede tener movimientos
     */
    public function puedeMoverse(): bool
    {
        return $this->acepta_movimientos && $this->estado;
    }

    /**
     * Calcular saldo de la cuenta
     */
    public function calcularSaldo($fechaDesde = null, $fechaHasta = null): float
    {
        $query = $this->movimientos()
            ->whereHas('diario', function ($q) {
                $q->where('estado', 'registrado');
            });

        if ($fechaDesde) {
            $query->whereHas('diario', function ($q) use ($fechaDesde) {
                $q->where('fecha_contabilizacion', '>=', $fechaDesde);
            });
        }

        if ($fechaHasta) {
            $query->whereHas('diario', function ($q) use ($fechaHasta) {
                $q->where('fecha_contabilizacion', '<=', $fechaHasta);
            });
        }

        $debe = $query->sum('monto_debe');
        $haber = $query->sum('monto_haber');

        // Calcular según naturaleza
        if ($this->naturaleza === 'deudora') {
            return $debe - $haber;
        } else {
            return $haber - $debe;
        }
    }
}
