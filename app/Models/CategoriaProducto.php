<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\Auditable;

class CategoriaProducto extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected string $auditoriaModulo = 'categorias';
    public static bool $auditarDeshabilitado = false;

    protected $table = 'categoria_productos';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'color',
        'icono',
        'orden',
        'activa',
        'tasa_interes_default',
        'tasa_mora_default',
        'tipo_mora_default',
        'mora_monto_fijo_default',
        'plazo_maximo_dias',
        'porcentaje_prestamo_maximo',
        'metodo_calculo_default',
        'afecta_interes_mensual',
        'permite_pago_capital_diferente',
        'campos_formulario',
        'campos_adicionales',
        'campos_dinamicos',
        // Configuración de refrendos
        'refrendos_maximos_default',
        'requiere_pago_capital_refrendo',
        'porcentaje_capital_minimo',
    ];

    protected $casts = [
        'activa' => 'boolean',
        'orden' => 'integer',
        'tasa_interes_default' => 'decimal:2',
        'tasa_mora_default' => 'decimal:2',
        'tipo_mora_default' => 'string',
        'mora_monto_fijo_default' => 'decimal:2',
        'plazo_maximo_dias' => 'integer',
        'porcentaje_prestamo_maximo' => 'decimal:2',
        'afecta_interes_mensual' => 'boolean',
        'permite_pago_capital_diferente' => 'boolean',
        'requiere_pago_capital_refrendo' => 'boolean',
        'porcentaje_capital_minimo' => 'decimal:2',
        'campos_formulario' => 'array',
        'campos_adicionales' => 'array',
        'campos_dinamicos' => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Relación con planes de interés de esta categoría
     */
    public function planesInteres(): HasMany
    {
        return $this->hasMany(PlanInteresCategoria::class, 'categoria_producto_id');
    }

    /**
     * Obtener plan de interés por defecto
     */
    public function planInteresDefault()
    {
        return $this->planesInteres()->where('es_default', true)->where('activo', true)->first();
    }

    /**
     * Obtener planes de interés activos
     */
    public function planesInteresActivos(): HasMany
    {
        return $this->planesInteres()->where('activo', true)->orderBy('orden');
    }
}
