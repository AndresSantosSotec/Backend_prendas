<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClienteComplementoPep extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cli_complementos_pep';

    protected $fillable = [
        'id_cliente',
        'tipo',
        'parentesco',
        'motivoAsociacion',
        'detalleOtro',
        'primerNombre',
        'segundoNombre',
        'otrosNombres',
        'primerApellido',
        'segundoApellido',
        'apellidoCasada',
        'genero',
        'condicion',
        'entidad',
        'puesto',
        'pais',
        'estado',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }
}
