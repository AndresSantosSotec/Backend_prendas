<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Prenda extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'credito_prendario_id',
        'codigo_cliente_propietario',
        'categoria_producto_id',
        'tasador_id',
        'codigo_prenda',
        'descripcion',
        'marca',
        'modelo',
        'serie',
        'color',
        'caracteristicas',
        'valor_estimado_cliente',
        'valor_tasacion',
        'valor_prestamo',
        'porcentaje_prestamo',
        'valor_venta',
        'estado',
        'condicion',
        'ubicacion_fisica',
        'seccion',
        'estante',
        'fotos',
        'foto_principal',
        'fecha_ingreso',
        'fecha_tasacion',
        'fecha_recuperacion',
        'fecha_venta',
        'fecha_publicacion_venta',
        'comprador_id',
        'precio_venta',
        'factura_venta',
        'observaciones',
        'requiere_mantenimiento',
        'notas_mantenimiento',
        'datos_adicionales',
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'fecha_tasacion' => 'date',
        'fecha_recuperacion' => 'date',
        'fecha_venta' => 'date',
        'fecha_publicacion_venta' => 'datetime',
        'valor_estimado_cliente' => 'decimal:2',
        'valor_tasacion' => 'decimal:2',
        'valor_prestamo' => 'decimal:2',
        'porcentaje_prestamo' => 'decimal:2',
        'valor_venta' => 'decimal:2',
        'precio_venta' => 'decimal:2',
        'caracteristicas' => 'array',
        'fotos' => 'array',
        'datos_adicionales' => 'array',
        'requiere_mantenimiento' => 'boolean',
    ];

    // Relaciones
    public function creditoPrendario()
    {
        return $this->belongsTo(CreditoPrendario::class, 'credito_prendario_id');
    }

    public function categoriaProducto()
    {
        return $this->belongsTo(CategoriaProducto::class, 'categoria_producto_id');
    }

    public function tasador()
    {
        return $this->belongsTo(User::class, 'tasador_id');
    }

    public function comprador()
    {
        return $this->belongsTo(Cliente::class, 'comprador_id');
    }

    public function tasaciones()
    {
        return $this->hasMany(Tasacion::class, 'prenda_id');
    }

    /**
     * Relación con los datos adicionales normalizados (tabla EAV)
     */
    public function datosAdicionalesNormalizados()
    {
        return $this->hasMany(PrendaDatoAdicional::class, 'prenda_id')->orderBy('orden');
    }

    /**
     * Relación con las imágenes normalizadas
     */
    public function imagenesNormalizadas()
    {
        return $this->hasMany(PrendaImagen::class, 'prenda_id')->orderBy('es_principal', 'desc')->orderBy('orden');
    }

    /**
     * Obtener imagen principal
     */
    public function imagenPrincipal()
    {
        return $this->hasOne(PrendaImagen::class, 'prenda_id')->where('es_principal', true);
    }

    /**
     * Obtener imágenes por tipo
     */
    public function imagenesPorTipo(string $tipo)
    {
        return $this->imagenesNormalizadas()->where('tipo_imagen', $tipo)->get();
    }

    // Scopes
    public function scopeEnCustodia($query)
    {
        return $query->where('estado', 'en_custodia');
    }

    public function scopeRecuperadas($query)
    {
        return $query->where('estado', 'recuperada');
    }

    public function scopeEnVenta($query)
    {
        return $query->where('estado', 'en_venta');
    }

    public function scopeVendidas($query)
    {
        return $query->where('estado', 'vendida');
    }

    public function scopePorCategoria($query, $categoriaId)
    {
        return $query->where('categoria_producto_id', $categoriaId);
    }

    // Métodos auxiliares
    public function puedeRecuperarse()
    {
        return $this->estado === 'en_custodia' && $this->creditoPrendario->estado === 'pagado';
    }

    public function puedeVenderse()
    {
        return in_array($this->estado, ['en_custodia', 'en_venta']) &&
               in_array($this->creditoPrendario->estado, ['vencido', 'incobrable']);
    }

    public function diasEnCustodia()
    {
        if ($this->estado !== 'en_custodia') {
            return 0;
        }
        return now()->diffInDays($this->fecha_ingreso);
    }

    /**
     * Boot del modelo - sincronizar código de cliente
     */
    protected static function boot()
    {
        parent::boot();

        // Al crear/actualizar, sincronizar código del cliente
        static::saving(function ($prenda) {
            if ($prenda->credito_prendario_id && !$prenda->codigo_cliente_propietario) {
                $credito = $prenda->creditoPrendario;
                if ($credito && $credito->cliente) {
                    $prenda->codigo_cliente_propietario = $credito->cliente->codigo_cliente;
                }
            }
        });
    }

    /**
     * Obtener cliente propietario a través del crédito
     */
    public function clientePropietario()
    {
        return $this->hasOneThrough(
            Cliente::class,
            CreditoPrendario::class,
            'id', // Foreign key en creditos_prendarios
            'id', // Foreign key en clientes
            'credito_prendario_id', // Local key en prendas
            'cliente_id' // Local key en creditos_prendarios
        );
    }

    /**
     * Scope para buscar por código de cliente
     */
    public function scopePorCodigoCliente($query, $codigoCliente)
    {
        return $query->where('codigo_cliente_propietario', $codigoCliente);
    }
}
