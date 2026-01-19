<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;

class IdempotencyKey extends Model
{
    use HasFactory;

    protected $table = 'idempotency_keys';
    public $timestamps = false; // Solo usamos created_at manualmente o via DB default

    protected $fillable = [
        'key_hash',
        'operacion',
        'credito_prendario_id',
        'movimiento_id',
        'resultado',
    ];

    protected $casts = [
        'resultado' => 'array',
        'created_at' => 'datetime',
    ];

    /**
     * Verificar si un idempotency_key ya existe
     */
    public static function existe(string $idempotencyKey): bool
    {
        $hash = hash('sha256', $idempotencyKey);
        return self::where('key_hash', $hash)->exists();
    }

    /**
     * Guardar un idempotency_key
     */
    public static function guardar(
        string $idempotencyKey,
        string $operacion,
        ?int $creditoPrendarioId = null,
        ?int $movimientoId = null,
        ?array $resultado = null
    ): self {
        $hash = hash('sha256', $idempotencyKey);
        
        return self::create([
            'key_hash' => $hash,
            'operacion' => $operacion,
            'credito_prendario_id' => $creditoPrendarioId,
            'movimiento_id' => $movimientoId,
            'resultado' => $resultado,
        ]);
    }

    /**
     * Obtener resultado guardado de un idempotency_key
     */
    public static function obtenerResultado(string $idempotencyKey): ?array
    {
        $hash = hash('sha256', $idempotencyKey);
        $key = self::where('key_hash', $hash)->first();
        
        return $key ? $key->resultado : null;
    }

    // Relaciones
    public function creditoPrendario()
    {
        return $this->belongsTo(CreditoPrendario::class, 'credito_prendario_id');
    }

    public function movimiento()
    {
        return $this->belongsTo(CreditoMovimiento::class, 'movimiento_id');
    }
}
