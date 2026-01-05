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
        'fecha_vencimiento',
        'fecha_cancelacion',
        'fecha_ultimo_pago',
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
        'fecha_vencimiento' => 'date',
        'fecha_cancelacion' => 'date',
        'fecha_ultimo_pago' => 'date',
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
