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
     * Formato: ORGDDMMYYAACORRELATIVO (16 dígitos sin guiones)
     * Ejemplo: 0102022601000001 = Org:01, 02/02/26, Agencia:01, Correlativo:000001
     */
    public static function generarCodigoCredito(int $sucursalId = 1): string
    {
        // 🏢 ORGANIZACIÓN: Código configurable desde .env (2 dígitos)
        $organizacion = str_pad(env('ORGANIZATION_CODE', '01'), 2, '0', STR_PAD_LEFT);

        // Fecha actual en formato DDMMYY (6 dígitos)
        $fecha = now()->format('dmy');

        // Agencia (2 dígitos)
        $agencia = str_pad($sucursalId, 2, '0', STR_PAD_LEFT);

        // 🔐 Buscar último correlativo continuo (sin reiniciar por día)
        // Buscamos el último crédito con formato de 16 dígitos
        $ultimoCredito = CreditoPrendario::withTrashed()
            ->whereNotNull('numero_credito')
            ->where('numero_credito', 'NOT LIKE', 'CR-%')
            ->whereRaw('LENGTH(numero_credito) = 16')
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->first();

        // También buscar en códigos prereservados
        $ultimoReservado = self::whereNotNull('codigo_credito')
            ->where('codigo_credito', 'NOT LIKE', 'CR-%')
            ->whereRaw('LENGTH(codigo_credito) = 16')
            ->orderBy('id', 'desc')
            ->lockForUpdate()
            ->first();

        // Obtener el correlativo más alto entre créditos y códigos reservados
        $correlativoCredito = 0;
        $correlativoReservado = 0;

        if ($ultimoCredito && $ultimoCredito->numero_credito) {
            $correlativoCredito = (int) substr($ultimoCredito->numero_credito, -6);
        }

        if ($ultimoReservado && $ultimoReservado->codigo_credito) {
            $correlativoReservado = (int) substr($ultimoReservado->codigo_credito, -6);
        }

        // Asegurar que si los registros anteriores tenían correlativo reiniciado diariamente (menor a total emitido),
        // arranque a partir del conteo total histórico de créditos para no chocar ni retroceder
        $totalHistorico = CreditoPrendario::withTrashed()->count();

        $ultimoCorrelativo = max($correlativoCredito, $correlativoReservado, $totalHistorico);
        $correlativo = $ultimoCorrelativo + 1;

        // Formato final: ORGDDMMYYAACORRELATIVO (16 dígitos)
        $codigo = $organizacion . $fecha . $agencia . str_pad($correlativo, 6, '0', STR_PAD_LEFT);

        // ✅ Verificar que no exista (doble verificación de colisión)
        $existe = self::where('codigo_credito', $codigo)->exists() ||
                  CreditoPrendario::withTrashed()->where('numero_credito', $codigo)->exists();

        while ($existe) {
            $correlativo++;
            $codigo = $organizacion . $fecha . $agencia . str_pad($correlativo, 6, '0', STR_PAD_LEFT);
            $existe = self::where('codigo_credito', $codigo)->exists() ||
                      CreditoPrendario::withTrashed()->where('numero_credito', $codigo)->exists();
        }

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
        int $sucursalId = 1,
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

        // Crear nueva reserva con nuevo formato de código
        return self::create([
            'codigo_credito' => self::generarCodigoCredito($sucursalId),
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
