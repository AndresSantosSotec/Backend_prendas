<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\Auditable;

class Apartado extends Model
{
    use HasFactory, Auditable;

    protected string $auditoriaModulo = 'apartados';
    public static bool $auditarDeshabilitado = false;

    protected $fillable = [
        'venta_id',
        'cliente_id',
        'cliente_nombre',
        'cliente_telefono',
        'total_apartado',
        'anticipo',
        'saldo_pendiente',
        'fecha_apartado',
        'fecha_limite',
        'fecha_completado',
        'fecha_cancelado',
        'estado',
        'observaciones',
        'motivo_cancelacion',
    ];

    protected $casts = [
        'total_apartado' => 'decimal:2',
        'anticipo' => 'decimal:2',
        'saldo_pendiente' => 'decimal:2',
        'fecha_apartado' => 'datetime',
        'fecha_limite' => 'date',
        'fecha_completado' => 'datetime',
        'fecha_cancelado' => 'datetime',
    ];

    // Relaciones
    public function venta()
    {
        return $this->belongsTo(Venta::class);
    }

    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    // Accessors
    public function getPorcentajePagadoAttribute()
    {
        if ($this->total_apartado == 0) return 0;
        return ($this->anticipo / $this->total_apartado) * 100;
    }

    public function getEstaVencidoAttribute()
    {
        return now()->gt($this->fecha_limite) && $this->estado === 'activo';
    }

    // Scopes
    public function scopeActivos($query)
    {
        return $query->where('estado', 'activo');
    }

    public function scopeVencidos($query)
    {
        return $query->where('estado', 'activo')
                    ->where('fecha_limite', '<', now());
    }

    public function scopeProximosVencer($query, $dias = 7)
    {
        return $query->where('estado', 'activo')
                    ->whereBetween('fecha_limite', [now(), now()->addDays($dias)]);
    }

    // Métodos
    public function completar()
    {
        $this->estado = 'completado';
        $this->fecha_completado = now();
        $this->saldo_pendiente = 0;
        $this->save();
    }

    public function cancelar($motivo = null)
    {
        $this->estado = 'cancelado';
        $this->fecha_cancelado = now();
        $this->motivo_cancelacion = $motivo;
        $this->save();
    }

    public function marcarVencido()
    {
        if ($this->esta_vencido) {
            $this->estado = 'vencido';
            $this->save();
        }
    }

    public function registrarPago($monto)
    {
        $this->anticipo += $monto;
        $this->saldo_pendiente = $this->total_apartado - $this->anticipo;

        if ($this->saldo_pendiente <= 0) {
            $this->completar();
        }

        $this->save();
    }
}
