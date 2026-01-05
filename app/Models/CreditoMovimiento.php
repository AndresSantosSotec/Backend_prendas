<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CreditoMovimiento extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'credito_prendario_id',
        'usuario_id',
        'sucursal_id',
        'cuota_id',
        'numero_movimiento',
        'numero_recibo',
        'numero_factura',
        'tipo_movimiento',
        'numero_cuota',
        'fecha_movimiento',
        'fecha_registro',
        'fecha_boleta',
        'monto_total',
        'capital',
        'interes',
        'mora',
        'otros_cargos',
        'saldo_capital',
        'saldo_interes',
        'saldo_mora',
        'forma_pago',
        'banco',
        'numero_cuenta',
        'numero_cheque',
        'numero_autorizacion',
        'referencia_bancaria',
        'concepto',
        'observaciones',
        'estado',
        'reversado_por',
        'fecha_reversion',
        'motivo_reversion',
        'movimiento_reversa_id',
        'moneda',
        'tipo_cambio',
        'terminal',
        'turno',
        'ip_origen',
        'datos_adicionales',
    ];

    protected $casts = [
        'fecha_movimiento' => 'date',
        'fecha_registro' => 'datetime',
        'fecha_boleta' => 'date',
        'fecha_reversion' => 'datetime',
        'monto_total' => 'decimal:2',
        'capital' => 'decimal:2',
        'interes' => 'decimal:2',
        'mora' => 'decimal:2',
        'otros_cargos' => 'decimal:2',
        'saldo_capital' => 'decimal:2',
        'saldo_interes' => 'decimal:2',
        'saldo_mora' => 'decimal:2',
        'tipo_cambio' => 'decimal:4',
        'datos_adicionales' => 'array',
    ];

    // Relaciones
    public function creditoPrendario()
    {
        return $this->belongsTo(CreditoPrendario::class, 'credito_prendario_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function cuota()
    {
        return $this->belongsTo(CreditoPlanPago::class, 'cuota_id');
    }

    public function reversadoPor()
    {
        return $this->belongsTo(User::class, 'reversado_por');
    }

    public function movimientoReversa()
    {
        return $this->belongsTo(CreditoMovimiento::class, 'movimiento_reversa_id');
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    public function scopeReversados($query)
    {
        return $query->where('estado', 'reversado');
    }

    public function scopePagos($query)
    {
        return $query->whereIn('tipo_movimiento', ['pago', 'pago_parcial', 'pago_total', 'pago_adelantado']);
    }

    public function scopeDesembolsos($query)
    {
        return $query->where('tipo_movimiento', 'desembolso');
    }

    public function scopePorCredito($query, $creditoId)
    {
        return $query->where('credito_prendario_id', $creditoId);
    }

    public function scopePorFecha($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha_movimiento', [$fechaInicio, $fechaFin]);
    }

    // Métodos auxiliares
    public function puedeReversarse()
    {
        return $this->estado === 'activo' && $this->tipo_movimiento !== 'desembolso';
    }

    public function esPago()
    {
        return in_array($this->tipo_movimiento, ['pago', 'pago_parcial', 'pago_total', 'pago_adelantado']);
    }

    public function esDesembolso()
    {
        return $this->tipo_movimiento === 'desembolso';
    }
}
