<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class MovimientoCaja extends Model
{
    use HasFactory;

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
    ];

    protected $casts = [
        'detalles_movimiento' => 'array',
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
}
