<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Compra extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'compras';

    protected $fillable = [
        'cliente_id',
        'prenda_id',
        'categoria_producto_id',
        'sucursal_id',
        'usuario_id',
        'movimiento_caja_id',
        'codigo_compra',
        'cliente_nombre',
        'cliente_documento',
        'cliente_telefono',
        'cliente_codigo',
        'categoria_nombre',
        'descripcion',
        'marca',
        'modelo',
        'serie',
        'color',
        'condicion',
        'valor_tasacion',
        'monto_pagado',
        'precio_venta_sugerido',
        'metodo_pago',
        'genera_egreso_caja',
        'estado',
        'observaciones',
        'fecha_compra',
        'codigo_prenda_generado',
        'datos_adicionales',
    ];

    protected $casts = [
        'valor_tasacion' => 'decimal:2',
        'monto_pagado' => 'decimal:2',
        'precio_venta_sugerido' => 'decimal:2',
        'genera_egreso_caja' => 'boolean',
        'fecha_compra' => 'datetime',
        'datos_adicionales' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relación con Cliente
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /**
     * Relación con la Prenda generada
     */
    public function prenda(): BelongsTo
    {
        return $this->belongsTo(Prenda::class, 'prenda_id');
    }

    /**
     * Relación con Categoría de Producto
     */
    public function categoriaProducto(): BelongsTo
    {
        return $this->belongsTo(CategoriaProducto::class, 'categoria_producto_id');
    }

    /**
     * Relación con Sucursal
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_id');
    }

    /**
     * Relación con Usuario que registró la compra
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Relación con el Movimiento de Caja
     */
    public function movimientoCaja(): BelongsTo
    {
        return $this->belongsTo(MovimientoCaja::class, 'movimiento_caja_id');
    }

    /**
     * Relación con Campos Dinámicos
     */
    public function camposDinamicos(): HasMany
    {
        return $this->hasMany(CompraCampoDinamico::class, 'compra_id');
    }

    /**
     * Scopes
     */
    public function scopeActivas($query)
    {
        return $query->where('estado', 'activa');
    }

    public function scopePorSucursal($query, $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopePorFecha($query, $fechaInicio, $fechaFin = null)
    {
        if ($fechaFin) {
            return $query->whereBetween('fecha_compra', [$fechaInicio, $fechaFin]);
        }
        return $query->whereDate('fecha_compra', $fechaInicio);
    }

    public function scopeRecientes($query)
    {
        return $query->orderBy('fecha_compra', 'desc');
    }

    /**
     * Calcular margen de utilidad esperado
     */
    public function getMargenEsperadoAttribute(): float
    {
        if ($this->monto_pagado <= 0) return 0;
        return round((($this->precio_venta_sugerido - $this->monto_pagado) / $this->monto_pagado) * 100, 2);
    }

    /**
     * Verificar si fue vendida
     */
    public function esVendida(): bool
    {
        return $this->estado === 'vendida';
    }

    /**
     * Verificar si está activa
     */
    public function esActiva(): bool
    {
        return $this->estado === 'activa';
    }
}
