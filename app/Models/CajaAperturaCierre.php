<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\User;
use App\Models\Sucursal;
use App\Models\MovimientoCaja;
use App\Models\Boveda;
use App\Traits\Auditable;

class CajaAperturaCierre extends Model
{
    use HasFactory, Auditable;

    protected string $auditoriaModulo = 'caja';
    public static bool $auditarDeshabilitado = false;

    protected $table = 'caja_apertura_cierres';

    protected $fillable = [
        'user_id',
        'sucursal_id',
        'fecha_apertura',
        'hora_apertura',
        'saldo_inicial',
        'saldo_final',
        'fecha_cierre',
        'diferencia',
        'resultado_arqueo',
        'detalles_arqueo',
        'estado',
        'boveda_destino_id',       // FK a la bóveda receptora del cierre diario
        // Campos integración Caja-Bóveda (modo integrado)
        'boveda_origen_id',            // Bóveda de la que se extrajo el saldo inicial
        'boveda_movimiento_apertura_id', // ID del movimiento de bóveda del retiro inicial
    ];

    protected $casts = [
        'user_id'         => 'integer',
        'sucursal_id'     => 'integer',
        'detalles_arqueo' => 'array',
        'fecha_cierre'    => 'datetime',
        'saldo_inicial'   => 'decimal:2',
        'saldo_final'     => 'decimal:2',
        'diferencia'      => 'decimal:2',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function movimientos()
    {
        return $this->hasMany(MovimientoCaja::class, 'caja_id');
    }

    /**
     * Bóveda a la que se transfirió el saldo al cerrar.
     */
    public function bovedaDestino()
    {
        return $this->belongsTo(Boveda::class, 'boveda_destino_id');
    }

    /**
     * Bóveda de la que se tomó el saldo inicial al abrir (modo integrado).
     */
    public function bovedaOrigen()
    {
        return $this->belongsTo(Boveda::class, 'boveda_origen_id');
    }
}

