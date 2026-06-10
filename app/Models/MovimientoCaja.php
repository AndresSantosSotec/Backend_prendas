<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class MovimientoCaja extends Model
{
    use HasFactory, Auditable;

    protected string $auditoriaModulo = 'caja';
    public static bool $auditarDeshabilitado = false;

    protected $table = 'movimiento_cajas';

    protected $fillable = [
        'caja_id',
        'tipo',
        'monto',
        'concepto',
        'detalles_movimiento',
        'estado',
        'user_id',
        'autorizado_por',
        // Campos de integración Caja-Bóveda
        'boveda_id',
        'boveda_movimiento_id',
        'estado_boveda',
        'fecha_aprobacion_boveda',
        'aprobado_por_id',
        'observaciones_boveda',
    ];

    protected $casts = [
        'detalles_movimiento'    => 'array',
        'fecha_aprobacion_boveda' => 'datetime',
    ];

    public function caja()
    {
        return $this->belongsTo(CajaAperturaCierre::class, 'caja_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function autorizador()
    {
        return $this->belongsTo(User::class, 'autorizado_por');
    }


    public function bovedaMovimiento()
    {
        return $this->belongsTo(BovedaMovimiento::class, 'boveda_movimiento_id');
    }

    /** Bóveda relacionada con este movimiento (modo integrado). */
    public function boveda()
    {
        return $this->belongsTo(Boveda::class, 'boveda_id');
    }

    public function aprobadoPor()
    {
        return $this->belongsTo(User::class, 'aprobado_por_id');
    }

    /**
     * Scope: movimientos pendientes de aprobación en bóveda.
     */
    public function scopePendientesBoveda($query)
    {
        return $query->where('estado_boveda', 'pendiente_aprobacion');
    }
}
