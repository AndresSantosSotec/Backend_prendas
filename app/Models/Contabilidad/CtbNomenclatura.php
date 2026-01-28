<?php

namespace App\Models\Contabilidad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CtbNomenclatura extends Model
{
    use SoftDeletes;

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
        'nivel' => 'integer',
        'acepta_movimientos' => 'boolean',
        'requiere_auxiliar' => 'boolean',
        'estado' => 'boolean',
    ];

    /**
     * Cuenta padre en la jerarquía
     */
    public function padre(): BelongsTo
    {
        return $this->belongsTo(CtbNomenclatura::class, 'cuenta_padre_id');
    }

    /**
     * Cuentas hijas
     */
    public function hijos(): HasMany
    {
        return $this->hasMany(CtbNomenclatura::class, 'cuenta_padre_id');
    }

    /**
     * Movimientos contables en esta cuenta
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(CtbMovimiento::class, 'cuenta_contable_id');
    }

    /**
     * Cuentas bancarias que usan esta cuenta contable
     */
    public function cuentasBancarias(): HasMany
    {
        return $this->hasMany(CtbBanco::class, 'cuenta_contable_id');
    }

    /**
     * Scope para cuentas activas
     */
    public function scopeActivas($query)
    {
        return $query->where('estado', true);
    }

    /**
     * Scope para cuentas que aceptan movimientos
     */
    public function scopeConMovimientos($query)
    {
        return $query->where('acepta_movimientos', true);
    }

    /**
     * Scope por tipo de cuenta
     */
    public function scopePorTipo($query, $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    /**
     * Scope por nivel
     */
    public function scopePorNivel($query, $nivel)
    {
        return $query->where('nivel', $nivel);
    }

    /**
     * Scope para buscar por código
     */
    public function scopePorCodigo($query, $codigo)
    {
        return $query->where('codigo_cuenta', $codigo);
    }

    /**
     * Calcular saldo de la cuenta
     */
    public function calcularSaldo($fechaInicio = null, $fechaFin = null)
    {
        $query = $this->movimientos()
            ->join('ctb_diario', 'ctb_movimientos.diario_id', '=', 'ctb_diario.id')
            ->where('ctb_diario.estado', 'registrado');

        if ($fechaInicio) {
            $query->where('ctb_diario.fecha_contabilizacion', '>=', $fechaInicio);
        }

        if ($fechaFin) {
            $query->where('ctb_diario.fecha_contabilizacion', '<=', $fechaFin);
        }

        $debe = $query->sum('ctb_movimientos.debe');
        $haber = $query->sum('ctb_movimientos.haber');

        // Aplicar naturaleza de la cuenta
        if ($this->naturaleza === 'deudora') {
            return $debe - $haber;
        } else {
            return $haber - $debe;
        }
    }

    /**
     * Obtener el path completo de la cuenta (jerarquía)
     */
    public function getPathAttribute()
    {
        $path = [$this->nombre_cuenta];
        $padre = $this->padre;

        while ($padre) {
            array_unshift($path, $padre->nombre_cuenta);
            $padre = $padre->padre;
        }

        return implode(' > ', $path);
    }
}
