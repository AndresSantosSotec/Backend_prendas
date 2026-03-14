<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ClienteBorrador extends Model
{
    protected $table = 'cliente_borradores';

    protected $fillable = [
        'user_id',
        'titulo',
        'datos',
    ];

    protected $casts = [
        'datos' => 'array',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
