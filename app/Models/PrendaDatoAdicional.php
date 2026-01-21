<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrendaDatoAdicional extends Model
{
    protected $table = 'prenda_datos_adicionales';

    protected $fillable = [
        'prenda_id',
        'campo_nombre',
        'campo_valor',
        'campo_tipo',
        'campo_label',
        'orden',
    ];

    protected $casts = [
        'orden' => 'integer',
    ];

    /**
     * Relación con la prenda
     */
    public function prenda(): BelongsTo
    {
        return $this->belongsTo(Prenda::class);
    }
}
