<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modelo pivot para gastos de créditos de ventas
 * Relaciona créditos de venta con gastos del sistema
 */
class VentaCreditoGasto extends Pivot
{
    protected $table = 'venta_credito_gastos';

    public $incrementing = true;

    protected $fillable = [
        'venta_credito_id',
        'gasto_id',
        'valor_calculado',
        'incluido_en_cuotas',
        'estado',
    ];

    protected $casts = [
        'valor_calculado' => 'decimal:2',
        'incluido_en_cuotas' => 'boolean',
    ];

    // ==================== RELACIONES ====================

    /**
     * Crédito de venta
     */
    public function ventaCredito(): BelongsTo
    {
        return $this->belongsTo(VentaCredito::class);
    }

    /**
     * Gasto asociado
     */
    public function gasto(): BelongsTo
    {
        return $this->belongsTo(Gasto::class, 'gasto_id', 'id_gasto');
    }
}
