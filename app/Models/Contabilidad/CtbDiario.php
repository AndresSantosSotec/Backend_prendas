<?php

namespace App\Models\Contabilidad;

use App\Models\CreditoPrendario;
use App\Models\CreditoMovimiento;
use App\Models\Venta;
use App\Models\CajaAperturaCierre;
use App\Models\Sucursal;
use App\Models\Moneda;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CtbDiario extends Model
{
    use SoftDeletes;

    protected $table = 'ctb_diario';

    protected $fillable = [
        'numero_comprobante',
        'tipo_poliza_id',
        'moneda_id',
        'tipo_origen',
        'credito_prendario_id',
        'movimiento_credito_id',
        'venta_id',
        'caja_id',
        'numero_documento',
        'glosa',
        'fecha_documento',
        'fecha_contabilizacion',
        'sucursal_id',
        'usuario_id',
        'estado',
        'editable',
        'aprobado_por',
        'fecha_aprobacion',
        'anulado_por',
        'fecha_anulacion',
        'motivo_anulacion',
    ];

    protected $casts = [
        'fecha_documento' => 'date',
        'fecha_contabilizacion' => 'date',
        'fecha_aprobacion' => 'datetime',
        'fecha_anulacion' => 'datetime',
        'editable' => 'boolean',
    ];

    /**
     * Tipo de póliza
     */
    public function tipoPoliza(): BelongsTo
    {
        return $this->belongsTo(CtbTipoPoliza::class, 'tipo_poliza_id');
    }

    /**
     * Moneda
     */
    public function moneda(): BelongsTo
    {
        return $this->belongsTo(Moneda::class);
    }

    /**
     * Crédito prendario relacionado
     */
    public function creditoPrendario(): BelongsTo
    {
        return $this->belongsTo(CreditoPrendario::class);
    }

    /**
     * Movimiento de crédito relacionado
     */
    public function movimientoCredito(): BelongsTo
    {
        return $this->belongsTo(CreditoMovimiento::class, 'movimiento_credito_id');
    }

    /**
     * Venta relacionada
     */
    public function venta(): BelongsTo
    {
        return $this->belongsTo(Venta::class);
    }

    /**
     * Caja relacionada
     */
    public function caja(): BelongsTo
    {
        return $this->belongsTo(CajaAperturaCierre::class, 'caja_id');
    }

    /**
     * Sucursal
     */
    public function sucursal(): BelongsTo
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * Usuario que registró
     */
    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Usuario que aprobó
     */
    public function usuarioAprobador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    /**
     * Usuario que anuló
     */
    public function usuarioAnulador(): BelongsTo
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    /**
     * Movimientos contables (debe/haber)
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(CtbMovimiento::class, 'diario_id');
    }

    /**
     * Scope para asientos registrados
     */
    public function scopeRegistrados($query)
    {
        return $query->where('estado', 'registrado');
    }

    /**
     * Scope para asientos aprobados
     */
    public function scopeAprobados($query)
    {
        return $query->where('estado', 'aprobado');
    }

    /**
     * Scope por rango de fechas
     */
    public function scopeEntreFechas($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha_contabilizacion', [$fechaInicio, $fechaFin]);
    }

    /**
     * Scope por tipo de origen
     */
    public function scopePorOrigen($query, $tipo)
    {
        return $query->where('tipo_origen', $tipo);
    }

    /**
     * Scope por sucursal
     */
    public function scopePorSucursal($query, $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    /**
     * Validar que el asiento cuadre (debe = haber)
     */
    public function validarCuadre()
    {
        $totalDebe = $this->movimientos()->sum('debe');
        $totalHaber = $this->movimientos()->sum('haber');

        $diferencia = abs($totalDebe - $totalHaber);

        return $diferencia < 0.01; // Tolerancia de 1 centavo
    }

    /**
     * Obtener total debe
     */
    public function getTotalDebeAttribute()
    {
        return $this->movimientos()->sum('debe');
    }

    /**
     * Obtener total haber
     */
    public function getTotalHaberAttribute()
    {
        return $this->movimientos()->sum('haber');
    }

    /**
     * Aprobar asiento
     */
    public function aprobar($usuarioId)
    {
        if (!$this->validarCuadre()) {
            throw new \Exception('El asiento no cuadra. Debe = Haber');
        }

        $this->estado = 'aprobado';
        $this->aprobado_por = $usuarioId;
        $this->fecha_aprobacion = now();
        $this->editable = false;
        $this->save();
    }

    /**
     * Anular asiento
     */
    public function anular($usuarioId, $motivo)
    {
        $this->estado = 'anulado';
        $this->anulado_por = $usuarioId;
        $this->fecha_anulacion = now();
        $this->motivo_anulacion = $motivo;
        $this->editable = false;
        $this->save();
    }

    /**
     * Generar número de comprobante
     */
    public static function generarNumeroComprobante($tipoCodigo, $sucursalId = null)
    {
        $anio = date('Y');
        $prefijo = "{$tipoCodigo}-{$anio}";

        if ($sucursalId) {
            $prefijo .= "-S{$sucursalId}";
        }

        // Obtener el último número
        $ultimo = static::where('numero_comprobante', 'like', "{$prefijo}-%")
            ->orderBy('numero_comprobante', 'desc')
            ->value('numero_comprobante');

        if ($ultimo) {
            $ultimoNumero = intval(substr($ultimo, strrpos($ultimo, '-') + 1));
            $nuevoNumero = $ultimoNumero + 1;
        } else {
            $nuevoNumero = 1;
        }

        return "{$prefijo}-" . str_pad($nuevoNumero, 6, '0', STR_PAD_LEFT);
    }
}
