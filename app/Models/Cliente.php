<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Cliente extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nombres',
        'apellidos',
        'dpi',
        'nit',
        'fecha_nacimiento',
        'genero',
        'telefono',
        'telefono_secundario',
        'email',
        'direccion',
        'municipio',
        'departamento_geoname_id',
        'municipio_geoname_id',
        'fotografia',
        'estado',
        'sucursal',
        'tipo_cliente',
        'notas',
        'eliminado',
        'eliminado_en',
    ];

    protected $casts = [
        'fecha_nacimiento' => 'date',
        'eliminado' => 'boolean',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    protected $appends = [
        'nombre_completo',
    ];

    /**
     * Obtener el nombre completo del cliente
     */
    public function getNombreCompletoAttribute(): string
    {
        return "{$this->nombres} {$this->apellidos}";
    }

    /**
     * Obtener la URL de la fotografía
     */
    public function getFotografiaUrlAttribute(): ?string
    {
        if (!$this->fotografia) {
            return null;
        }

        // Si es una URL completa, retornarla
        if (filter_var($this->fotografia, FILTER_VALIDATE_URL)) {
            return $this->fotografia;
        }

        // Si es una ruta de archivo, retornar la URL pública
        if (str_starts_with($this->fotografia, 'storage/')) {
            return asset($this->fotografia);
        }

        // Si es base64, retornarlo como está
        if (str_starts_with($this->fotografia, 'data:image')) {
            return $this->fotografia;
        }

        return asset('storage/' . $this->fotografia);
    }
}

