<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon;

class Remate extends Model
{
    use SoftDeletes;

    protected $table = 'remates';

    // Tipos de remate
    public const TIPO_MANUAL = 'manual';
    public const TIPO_AUTOMATICO = 'automatico';

    // Estados del remate
    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_EJECUTADO = 'ejecutado';
    public const ESTADO_CANCELADO = 'cancelado';
    public const ESTADO_VENDIDO = 'vendido';

    protected $fillable = [
        'codigo_remate',
        'credito_id',
        'prenda_id',
        'sucursal_id',
        'usuario_id',
        'tipo',
        'estado',
        'capital_pendiente',
        'intereses_pendientes',
        'mora_pendiente',
        'deuda_total',
        'valor_avaluo',
        'precio_remate',
        'fecha_vencimiento_credito',
        'dias_vencido',
        'fecha_remate',
        'fecha_venta_remate',
        'motivo',
        'observaciones',
    ];

    protected $casts = [
        'capital_pendiente' => 'decimal:2',
        'intereses_pendientes' => 'decimal:2',
        'mora_pendiente' => 'decimal:2',
        'deuda_total' => 'decimal:2',
        'valor_avaluo' => 'decimal:2',
        'precio_remate' => 'decimal:2',
        'dias_vencido' => 'integer',
        'fecha_remate' => 'datetime',
        'fecha_venta_remate' => 'datetime',
        'fecha_vencimiento_credito' => 'date',
    ];

    /**
     * Boot: auto-generar código de remate
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($remate) {
            if (empty($remate->codigo_remate)) {
                $remate->codigo_remate = self::generarCodigo();
            }

            // Calcular días vencido si no se proporcionó
            if ($remate->fecha_vencimiento_credito && !$remate->dias_vencido) {
                $remate->dias_vencido = max(0, Carbon::parse($remate->fecha_vencimiento_credito)->diffInDays(now()));
            }

            // Calcular deuda total
            if (!$remate->deuda_total) {
                $remate->deuda_total = ($remate->capital_pendiente ?? 0)
                    + ($remate->intereses_pendientes ?? 0)
                    + ($remate->mora_pendiente ?? 0);
            }
        });
    }

    /**
     * Generar código único: REM-YYYYMMDD-XXXXXX
     */
    public static function generarCodigo(): string
    {
        $fecha = now()->format('Ymd');
        $ultimo = self::withTrashed()
            ->where('codigo_remate', 'like', "REM-{$fecha}-%")
            ->orderByDesc('id')
            ->value('codigo_remate');

        $secuencial = 1;
        if ($ultimo) {
            $partes = explode('-', $ultimo);
            $secuencial = intval(end($partes)) + 1;
        }

        return sprintf("REM-%s-%06d", $fecha, $secuencial);
    }

    // ===== RELACIONES =====

    public function credito(): BelongsTo
    {
        return $this->belongsTo(CreditoPrendario::class, 'credito_id');
    }

    public function prenda(): BelongsTo
    {
        return $this->belongsTo(Prenda::class, 'prenda_id');
    }

    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    // ===== SCOPES =====

    public function scopePendientes($query)
    {
        return $query->where('estado', self::ESTADO_PENDIENTE);
    }

    public function scopeEjecutados($query)
    {
        return $query->where('estado', self::ESTADO_EJECUTADO);
    }

    public function scopeManuales($query)
    {
        return $query->where('tipo', self::TIPO_MANUAL);
    }

    public function scopeAutomaticos($query)
    {
        return $query->where('tipo', self::TIPO_AUTOMATICO);
    }

    public function scopeDeSucursal($query, $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }
}
