<?php

namespace App\Models\Contabilidad;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TbBanco extends Model
{
    use SoftDeletes;

    protected $table = 'tb_bancos';

    protected $fillable = [
        'nombre',
        'codigo_swift',
        'codigo_local',
        'abreviatura',
        'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Cuentas bancarias en este banco
     */
    public function cuentasBancarias(): HasMany
    {
        return $this->hasMany(CtbBanco::class, 'banco_id');
    }

    /**
     * Scope para bancos activos
     */
    public function scopeActivos($query)
    {
        return $query->where('activo', true);
    }

    /**
     * Scope para buscar por nombre
     */
    public function scopeBuscar($query, $termino)
    {
        return $query->where('nombre', 'like', "%{$termino}%")
            ->orWhere('abreviatura', 'like', "%{$termino}%");
    }
}
