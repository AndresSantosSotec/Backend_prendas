<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use App\Traits\Auditable;

/**
 * Modelo Gasto
 *
 * Representa un tipo de gasto/cargo que puede asociarse a créditos prendarios.
 * Los gastos NO generan interés y se distribuyen prorrateados en las cuotas.
 *
 * Tipos:
 * - FIJO: Monto monetario fijo (ej. Q50)
 * - VARIABLE: Porcentaje del monto otorgado (ej. 2.5%)
 *
 * @property int $id_gasto
 * @property string $nombre
 * @property string $tipo
 * @property float|null $porcentaje
 * @property float|null $monto
 * @property string|null $descripcion
 * @property bool $activo
 */
class Gasto extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected string $auditoriaModulo = 'gastos';
    public static bool $auditarDeshabilitado = false;

    protected $table = 'gastos';
    protected $primaryKey = 'id_gasto';

    protected $fillable = [
        'nombre',
        'tipo',
        'porcentaje',
        'monto',
        'descripcion',
        'activo',
    ];

    protected $casts = [
        'porcentaje' => 'decimal:2',
        'monto' => 'decimal:2',
        'activo' => 'boolean',
    ];

    /**
     * Constantes para tipos de gasto
     */
    const TIPO_FIJO = 'FIJO';
    const TIPO_VARIABLE = 'VARIABLE';

    /**
     * Relación muchos a muchos con créditos prendarios
     */
    public function creditos(): BelongsToMany
    {
        return $this->belongsToMany(
            CreditoPrendario::class,
            'credito_gasto',
            'gasto_id',
            'credito_id'
        )
        ->withPivot('valor_calculado')
        ->withTimestamps();
    }

    /**
     * Calcular el valor del gasto para un monto dado
     *
     * @param float $montoOtorgado Monto del crédito
     * @return float Valor calculado del gasto
     */
    public function calcularValor(float $montoOtorgado): float
    {
        if ($this->tipo === self::TIPO_FIJO) {
            return round((float) $this->monto, 2);
        }

        if ($this->tipo === self::TIPO_VARIABLE) {
            return round($montoOtorgado * ((float) $this->porcentaje / 100), 2);
        }

        return 0;
    }

    /**
     * Verificar si el gasto es de tipo fijo
     */
    public function esFijo(): bool
    {
        return $this->tipo === self::TIPO_FIJO;
    }

    /**
     * Verificar si el gasto es de tipo variable
     */
    public function esVariable(): bool
    {
        return $this->tipo === self::TIPO_VARIABLE;
    }

    /**
     * Verificar si el gasto está asociado a algún crédito
     */
    public function tieneCreditos(): bool
    {
        return $this->creditos()->exists();
    }

    /**
     * Scope para gastos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para filtrar por tipo
     */
    public function scopeTipo($query, string $tipo)
    {
        return $query->where('tipo', $tipo);
    }

    /**
     * Scope para búsqueda por nombre
     */
    public function scopeBuscar($query, ?string $termino)
    {
        if (!$termino) {
            return $query;
        }

        return $query->where('nombre', 'LIKE', "%{$termino}%");
    }

    /**
     * Obtener el valor formateado según el tipo
     */
    public function getValorFormateadoAttribute(): string
    {
        if ($this->esFijo()) {
            return 'Q ' . number_format((float) $this->monto, 2);
        }

        return number_format((float) $this->porcentaje, 2) . '%';
    }
}
