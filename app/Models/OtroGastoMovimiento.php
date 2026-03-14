<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class OtroGastoMovimiento extends Model
{
    use SoftDeletes;

    protected $table = 'otro_gasto_movimientos';

    protected $fillable = [
        'user_id',
        'sucursal_id',
        'otro_gasto_tipo_id',
        'caja_id',
        'movimiento_caja_id',
        'fecha',
        'tipo',
        'monto',
        'concepto',
        'descripcion',
        'numero_recibo',
        'forma_pago',
        'estado',
        'anulado_motivo',
    ];

    protected $casts = [
        'fecha'  => 'date',
        'monto'  => 'decimal:2',
    ];

    public function tipo_gasto()
    {
        return $this->belongsTo(OtroGastoTipo::class, 'otro_gasto_tipo_id');
    }

    public function user()
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function sucursal()
    {
        return $this->belongsTo(\App\Models\Sucursal::class);
    }

    public function caja()
    {
        return $this->belongsTo(CajaAperturaCierre::class, 'caja_id');
    }

    public function movimientoCaja()
    {
        return $this->belongsTo(MovimientoCaja::class, 'movimiento_caja_id');
    }
}
