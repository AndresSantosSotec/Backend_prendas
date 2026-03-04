<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ClienteReferencia extends Model
{
    use HasFactory;

    protected $table = 'cliente_referencias';

    protected $fillable = [
        'cliente_id',
        'nombre',
        'telefono',
        'relacion',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }
}

