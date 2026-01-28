<?php

namespace App\Models\Contabilidad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CtbTipoPoliza extends Model
{
    protected $table = 'ctb_tipo_poliza';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'requiere_aprobacion',
        'usuario_aprobador_rol',
        'activo',
    ];

    protected $casts = [
        'requiere_aprobacion' => 'boolean',
        'activo' => 'boolean',
    ];

    /**
     * Asientos que usan este tipo de póliza
     */
    public function asientos(): HasMany
    {
        return $this->hasMany(CtbDiario::class, 'tipo_poliza_id');
    }

    /**
     * Scope para tipos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para buscar por código
     */
    public function scopePorCodigo($query, $codigo)
    {
        return $query->where('codigo', $codigo);
    }
}
