<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Auditable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlanInteresCategoria extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected string $auditoriaModulo = 'planes_interes';
    public static bool $auditarDeshabilitado = false;

    protected $table = 'planes_interes_categoria';

    // Constantes de tipos de periodo
    public const PERIODO_DIARIO = 'diario';
    public const PERIODO_SEMANAL = 'semanal';
    public const PERIODO_QUINCENAL = 'quincenal';
    public const PERIODO_MENSUAL = 'mensual';

    public const PERIODOS = [
        self::PERIODO_DIARIO,
        self::PERIODO_SEMANAL,
        self::PERIODO_QUINCENAL,
        self::PERIODO_MENSUAL,
    ];

    // Constantes de unidades de plazo
    public const UNIDAD_DIAS = 'dias';
    public const UNIDAD_SEMANAS = 'semanas';
    public const UNIDAD_QUINCENAS = 'quincenas';
    public const UNIDAD_MESES = 'meses';

    public const UNIDADES = [
        self::UNIDAD_DIAS,
        self::UNIDAD_SEMANAS,
        self::UNIDAD_QUINCENAS,
        self::UNIDAD_MESES,
    ];

    protected $fillable = [
        'categoria_producto_id',
        'nombre',
        'codigo',
        'descripcion',
        'tipo_periodo',
        'plazo_numero',
        'plazo_unidad',
        'plazo_dias_total',
        'tasa_interes',
        'tasa_almacenaje',
        'tasa_moratorios',
        'tipo_mora',
        'mora_monto_fijo',
        'porcentaje_prestamo',
        'monto_minimo',
        'monto_maximo',
        'dias_gracia',
        'dias_enajenacion',
        'cat',
        'interes_anual',
        'porcentaje_precio_venta',
        'numero_refrendos_permitidos',
        'permite_refrendos',
        'activo',
        'es_default',
        'orden',
    ];

    protected $casts = [
        'categoria_producto_id' => 'integer',
        'plazo_numero' => 'integer',
        'plazo_dias_total' => 'integer',
        'tasa_interes' => 'decimal:4',
        'tasa_almacenaje' => 'decimal:4',
        'tasa_moratorios' => 'decimal:4',
        'tipo_mora' => 'string',
        'mora_monto_fijo' => 'decimal:2',
        'porcentaje_prestamo' => 'decimal:2',
        'monto_minimo' => 'decimal:2',
        'monto_maximo' => 'decimal:2',
        'dias_gracia' => 'integer',
        'dias_enajenacion' => 'integer',
        'cat' => 'decimal:2',
        'interes_anual' => 'decimal:2',
        'porcentaje_precio_venta' => 'decimal:2',
        'numero_refrendos_permitidos' => 'integer',
        'permite_refrendos' => 'boolean',
        'activo' => 'boolean',
        'es_default' => 'boolean',
        'orden' => 'integer',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Boot del modelo
     */
    protected static function boot()
    {
        parent::boot();

        // Calcular automáticamente el plazo en días al crear/actualizar
        static::saving(function ($plan) {
            $plan->plazo_dias_total = $plan->calcularPlazoDias();

            // Generar código si no existe
            if (empty($plan->codigo)) {
                $plan->codigo = $plan->generarCodigo();
            }

            // Solo puede haber un plan default por categoría
            if ($plan->es_default) {
                static::where('categoria_producto_id', $plan->categoria_producto_id)
                    ->where('id', '!=', $plan->id)
                    ->update(['es_default' => false]);
            }
        });
    }

    /**
     * Relación con categoría de producto
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaProducto::class, 'categoria_producto_id');
    }

    /**
     * Relación con créditos que usan este plan
     */
    public function creditos(): HasMany
    {
        return $this->hasMany(CreditoPrendario::class, 'plan_interes_id');
    }

    /**
     * Scope: Planes activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope: Planes de una categoría
     */
    public function scopeDeCategoria($query, $categoriaId)
    {
        return $query->where('categoria_producto_id', $categoriaId);
    }

    /**
     * Scope: Planes por tipo de periodo
     */
    public function scopePorPeriodo($query, $periodo)
    {
        return $query->where('tipo_periodo', $periodo);
    }

    /**
     * Scope: Plan default de una categoría
     */
    public function scopePlanDefault($query, $categoriaId)
    {
        return $query->where('categoria_producto_id', $categoriaId)
                     ->where('es_default', true)
                     ->where('activo', true);
    }

    /**
     * Scope: Ordenados por visualización
     */
    public function scopeOrdenados($query)
    {
        return $query->orderBy('orden', 'asc')
                     ->orderBy('plazo_dias_total', 'asc');
    }

    /**
     * Calcular plazo total en días según unidad
     */
    public function calcularPlazoDias(): int
    {
        return match($this->plazo_unidad) {
            self::UNIDAD_DIAS => $this->plazo_numero,
            self::UNIDAD_SEMANAS => $this->plazo_numero * 7,
            self::UNIDAD_QUINCENAS => $this->plazo_numero * 15,
            self::UNIDAD_MESES => $this->plazo_numero * 30,
            default => $this->plazo_numero,
        };
    }

    /**
     * Generar código único para el plan
     */
    public function generarCodigo(): string
    {
        $prefijo = strtoupper(substr($this->tipo_periodo, 0, 1));
        $numero = str_pad($this->plazo_numero, 2, '0', STR_PAD_LEFT);
        $unidad = strtoupper(substr($this->plazo_unidad, 0, 1));

        $base = "{$prefijo}{$numero}{$unidad}";

        // Verificar unicidad dentro de la misma categoría
        $query = static::where('categoria_producto_id', $this->categoria_producto_id)
            ->where('codigo', 'like', "{$base}%");

        // Excluir el registro actual al actualizar
        if ($this->id) {
            $query->where('id', '!=', $this->id);
        }

        $existentes = $query->pluck('codigo')->all();

        if (!in_array($base, $existentes)) {
            return $base;
        }

        // Agregar sufijo numérico hasta encontrar uno libre
        $contador = 2;
        while (in_array("{$base}{$contador}", $existentes)) {
            $contador++;
        }

        return "{$base}{$contador}";
    }

    /**
     * Calcular fecha de vencimiento desde una fecha de inicio
     */
    public function calcularFechaVencimiento(\DateTime $fechaInicio): \DateTime
    {
        $fecha = clone $fechaInicio;
        $fecha->modify("+{$this->plazo_dias_total} days");
        return $fecha;
    }

    /**
     * Calcular interés total para un monto dado
     */
    public function calcularInteresTotal(float $montoCapital): float
    {
        $tasaTotal = $this->tasa_interes + $this->tasa_almacenaje;

        // Calcular según el tipo de periodo
        $numeroPeriodos = match($this->tipo_periodo) {
            self::PERIODO_DIARIO => $this->plazo_dias_total,
            self::PERIODO_SEMANAL => $this->plazo_numero,
            self::PERIODO_QUINCENAL => $this->plazo_numero,
            self::PERIODO_MENSUAL => $this->plazo_numero,
            default => $this->plazo_numero,
        };

        return round($montoCapital * ($tasaTotal / 100) * $numeroPeriodos, 2);
    }

    /**
     * Calcular monto máximo de préstamo según avalúo
     */
    public function calcularMontoPrestamo(float $valorAvaluo): float
    {
        $montoPrestamo = round($valorAvaluo * ($this->porcentaje_prestamo / 100), 2);

        // Validar limites si existen
        if ($this->monto_minimo && $montoPrestamo < $this->monto_minimo) {
            return $this->monto_minimo;
        }

        if ($this->monto_maximo && $montoPrestamo > $this->monto_maximo) {
            return $this->monto_maximo;
        }

        return $montoPrestamo;
    }

    /**
     * Verificar si un monto está dentro del rango permitido
     */
    public function validarMontoEnRango(float $monto): bool
    {
        if ($this->monto_minimo && $monto < $this->monto_minimo) {
            return false;
        }

        if ($this->monto_maximo && $monto > $this->monto_maximo) {
            return false;
        }

        return true;
    }

    /**
     * Accessor: Nombre completo del plan
     */
    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombre} ({$this->tasa_interes}% {$this->tipo_periodo})";
    }

    /**
     * Accessor: Descripción del plazo
     */
    public function getDescripcionPlazoAttribute(): string
    {
        $unidadTexto = [
            'dias' => 'día(s)',
            'semanas' => 'semana(s)',
            'quincenas' => 'quincena(s)',
            'meses' => 'mes(es)',
        ];

        return "{$this->plazo_numero} {$unidadTexto[$this->plazo_unidad]} ({$this->plazo_dias_total} días)";
    }

    /**
     * Método estático: Obtener plan default de una categoría
     */
    public static function obtenerPlanDefault(int $categoriaId): ?self
    {
        return static::planDefault($categoriaId)->first();
    }

    /**
     * Método estático: Buscar plan compatible con monto y categoría
     */
    public static function buscarPlanCompatible(int $categoriaId, float $monto, ?string $periodo = null): ?self
    {
        $query = static::deCategoria($categoriaId)
                      ->activos()
                      ->ordenados();

        if ($periodo) {
            $query->porPeriodo($periodo);
        }

        $planes = $query->get();

        foreach ($planes as $plan) {
            if ($plan->validarMontoEnRango($monto)) {
                return $plan;
            }
        }

        return null;
    }
}
