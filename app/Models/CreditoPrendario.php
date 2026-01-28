<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditoPrendario extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'creditos_prendarios';

    protected $fillable = [
        'numero_credito',
        'cliente_id',
        'sucursal_id',
        'analista_id',
        'cajero_id',
        'tasador_id',
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
            ->whereIn('tipo_movimiento', ['pago_cuota', 'pago_parcial', 'pago', 'rescate', 'pago_total'])
            ->sum('capital');

        // Calcular interés pagado
        $interesPagado = $movimientosActivos
            ->whereIn('tipo_movimiento', ['pago_cuota', 'pago_parcial', 'pago', 'rescate', 'pago_total'])
            ->sum('interes');

        // Calcular mora pagada
        $moraPagada = $movimientosActivos
            ->whereIn('tipo_movimiento', ['pago_cuota', 'pago_parcial', 'pago', 'rescate', 'pago_total', 'pago_mora'])
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
            ->whereIn('tipo_movimiento', ['pago_cuota', 'pago_parcial', 'pago', 'rescate', 'pago_total'])
            ->sum('capital');

        $interesPagado = $movimientosActivos
            ->whereIn('tipo_movimiento', ['pago_cuota', 'pago_parcial', 'pago', 'rescate', 'pago_total'])
            ->sum('interes');

        $moraPagada = $movimientosActivos
            ->whereIn('tipo_movimiento', ['pago_cuota', 'pago_parcial', 'pago', 'rescate', 'pago_total', 'pago_mora'])
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
}
