<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CliPep extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cli_pep';

    protected $fillable = [
        'id_cliente',
        'entidad',
        'puesto',
        'paisEntidad',
        'origen',
    ];

    public function cliente()
    {
        return $this->belongsTo(Cliente::class, 'id_cliente');
    }
}
