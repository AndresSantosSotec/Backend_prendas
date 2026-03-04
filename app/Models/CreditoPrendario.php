<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Auditable;

class CreditoPrendario extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected string $auditoriaModulo = 'creditos';
    public static bool $auditarDeshabilitado = false;

    protected $table = 'creditos_prendarios';

    /**
     * Estados finales donde el crédito no puede cambiar de estado
     */
    public const ESTADOS_FINALES = ['pagado', 'cancelado', 'incobrable', 'liquidado', 'rematado'];

    /**
     * Boot del modelo - eventos y validaciones
     */
    protected static function boot()
    {
        parent::boot();

        // Validar que no se pueda cambiar el estado de un crédito completamente pagado
        static::updating(function ($credito) {
            // Si el estado original es un estado final, no permitir cambios de estado
            $estadoOriginal = $credito->getOriginal('estado');
            $estadoNuevo = $credito->estado;

            if ($estadoOriginal !== $estadoNuevo && in_array($estadoOriginal, self::ESTADOS_FINALES)) {
                throw new \Exception("No se puede modificar el estado de un crédito que ya está {$estadoOriginal}. El crédito #{$credito->numero_credito} no puede ser editado.");
            }
        });
    }

    protected $fillable = [
        'numero_credito',
        'cliente_id',
        'sucursal_id',
        'analista_id',
        'cajero_id',
        'tasador_id',
        'plan_interes_id',
        'estado',
        'fecha_solicitud',
        'fecha_analisis',
        'fecha_aprobacion',
        'fecha_desembolso',
        'fecha_primer_pago',
        'fecha_vencimiento',
        'fecha_cancelacion',
        'fecha_ultimo_pago',
        'fecha_incobrable',
        'monto_solicitado',
        'monto_aprobado',
        'monto_desembolsado',
        'valor_tasacion',
        'capital_pendiente',
        'capital_pagado',
        'interes_generado',
        'interes_pagado',
        'mora_generada',
        'mora_pagada',
        'tasa_interes',
        'tasa_mora',
        'tipo_interes',
        'metodo_calculo',
        'afecta_interes_mensual',
        'permite_pago_capital_diferente',
        'plazo_dias',
        'dias_gracia',
        'numero_cuotas',
        'monto_cuota',
        'dias_mora',
        'calificacion',
        'forma_desembolso',
        'referencia_desembolso',
        'observaciones',
        'motivo_rechazo',
        'motivo_incobrable',
        'numero_pagare',
        'numero_contrato',
        'requiere_renovacion',
        'credito_renovado_id',
        // Campos de refrendos
        'refrendos_realizados',
        'refrendos_maximos',
        'permite_refrendo',
        'fecha_ultimo_refrendo',
    ];

    protected $casts = [
        'fecha_solicitud' => 'date',
        'fecha_analisis' => 'date',
        'fecha_aprobacion' => 'date',
        'fecha_desembolso' => 'date',
        'fecha_primer_pago' => 'date',
        'fecha_vencimiento' => 'date',
        'fecha_cancelacion' => 'date',
        'fecha_ultimo_pago' => 'date',
        'fecha_incobrable' => 'date',
        'monto_solicitado' => 'decimal:2',
        'monto_aprobado' => 'decimal:2',
        'monto_desembolsado' => 'decimal:2',
        'valor_tasacion' => 'decimal:2',
        'capital_pendiente' => 'decimal:2',
        'capital_pagado' => 'decimal:2',
        'interes_generado' => 'decimal:2',
        'interes_pagado' => 'decimal:2',
        'mora_generada' => 'decimal:2',
        'mora_pagada' => 'decimal:2',
        'tasa_interes' => 'decimal:2',
        'tasa_mora' => 'decimal:2',
        'monto_cuota' => 'decimal:2',
        'afecta_interes_mensual' => 'boolean',
        'permite_pago_capital_diferente' => 'boolean',
        'requiere_renovacion' => 'boolean',
        'permite_refrendo' => 'boolean',
        'fecha_ultimo_refrendo' => 'datetime',
    ];

    // Relaciones
    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function analista()
    {
        return $this->belongsTo(User::class, 'analista_id');
    }

    public function cajero()
    {
        return $this->belongsTo(User::class, 'cajero_id');
    }

    public function tasador()
    {
        return $this->belongsTo(User::class, 'tasador_id');
    }

    public function planInteres()
    {
        return $this->belongsTo(PlanInteresCategoria::class, 'plan_interes_id');
    }

    public function prendas()
    {
        return $this->hasMany(Prenda::class, 'credito_prendario_id');
    }

    public function movimientos()
    {
        return $this->hasMany(CreditoMovimiento::class, 'credito_prendario_id');
    }

    public function planPagos()
    {
        return $this->hasMany(CreditoPlanPago::class, 'credito_prendario_id');
    }

    public function tasaciones()
    {
        return $this->hasMany(Tasacion::class, 'credito_prendario_id');
    }

    public function creditoRenovado()
    {
        return $this->belongsTo(CreditoPrendario::class, 'credito_renovado_id');
    }

    public function renovaciones()
    {
        return $this->hasMany(CreditoPrendario::class, 'credito_renovado_id');
    }

    /**
     * Relación: Refrendos del crédito
     */
    public function refrendos()
    {
        return $this->hasMany(Refrendo::class, 'credito_id')->orderBy('numero_refrendo', 'asc');
    }

    /**
     * Relación: Remates del crédito
     */
    public function remates()
    {
        return $this->hasMany(\App\Models\Remate::class, 'credito_id')->orderByDesc('fecha_remate');
    }

    /**
     * Relación muchos a muchos con gastos
     * Los gastos son cargos adicionales que NO generan interés
     */
    public function gastos()
    {
        return $this->belongsToMany(
            Gasto::class,
            'credito_gasto',
            'credito_id',
            'gasto_id',
            'id',
            'id_gasto'
        )
        ->withPivot('valor_calculado')
        ->withTimestamps();
    }

    /**
     * Calcular el total de gastos del crédito
     */
    public function calcularTotalGastos(): float
    {
        $montoOtorgado = (float) ($this->monto_aprobado ?? $this->monto_solicitado ?? 0);
        $total = 0;

        foreach ($this->gastos as $gasto) {
            $total += $gasto->calcularValor($montoOtorgado);
        }

        return round($total, 2);
    }

    // Scopes útiles
    public function scopeVigentes($query)
    {
        return $query->where('estado', 'vigente');
    }

    public function scopeVencidos($query)
    {
        return $query->where('estado', 'vencido');
    }

    public function scopeEnMora($query)
    {
        return $query->where('estado', 'en_mora');
    }

    public function scopePorCliente($query, $clienteId)
    {
        return $query->where('cliente_id', $clienteId);
    }

    public function scopePorSucursal($query, $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    // Métodos auxiliares
    public function calcularSaldoTotal()
    {
        return $this->capital_pendiente + $this->interes_generado - $this->interes_pagado + $this->mora_generada - $this->mora_pagada;
    }

    /**
     * Recalcular saldos desde el kardex (fuente única de verdad)
     * Este método calcula los saldos desde credito_movimientos, excluyendo movimientos anulados
     */
    public function recalcularSaldosDesdeKardex(): void
    {
        // Obtener todos los movimientos activos (no anulados ni reversados)
        // El modelo usa 'estado' = 'activo' para movimientos válidos
        $movimientosActivos = $this->movimientos()
            ->where('estado', 'activo')
            ->get();

        // Calcular capital pagado (suma de capital de pagos)
        $capitalPagado = $movimientosActivos
            ->whereIn('tipo_movimiento', ['pago', 'pago_parcial', 'pago_total', 'pago_adelantado'])
            ->sum('capital');

        // Calcular interés pagado
        $interesPagado = $movimientosActivos
            ->whereIn('tipo_movimiento', ['pago', 'pago_parcial', 'pago_total', 'pago_adelantado'])
            ->sum('interes');

        // Calcular mora pagada
        $moraPagada = $movimientosActivos
            ->whereIn('tipo_movimiento', ['pago', 'pago_parcial', 'pago_total', 'pago_adelantado', 'cargo_mora'])
            ->sum('mora');

        // Calcular capital pendiente
        $capitalPendiente = $this->monto_aprobado - $capitalPagado;

        // Calcular interés generado (suma de interés de todas las cuotas proyectadas)
        $interesGenerado = $this->planPagos()
            ->sum('interes_proyectado');

        // Calcular mora generada (suma de mora proyectada de cuotas vencidas)
        $moraGenerada = $this->planPagos()
            ->where('dias_mora', '>', 0)
            ->sum('mora_proyectada');

        // Actualizar campos de cache (se actualizan por jobs, pero aquí se recalcula)
        $this->update([
            'capital_pagado' => $capitalPagado,
            'capital_pendiente' => $capitalPendiente,
            'interes_pagado' => $interesPagado,
            'mora_pagada' => $moraPagada,
            'interes_generado' => $interesGenerado,
            'mora_generada' => $moraGenerada,
        ]);
    }

    /**
     * Obtener saldo actual calculado desde kardex (sin guardar)
     */
    public function getSaldoDesdeKardex(): array
    {
        $movimientosActivos = $this->movimientos()
            ->where('estado', 'activo')
            ->get();

        $capitalPagado = $movimientosActivos
            ->whereIn('tipo_movimiento', ['pago', 'pago_parcial', 'pago_total', 'pago_adelantado'])
            ->sum('capital');

        $interesPagado = $movimientosActivos
            ->whereIn('tipo_movimiento', ['pago', 'pago_parcial', 'pago_total', 'pago_adelantado'])
            ->sum('interes');

        $moraPagada = $movimientosActivos
            ->whereIn('tipo_movimiento', ['pago', 'pago_parcial', 'pago_total', 'pago_adelantado', 'cargo_mora'])
            ->sum('mora');

        $capitalPendiente = $this->monto_aprobado - $capitalPagado;

        $interesGenerado = $this->planPagos()
            ->sum('interes_proyectado');

        $moraGenerada = $this->planPagos()
            ->where('dias_mora', '>', 0)
            ->sum('mora_proyectada');

        $interesPendiente = $interesGenerado - $interesPagado;
        $moraPendiente = $moraGenerada - $moraPagada;
        $saldoTotal = $capitalPendiente + $interesPendiente + $moraPendiente;

        return [
            'capital_pagado' => (float) $capitalPagado,
            'capital_pendiente' => (float) $capitalPendiente,
            'interes_pagado' => (float) $interesPagado,
            'interes_generado' => (float) $interesGenerado,
            'interes_pendiente' => (float) $interesPendiente,
            'mora_pagada' => (float) $moraPagada,
            'mora_generada' => (float) $moraGenerada,
            'mora_pendiente' => (float) $moraPendiente,
            'saldo_total' => (float) $saldoTotal,
        ];
    }

    public function estaVencido()
    {
        return $this->fecha_vencimiento && $this->fecha_vencimiento->isPast() && in_array($this->estado, ['vigente', 'en_mora']);
    }

    public function puedeRenovarse()
    {
        return in_array($this->estado, ['vigente', 'vencido', 'en_mora']) && !$this->requiere_renovacion;
    }

    public function tienePagosAtrasados()
    {
        return $this->dias_mora > 0;
    }

    /**
     * Verificar si el crédito está completamente pagado
     * Un crédito está pagado cuando:
     * - Su estado es 'pagado', 'cancelado' o 'liquidado', O
     * - El capital pendiente es 0 o menor
     */
    public function getEstaPagadoAttribute(): bool
    {
        // Por estado explícito
        if (in_array($this->estado, ['pagado', 'cancelado', 'liquidado'])) {
            return true;
        }

        // Por saldo (capital pendiente = 0 o menor)
        $capitalPendiente = (float) ($this->capital_pendiente ?? 0);
        return $capitalPendiente <= 0 && $this->monto_aprobado > 0;
    }

    /**
     * Verificar si el crédito está en un estado final (no modificable)
     */
    public function getEstaEnEstadoFinalAttribute(): bool
    {
        return in_array($this->estado, self::ESTADOS_FINALES);
    }

    /**
     * Verificar si se puede editar el estado del crédito
     */
    public function puedeEditarEstado(): bool
    {
        return !$this->esta_en_estado_final;
    }

    /**
     * Obtener mensaje de por qué no se puede editar el estado
     */
    public function getMensajeEstadoBloqueadoAttribute(): ?string
    {
        if ($this->esta_en_estado_final) {
            return "El crédito #{$this->numero_credito} está en estado '{$this->estado}' y no puede ser modificado.";
        }
        return null;
    }
}
