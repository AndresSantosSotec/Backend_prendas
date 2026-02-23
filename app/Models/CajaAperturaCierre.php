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
        'boveda_destino_id',   // ← FK a la bóveda receptora del cierre diario
    ];

    protected $casts = [
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
}
