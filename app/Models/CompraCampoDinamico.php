<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CompraCampoDinamico extends Model
{
    use HasFactory;

    protected $table = 'compra_campos_dinamicos';

    protected $fillable = [
        'compra_id',
        'campo_dinamico_id',
        'valor',
        'campo_nombre',
        'campo_tipo',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    /**
     * Relación con Compra
     */
    public function compra(): BelongsTo
    {
        return $this->belongsTo(Compra::class, 'compra_id');
    }

    /**
     * Nota: Los campos dinámicos están definidos en JSON en la tabla categoria_productos
     * Esta relación no se usa actualmente
     */

    /**
     * Obtener valor formateado según tipo
     */
    public function getValorFormateadoAttribute(): string
    {
        if (empty($this->valor)) return '-';

        return match($this->campo_tipo) {
            'number' => number_format($this->valor, 2),
            'currency' => '$' . number_format($this->valor, 2),
            'date' => date('d/m/Y', strtotime($this->valor)),
            'boolean' => $this->valor ? 'Sí' : 'No',
            default => $this->valor,
        };
    }
}
