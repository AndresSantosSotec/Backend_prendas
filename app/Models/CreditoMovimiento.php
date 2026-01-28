<?php

namespace App\Models;

use App\Services\ContabilidadService;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Log;

class CreditoMovimiento extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Boot del modelo - Eventos
     */
    protected static function booted()
    {
        // Generar asiento contable automático después de crear el movimiento
        static::created(function ($movimiento) {
            // Solo generar asiento si está habilitado en configuración
            if (!config('contabilidad.auto_asientos', false)) {
                return;
            }

            // Verificar si el tipo de operación está habilitado
            $tipoOperacion = null;

            if ($movimiento->esDesembolso()) {
                if (!config('contabilidad.auto_asientos_por_operacion.desembolso_credito', false)) {
                    return;
                }
                $tipoOperacion = 'desembolso_credito';
            } elseif ($movimiento->esPago()) {
                if (!config('contabilidad.auto_asientos_por_operacion.pago_credito', false)) {
                    return;
                }
                $tipoOperacion = 'pago_credito';
            }

            // Si no hay tipo de operación válido, salir
            if (!$tipoOperacion) {
                return;
            }

            try {
                $service = app(ContabilidadService::class);
                $asiento = $service->generarAsientoAutomatico($movimiento, $tipoOperacion);

                if (config('contabilidad.log_asientos', true)) {
                    Log::info("Asiento contable generado automáticamente", [
                        'movimiento_id' => $movimiento->id,
                        'asiento_id' => $asiento->id,
                        'tipo_operacion' => $tipoOperacion,
                        'numero_comprobante' => $asiento->numero_comprobante,
                    ]);
                }
            } catch (\Exception $e) {
                Log::error("Error al generar asiento contable automático", [
                    'movimiento_id' => $movimiento->id,
                    'tipo_operacion' => $tipoOperacion,
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);

                // No lanzar la excepción para no afectar el flujo principal
                // El movimiento se crea aunque falle el asiento
            }
        });
    }

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
