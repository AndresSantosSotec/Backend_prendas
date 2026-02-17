<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CtbTipoPoliza extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'ctb_tipo_poliza';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'requiere_documento',
        'activo',
    ];

    protected $casts = [
        'requiere_documento' => 'boolean',
        'activo' => 'boolean',
    ];

    /**
     * Asientos con este tipo de póliza
     */
    public function diarios(): HasMany
    {
        return $this->hasMany(CtbDiario::class, 'tipo_poliza_id');
    }

    /**
     * Parametrizaciones con este tipo de póliza
     */
    public function parametrizaciones(): HasMany
    {
        return $this->hasMany(CtbParametrizacionCuenta::class, 'tipo_poliza_id');
    }
}
