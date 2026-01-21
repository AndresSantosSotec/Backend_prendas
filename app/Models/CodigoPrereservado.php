<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CodigoPrereservado extends Model
{
    use HasUuids;

    protected $table = 'codigos_prereservados';

    protected $fillable = [
        'codigo_credito',
        'codigo_prenda',
        'session_token',
        'usuario_id',
        'cliente_id',
        'estado',
        'fecha_expiracion',
    ];

    protected $casts = [
        'fecha_expiracion' => 'datetime',
        'usuario_id' => 'integer',
    ];

    /**
     * Relación con el usuario
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Relación con el cliente
     */
    public function cliente(): BelongsTo
    {
        return $this->belongsTo(Cliente::class, 'cliente_id');
    }

    /**
     * Generar un código de crédito único
     */
    public static function generarCodigoCredito(): string
    {
        do {
            $fecha = now();
            $año = $fecha->format('y');
            $mes = $fecha->format('m');
            $random = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $codigo = "CR-{$año}{$mes}{$random}";
        } while (
            self::where('codigo_credito', $codigo)->exists() ||
            CreditoPrendario::where('numero_credito', $codigo)->exists()
        );

        return $codigo;
    }

    /**
     * Generar un código de prenda único
     */
    public static function generarCodigoPrenda(): string
    {
        do {
            $fecha = now();
            $año = $fecha->format('y');
            $mes = $fecha->format('m');
            $random = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT);
            $codigo = "PRN-{$año}{$mes}{$random}";
        } while (
            self::where('codigo_prenda', $codigo)->exists() ||
            Prenda::where('codigo_prenda', $codigo)->exists()
        );

        return $codigo;
    }

    /**
     * Reservar códigos únicos para un usuario/sesión
     */
    public static function reservarCodigos(
        string $sessionToken,
        int|string|null $usuarioId = null,
        ?string $clienteId = null,
        int $horasExpiracion = 24
    ): self {
        // Primero, buscar si ya existe una reserva activa para este session_token
        $reservaExistente = self::where('session_token', $sessionToken)
            ->where('estado', 'reservado')
            ->where('fecha_expiracion', '>', now())
            ->first();

        if ($reservaExistente) {
            // Actualizar cliente_id si se proporciona
            if ($clienteId && $reservaExistente->cliente_id !== $clienteId) {
                $reservaExistente->update(['cliente_id' => $clienteId]);
            }
            return $reservaExistente;
        }

        // Crear nueva reserva
        return self::create([
            'codigo_credito' => self::generarCodigoCredito(),
            'codigo_prenda' => self::generarCodigoPrenda(),
            'session_token' => $sessionToken,
            'usuario_id' => $usuarioId,
            'cliente_id' => $clienteId,
            'estado' => 'reservado',
            'fecha_expiracion' => now()->addHours($horasExpiracion),
        ]);
    }

    /**
     * Marcar los códigos como usados (cuando se crea el crédito)
     */
    public function marcarComoUsado(): void
    {
        $this->update(['estado' => 'usado']);
    }

    /**
     * Limpiar códigos expirados (para ejecutar periódicamente)
     */
    public static function limpiarExpirados(): int
    {
        return self::where('estado', 'reservado')
            ->where('fecha_expiracion', '<', now())
            ->update(['estado' => 'expirado']);
    }

    /**
     * Buscar reserva por session token
     */
    public static function buscarPorSessionToken(string $sessionToken): ?self
    {
        return self::where('session_token', $sessionToken)
            ->where('estado', 'reservado')
            ->where('fecha_expiracion', '>', now())
            ->first();
    }
}
