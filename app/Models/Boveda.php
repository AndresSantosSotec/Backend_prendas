<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Sucursal;
use App\Models\User;
use App\Models\BovedaMovimiento;

class Boveda extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'bovedas';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'sucursal_id',
        'saldo_actual',
        'saldo_minimo',
        'saldo_maximo',
        'tipo',
        'activa',
        'requiere_aprobacion',
        'responsable_id',
        'creado_por',
        'ultima_apertura',
        'ultimo_cierre',
    ];

    protected $casts = [
        'saldo_actual' => 'decimal:2',
        'saldo_minimo' => 'decimal:2',
        'saldo_maximo' => 'decimal:2',
        'activa' => 'boolean',
        'requiere_aprobacion' => 'boolean',
        'ultima_apertura' => 'datetime',
        'ultimo_cierre' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relaciones
    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function responsable()
    {
        return $this->belongsTo(User::class, 'responsable_id');
    }

    public function creador()
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function movimientos()
    {
        return $this->hasMany(BovedaMovimiento::class);
    }

    public function movimientosAprobados()
    {
        return $this->hasMany(BovedaMovimiento::class)->where('estado', 'aprobado');
    }

    // Scopes
    public function scopeActivas($query)
    {
        return $query->where('activa', true);
    }

    public function scopeDeSucursal($query, $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopePrincipales($query)
    {
        return $query->where('tipo', 'principal');
    }

    public function scopeGenerales($query)
    {
        return $query->where('tipo', 'general');
    }

    // Métodos de negocio
    public function puedeRecibirMonto($monto)
    {
        if ($this->saldo_maximo && $this->saldo_actual + $monto > $this->saldo_maximo) {
            return false;
        }
        return true;
    }

    public function puedeRetirarMonto($monto)
    {
        return $this->saldo_actual - $monto >= $this->saldo_minimo;
    }

    public function estaAbierta()
    {
        // Una bóveda está abierta si tiene movimientos hoy y no ha sido cerrada
        $hoy = now()->format('Y-m-d');
        return $this->movimientos()
            ->whereDate('created_at', $hoy)
            ->where('tipo_movimiento', '!=', 'transferencia_salida')
            ->exists();
    }

    public function getSaldoFormateadoAttribute()
    {
        return 'Q' . number_format($this->saldo_actual, 2);
    }

    public function getEstadoAttribute()
    {
        return $this->activa ? 'Activa' : 'Inactiva';
    }

    // Métodos estáticos
    public static function generarCodigo($sucursalId)
    {
        $sucursal = Sucursal::find($sucursalId);
        $prefijo = $sucursal ? substr(strtoupper($sucursal->nombre), 0, 3) : 'SUC';
        $secuencia = self::where('sucursal_id', $sucursalId)->count() + 1;
        return $prefijo . '-BOV-' . str_pad($secuencia, 3, '0', STR_PAD_LEFT);
    }

    /**
     * Calcular saldo basado en movimientos aprobados
     * Fórmula: SUM(entradas + iniciales) - SUM(salidas)
     */
    public function calcularSaldo()
    {
        $saldo = $this->movimientos()
            ->where('estado', 'aprobado')
            ->selectRaw("
                COALESCE(SUM(
                    CASE
                        WHEN tipo_movimiento IN ('entrada', 'transferencia_entrada') THEN monto
                        WHEN tipo_movimiento IN ('salida', 'transferencia_salida') THEN -monto
                        ELSE 0
                    END
                ), 0) as saldo
            ")
            ->value('saldo');

        return floatval($saldo);
    }

    /**
     * Actualizar saldo_actual basándose en los movimientos
     */
    public function actualizarSaldo()
    {
        $this->saldo_actual = $this->calcularSaldo();
        $this->save();
        return $this->saldo_actual;
    }

    /**
     * Obtener el desglose consolidado de denominaciones
     */
    public function getDesgloseDenominaciones()
    {
        $movimientos = $this->movimientos()
            ->where('estado', 'aprobado')
            ->whereNotNull('desglose_denominaciones')
            ->get();

        $desglose = [];
        foreach ($movimientos as $mov) {
            $denominaciones = $mov->desglose_denominaciones;
            if (!is_array($denominaciones)) continue;

            $multiplicador = $mov->esEntrada() ? 1 : -1;

            foreach ($denominaciones as $valor => $cantidad) {
                if (!isset($desglose[$valor])) {
                    $desglose[$valor] = 0;
                }
                $desglose[$valor] += ($cantidad * $multiplicador);
            }
        }

        // Filtrar valores negativos (no debería haber pero por seguridad)
        return array_filter($desglose, fn($v) => $v > 0);
    }
}
