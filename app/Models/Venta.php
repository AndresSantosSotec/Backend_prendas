<?php

namespace App\Models;

use App\Enums\EstadoVenta;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Venta extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        // Campos anteriores
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
        'estado',
        // Nuevos campos del sistema completo
        'tipo_documento',
        'numero_documento',
        'serie_documento',
        'cliente_id',
        'consumidor_final',
        'moneda_id',
        'tipo_cambio',
        'subtotal',
        'total_descuentos',
        'total_impuestos',
        'total_final',
        'total_pagado',
        'cambio_devuelto',
        'tipo_venta',
        'certificada',
        'no_autorizacion',
        'fecha_certificacion',
        'notas',
        // Campos plan de pagos y apartados
        'plazo_dias',
        'fecha_vencimiento',
        'enganche',
        'saldo_pendiente',
        'numero_cuotas',
        'monto_cuota',
        'frecuencia_pago',
        'fecha_proximo_pago',
        'cuotas_pagadas',
        'intereses',
        'tasa_interes',
        'fecha_liquidacion',
    ];

    protected $casts = [
        'fecha_venta' => 'datetime',
        'fecha_cancelacion' => 'datetime',
        'fecha_certificacion' => 'datetime',
        'fecha_vencimiento' => 'datetime',
        'fecha_proximo_pago' => 'datetime',
        'fecha_liquidacion' => 'datetime',
        'precio_publicado' => 'decimal:2',
        'precio_final' => 'decimal:2',
        'descuento' => 'decimal:2',
        'tipo_cambio' => 'decimal:4',
        'subtotal' => 'decimal:2',
        'total_descuentos' => 'decimal:2',
        'total_impuestos' => 'decimal:2',
        'total_final' => 'decimal:2',
        'total_pagado' => 'decimal:2',
        'cambio_devuelto' => 'decimal:2',
        'enganche' => 'decimal:2',
        'saldo_pendiente' => 'decimal:2',
        'monto_cuota' => 'decimal:2',
        'intereses' => 'decimal:2',
        'tasa_interes' => 'decimal:2',
        'consumidor_final' => 'boolean',
        'certificada' => 'boolean',
        'cuotas_pagadas' => 'integer',
        'numero_cuotas' => 'integer',
        'plazo_dias' => 'integer',
    ];

    // Relaciones anteriores
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

    // Nuevas relaciones
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function moneda()
    {
        return $this->belongsTo(Moneda::class);
    }

    public function detalles()
    {
        return $this->hasMany(VentaDetalle::class);
    }

    public function pagos()
    {
        return $this->hasMany(VentaPago::class);
    }

    public function apartado()
    {
        return $this->hasOne(Apartado::class);
    }

    // Scopes
    public function scopePorEstado($query, $estado)
    {
        return $query->where('estado', $estado);
    }

    public function scopePorTipoVenta($query, $tipo)
    {
        return $query->where('tipo_venta', $tipo);
    }

    public function scopeEntreFechas($query, $desde, $hasta)
    {
        return $query->whereBetween('fecha_venta', [$desde, $hasta]);
    }

    public function scopeCertificadas($query)
    {
        return $query->where('certificada', true);
    }

    // Accessors
    public function getEsPagadaAttribute()
    {
        return $this->estado === 'pagada';
    }

    public function getTieneSaldoAttribute()
    {
        return $this->total_final > $this->total_pagado;
    }

    // Métodos
    public function calcularTotales()
    {
        $this->subtotal = $this->detalles->sum('subtotal');
        $this->total_descuentos = $this->detalles->sum(function($detalle) {
            return $detalle->descuento + (($detalle->precio_unitario * $detalle->cantidad * $detalle->descuento_porcentaje) / 100);
        });
        $this->total_final = $this->subtotal - $this->total_descuentos + $this->total_impuestos;
        $this->save();
    }

    public function actualizarPagos()
    {
        $this->total_pagado = $this->pagos->sum('monto');
        $this->cambio_devuelto = $this->pagos->sum('cambio');

        if ($this->total_pagado >= $this->total_final) {
            $this->estado = 'pagada';
        }

        $this->save();
    }

    public function generarNumeroDocumento()
    {
        $sucursal = $this->sucursal_id ?? 1;
        $serie = $this->serie_documento ?? 'A';
        $correlativo = static::where('sucursal_id', $sucursal)
            ->whereYear('created_at', now()->year)
            ->count() + 1;

        $this->numero_documento = sprintf('%s-%04d-%08d', $serie, $sucursal, $correlativo);
        $this->save();
    }
}
