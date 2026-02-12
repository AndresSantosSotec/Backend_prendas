<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\Auditable;

class Sucursal extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected string $auditoriaModulo = 'sucursales';
    public static bool $auditarDeshabilitado = false;

    protected $table = 'sucursales';

    protected $fillable = [
        'codigo',
        'nombre',
        'direccion',
        'telefono',
        'email',
        'ciudad',
        'departamento',
        'municipio',
        'departamento_geoname_id',
        'municipio_geoname_id',
        'pais',
        'pais_geoname_id',
        'descripcion',
        'activa',
    ];

    protected $casts = [
        'activa' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Obtener clientes de esta sucursal
     */
    public function clientes()
    {
        return $this->hasMany(Cliente::class, 'sucursal', 'codigo');
    }
}
