<?php

namespace App\Models;

use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo para movimientos de créditos de ventas (Kardex)
 * Registra todos los pagos de cuotas de ventas a crédito
 */
class VentaCreditoMovimiento extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected string $auditoriaModulo = 'ventas_credito';
    public static bool $auditarDeshabilitado = false;

    protected $table = 'venta_credito_movimientos';

    protected $fillable = [
        'venta_credito_id',
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
        'saldo_total',
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
        'caja_apertura_cierre_id',
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
        'saldo_total' => 'decimal:2',
        'tipo_cambio' => 'decimal:4',
        'datos_adicionales' => 'json',
    ];

    // ==================== RELACIONES ====================

    /**
     * Crédito de venta al que pertenece
     */
    public function ventaCredito(): BelongsTo
    {
        return $this->belongsTo(VentaCredito::class);
    }

    /**
     * Usuario que registró el movimiento
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Sucursal donde se realizó
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * Cuota afectada por el movimiento
     */
    public function cuota(): BelongsTo
    {
        return $this->belongsTo(VentaCreditoPlanPago::class, 'cuota_id');
    }

    /**
     * Usuario que reversó el movimiento
     */
    public function reversadoPorUsuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversado_por');
    }

    /**
     * Movimiento de reversa asociado
     */
    public function movimientoReversa(): BelongsTo
    {
        return $this->belongsTo(VentaCreditoMovimiento::class, 'movimiento_reversa_id');
    }

    /**
     * Caja donde se registró
     */
    public function cajaAperturaCierre(): BelongsTo
    {
        return $this->belongsTo(CajaAperturaCierre::class);
    }

    // ==================== SCOPES ====================

    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    public function scopePagos($query)
    {
        return $query->whereIn('tipo_movimiento', ['pago', 'pago_parcial', 'pago_total', 'pago_adelantado']);
    }

    public function scopeDelCredito($query, int $creditoId)
    {
        return $query->where('venta_credito_id', $creditoId);
    }

    public function scopeEnFecha($query, string $fecha)
    {
        return $query->whereDate('fecha_movimiento', $fecha);
    }

    public function scopeEnRangoFechas($query, string $desde, string $hasta)
    {
        return $query->whereBetween('fecha_movimiento', [$desde, $hasta]);
    }

    // ==================== MÉTODOS ====================

    /**
     * Generar número de movimiento único
     */
    public static function generarNumeroMovimiento(): string
    {
        $prefix = 'VCM-' . date('Ymd') . '-';
        $ultimo = self::whereDate('created_at', today())->count() + 1;
        return $prefix . str_pad($ultimo, 5, '0', STR_PAD_LEFT);
    }

    /**
     * Verificar si es un pago
     */
    public function esPago(): bool
    {
        return in_array($this->tipo_movimiento, ['pago', 'pago_parcial', 'pago_total', 'pago_adelantado', 'enganche']);
    }

    /**
     * Verificar si está reversado
     */
    public function estaReversado(): bool
    {
        return $this->estado === 'reversado';
    }

    /**
     * Verificar si puede reversarse
     */
    public function puedeReversarse(): bool
    {
        return $this->estado === 'activo' && $this->esPago();
    }
}
