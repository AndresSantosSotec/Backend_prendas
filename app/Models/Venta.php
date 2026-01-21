<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venta extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'prenda_id',
        'credito_prendario_id',
        'codigo_venta',
        'cliente_nombre',
        'cliente_nit',
        'cliente_telefono',
        'cliente_email',
        'precio_publicado',
        'precio_final',
        'descuento',
        'metodo_pago',
        'referencia_pago',
        'vendedor_id',
        'sucursal_id',
        'fecha_venta',
        'fecha_cancelacion',
        'observaciones',
        'motivo_cancelacion',
        'estado'
    ];

    protected $casts = [
        'fecha_venta' => 'datetime',
        'fecha_cancelacion' => 'datetime',
        'precio_publicado' => 'decimal:2',
        'precio_final' => 'decimal:2',
        'descuento' => 'decimal:2',
    ];

    // Relaciones
    public function prenda()
    {
        return $this->belongsTo(Prenda::class);
    }

    public function creditoPrendario()
    {
        return $this->belongsTo(CreditoPrendario::class);
    }

    public function vendedor()
    {
        return $this->belongsTo(User::class, 'vendedor_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }
}
