<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class AuditoriaCredito extends Model
{
    use HasFactory;

    protected $table = 'auditoria_creditos';

    // Solo usar created_at, no updated_at
    const UPDATED_AT = null;

    protected $fillable = [
        'credito_prendario_id',
        'usuario_id',
        'accion',
        'campo_modificado',
        'valor_anterior',
        'valor_nuevo',
        'observaciones',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    // Relaciones
    public function creditoPrendario()
    {
        return $this->belongsTo(CreditoPrendario::class, 'credito_prendario_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Registrar una acción de auditoría
     */
    public static function registrar(
        int $creditoPrendarioId,
        string $accion,
        ?string $campoModificado = null,
        ?string $valorAnterior = null,
        ?string $valorNuevo = null,
        ?string $observaciones = null,
        ?int $usuarioId = null
    ): self {
        $usuarioId = $usuarioId ?? Auth::id();

        return self::create([
            'credito_prendario_id' => $creditoPrendarioId,
            'usuario_id' => $usuarioId,
            'accion' => $accion,
            'campo_modificado' => $campoModificado,
            'valor_anterior' => $valorAnterior,
            'valor_nuevo' => $valorNuevo,
            'observaciones' => $observaciones,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
        ]);
    }

    // Scopes
    public function scopePorCredito($query, $creditoId)
    {
        return $query->where('credito_prendario_id', $creditoId);
    }

    public function scopePorAccion($query, $accion)
    {
        return $query->where('accion', $accion);
    }

    public function scopePorUsuario($query, $usuarioId)
    {
        return $query->where('usuario_id', $usuarioId);
    }

    public function scopeRecientes($query)
    {
        return $query->orderBy('created_at', 'desc');
    }
}
