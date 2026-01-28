<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class VentaDetalle extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'venta_id',
        'prenda_id',
        'producto_id',
        'codigo',
        'descripcion',
        'cantidad',
        'precio_unitario',
        'descuento',
        'descuento_porcentaje',
        'subtotal',
        'total',
        'tipo_precio',
        'observaciones',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'precio_unitario' => 'decimal:2',
        'descuento' => 'decimal:2',
        'descuento_porcentaje' => 'decimal:2',
        'subtotal' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    // Relaciones
    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function prenda()
    {
        return $this->belongsTo(Prenda::class);
    }

    // Accessors
    public function getMontoDescuentoAttribute()
    {
        return $this->descuento + (($this->precio_unitario * $this->cantidad * $this->descuento_porcentaje) / 100);
    }

    // Métodos
    public function calcularTotal()
    {
        $subtotal = $this->precio_unitario * $this->cantidad;
        $descuentoMonto = $this->descuento;
        $descuentoPorcentaje = ($subtotal * $this->descuento_porcentaje) / 100;

        $this->subtotal = $subtotal;
        $this->total = $subtotal - $descuentoMonto - $descuentoPorcentaje;

        return $this->total;
    }
}
