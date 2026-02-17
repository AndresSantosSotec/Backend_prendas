<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CtbDiario extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ctb_diario';

    protected $fillable = [
        'numero_comprobante',
        'tipo_poliza_id',
        'moneda_id',
        'tipo_origen',
        'credito_prendario_id',
        'movimiento_credito_id',
        'venta_id',
        'caja_id',
        'compra_id',
        'numero_documento',
        'glosa',
        'fecha_documento',
        'fecha_contabilizacion',
        'sucursal_id',
        'usuario_id',
        'estado',
        'editable',
        'aprobado_por',
        'fecha_aprobacion',
        'anulado_por',
        'fecha_anulacion',
        'motivo_anulacion',
    ];

    protected $casts = [
        'fecha_documento' => 'date',
        'fecha_contabilizacion' => 'date',
        'fecha_aprobacion' => 'datetime',
        'fecha_anulacion' => 'datetime',
        'editable' => 'boolean',
    ];

    /**
     * Tipo de póliza
     */
    public function tipoPoliza(): BelongsTo
    {
        return $this->belongsTo(CtbTipoPoliza::class, 'tipo_poliza_id');
    }

    /**
     * Moneda
     */
    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class);
    }

    /**
     * Sucursal
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * Usuario que registra
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Usuario que aprueba
     */
    public function usuarioAprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    /**
     * Usuario que anula
     */
    public function usuarioAnulador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    /**
     * Crédito prendario relacionado
     */
    public function creditoPrendario(): BelongsTo
    {
        return $this->belongsTo(CreditoPrendario::class, 'credito_prendario_id');
    }

    /**
     * Venta relacionada
     */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    /**
     * Compra relacionada
     */
    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class);
    }

    /**
     * Movimientos del asiento
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(CtbMovimiento::class, 'diario_id');
    }

    /**
     * Calcular total debe
     */
    public function getTotalDebeAttribute(): float
    {
        return $this->movimientos->sum('monto_debe');
    }

    /**
     * Calcular total haber
     */
    public function getTotalHaberAttribute(): float
    {
        return $this->movimientos->sum('monto_haber');
    }

    /**
     * Verificar si el asiento está cuadrado
     */
    public function estaCuadrado(): bool
    {
        $totalDebe = $this->movimientos->sum('monto_debe');
        $totalHaber = $this->movimientos->sum('monto_haber');

        return abs($totalDebe - $totalHaber) < 0.01; // Tolerancia de 1 centavo
    }

    /**
     * Verificar si se puede editar
     */
    public function puedeEditarse(): bool
    {
        return $this->editable && in_array($this->estado, ['borrador', 'registrado']);
    }

    /**
     * Verificar si se puede anular
     */
    public function puedeAnularse(): bool
    {
        return in_array($this->estado, ['registrado', 'aprobado']);
    }
}
