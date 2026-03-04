<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Refrendo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'refrendos';

    /**
     * Tipos de refrendo disponibles
     */
    public const TIPO_PARCIAL = 'parcial';        // Solo intereses + mora
    public const TIPO_TOTAL = 'total';            // Intereses + mora + capital
    public const TIPO_CON_CAPITAL = 'con_capital'; // Con % mínimo obligatorio

    protected $fillable = [
        'credito_id',
        'numero_refrendo',
        'tipo_refrendo',
        'monto_interes_adeudado',
        'monto_mora_adeudado',
        'monto_capital_pagado',
        'monto_total_pagado',
        'fecha_refrendo',
        'fecha_vencimiento_anterior',
        'fecha_vencimiento_nueva',
        'dias_extendidos',
        'tasa_interes_aplicada',
        'plazo_dias_nuevo',
        'promocion_aplicada',
        'descuento_aplicado',
        'usuario_id',
        'sucursal_id',
        'caja_movimiento_id',
        'recibo_pdf_url',
        'observaciones',
    ];

    protected $casts = [
        'monto_interes_adeudado' => 'decimal:2',
        'monto_mora_adeudado' => 'decimal:2',
        'monto_capital_pagado' => 'decimal:2',
        'monto_total_pagado' => 'decimal:2',
        'tasa_interes_aplicada' => 'decimal:2',
        'descuento_aplicado' => 'decimal:2',
        'fecha_refrendo' => 'datetime',
        'fecha_vencimiento_anterior' => 'date',
        'fecha_vencimiento_nueva' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relación: Refrendo pertenece a un crédito prendario
     */
    public function credito(): BelongsTo
    {
        return $this->belongsTo(CreditoPrendario::class, 'credito_id');
    }

    /**
     * Relación: Refrendo procesado por un usuario
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Relación: Refrendo realizado en una sucursal
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    /**
     * Relación: Movimiento de caja asociado (opcional)
     */
    public function movimientoCaja(): BelongsTo
    {
        return $this->belongsTo(MovimientoCaja::class, 'caja_movimiento_id');
    }

    /**
     * Scope: Refrendos de un crédito específico
     */
    public function scopeDelCredito($query, int $creditoId)
    {
        return $query->where('credito_id', $creditoId);
    }

    /**
     * Scope: Refrendos por tipo
     */
    public function scopePorTipo($query, string $tipo)
    {
        return $query->where('tipo_refrendo', $tipo);
    }

    /**
     * Scope: Refrendos en un rango de fechas
     */
    public function scopeEntreFechas($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha_refrendo', [$fechaInicio, $fechaFin]);
    }

    /**
     * Scope: Refrendos de una sucursal
     */
    public function scopeDeSucursal($query, int $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    /**
     * Accessor: Obtener el monto neto pagado (sin descuentos)
     */
    public function getMontoNetoPagadoAttribute(): float
    {
        return (float) ($this->monto_total_pagado - $this->descuento_aplicado);
    }

    /**
     * Accessor: Verificar si tiene promoción aplicada
     */
    public function getTienePromocionAttribute(): bool
    {
        return !is_null($this->promocion_aplicada) && $this->descuento_aplicado > 0;
    }

    /**
     * Accessor: Obtener descripción del tipo de refrendo
     */
    public function getTipoRefrendoDescripcionAttribute(): string
    {
        return match($this->tipo_refrendo) {
            self::TIPO_PARCIAL => 'Refrendo Parcial (Solo Intereses)',
            self::TIPO_TOTAL => 'Refrendo Total (Con Capital)',
            self::TIPO_CON_CAPITAL => 'Refrendo con Capital Obligatorio',
            default => 'Desconocido'
        };
    }

    /**
     * Boot del modelo - eventos
     */
    protected static function boot()
    {
        parent::boot();

        // Antes de crear, auto-calcular el número de refrendo
        static::creating(function ($refrendo) {
            if (!$refrendo->numero_refrendo) {
                $ultimoRefrendo = self::where('credito_id', $refrendo->credito_id)
                    ->max('numero_refrendo');
                $refrendo->numero_refrendo = $ultimoRefrendo ? $ultimoRefrendo + 1 : 1;
            }

            // Auto-calcular días extendidos
            if (!$refrendo->dias_extendidos && $refrendo->fecha_vencimiento_anterior && $refrendo->fecha_vencimiento_nueva) {
                $fechaAnterior = \Carbon\Carbon::parse($refrendo->fecha_vencimiento_anterior);
                $fechaNueva = \Carbon\Carbon::parse($refrendo->fecha_vencimiento_nueva);
                $refrendo->dias_extendidos = $fechaAnterior->diffInDays($fechaNueva);
            }
        });
    }

    /**
     * Método helper: Crear refrendo parcial
     */
    public static function crearParcial(array $datos): self
    {
        $datos['tipo_refrendo'] = self::TIPO_PARCIAL;
        $datos['monto_capital_pagado'] = 0;
        return self::create($datos);
    }

    /**
     * Método helper: Crear refrendo total
     */
    public static function crearTotal(array $datos): self
    {
        $datos['tipo_refrendo'] = self::TIPO_TOTAL;
        return self::create($datos);
    }

    /**
     * Método helper: Crear refrendo con capital obligatorio
     */
    public static function crearConCapital(array $datos): self
    {
        $datos['tipo_refrendo'] = self::TIPO_CON_CAPITAL;
        return self::create($datos);
    }
}
