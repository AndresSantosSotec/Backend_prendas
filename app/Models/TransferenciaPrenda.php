<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransferenciaPrenda extends Model
{
    use SoftDeletes;

    protected $table = 'transferencias_prendas';

    // Estados
    public const ESTADO_SOLICITADA = 'solicitada';
    public const ESTADO_AUTORIZADA = 'autorizada';
    public const ESTADO_EN_TRANSITO = 'en_transito';
    public const ESTADO_RECIBIDA = 'recibida';
    public const ESTADO_RECHAZADA = 'rechazada';
    public const ESTADO_CANCELADA = 'cancelada';

    protected $fillable = [
        'codigo_transferencia',
        'prenda_id',
        'credito_id',
        'sucursal_origen_id',
        'sucursal_destino_id',
        'usuario_solicita_id',
        'usuario_autoriza_id',
        'usuario_recibe_id',
        'estado',
        'motivo',
        'observaciones_autorizacion',
        'observaciones_recepcion',
        'motivo_rechazo',
        'fecha_solicitud',
        'fecha_autorizacion',
        'fecha_envio',
        'fecha_recepcion',
    ];

    protected $casts = [
        'fecha_solicitud' => 'datetime',
        'fecha_autorizacion' => 'datetime',
        'fecha_envio' => 'datetime',
        'fecha_recepcion' => 'datetime',
    ];

    /**
     * Boot: auto-generar código
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($transferencia) {
            if (empty($transferencia->codigo_transferencia)) {
                $transferencia->codigo_transferencia = self::generarCodigo();
            }
        });
    }

    /**
     * Generar código único: TRF-YYYYMMDD-XXXXXX
     */
    public static function generarCodigo(): string
    {
        $fecha = now()->format('Ymd');
        $ultimo = self::withTrashed()
            ->where('codigo_transferencia', 'like', "TRF-{$fecha}-%")
            ->orderByDesc('id')
            ->value('codigo_transferencia');

        $secuencial = 1;
        if ($ultimo) {
            $partes = explode('-', $ultimo);
            $secuencial = intval(end($partes)) + 1;
        }

        return sprintf("TRF-%s-%06d", $fecha, $secuencial);
    }

    // ===== RELACIONES =====

    public function prenda(): BelongsTo
    {
        return $this->belongsTo(Prenda::class, 'prenda_id');
    }

    public function credito(): BelongsTo
    {
        return $this->belongsTo(CreditoPrendario::class, 'credito_id');
    }

    public function sucursalOrigen(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_origen_id');
    }

    public function sucursalDestino(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class, 'sucursal_destino_id');
    }

    public function usuarioSolicita(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_solicita_id');
    }

    public function usuarioAutoriza(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_autoriza_id');
    }

    public function usuarioRecibe(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_recibe_id');
    }

    // ===== SCOPES =====

    public function scopePendientes($query)
    {
        return $query->whereIn('estado', [self::ESTADO_SOLICITADA, self::ESTADO_AUTORIZADA, self::ESTADO_EN_TRANSITO]);
    }

    public function scopeCompletadas($query)
    {
        return $query->where('estado', self::ESTADO_RECIBIDA);
    }

    public function scopeDeOrigen($query, $sucursalId)
    {
        return $query->where('sucursal_origen_id', $sucursalId);
    }

    public function scopeDeDestino($query, $sucursalId)
    {
        return $query->where('sucursal_destino_id', $sucursalId);
    }
}
