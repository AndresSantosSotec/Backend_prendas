<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Boveda;
use App\Models\User;
use App\Models\Sucursal;
use App\Traits\Auditable;

class BovedaMovimiento extends Model
{
    use HasFactory, SoftDeletes, Auditable;

    protected string $auditoriaModulo = 'boveda';
    public static bool $auditarDeshabilitado = false;

    protected $table = 'boveda_movimientos';

    protected $fillable = [
        'boveda_id',
        'usuario_id',
        'sucursal_id',
        'tipo_movimiento',
        'monto',
        'concepto',
        'desglose_denominaciones',
        'boveda_destino_id',
        'referencia',
        'estado',
        'aprobado_por',
        'fecha_aprobacion',
        'motivo_rechazo',
    ];

    protected $casts = [
        'monto' => 'decimal:2',
        'desglose_denominaciones' => 'array',
        'fecha_aprobacion' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relaciones
    public function boveda()
    {
        return $this->belongsTo(Boveda::class);
    }

    public function bovedaDestino()
    {
        return $this->belongsTo(Boveda::class, 'boveda_destino_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class);
    }

    public function aprobador()
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * Detalles de denominaciones del movimiento
     */
    public function detalles()
    {
        return $this->hasMany(BovedaDetalle::class, 'movimiento_id');
    }

    // Scopes
    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente');
    }

    public function scopeAprobados($query)
    {
        return $query->where('estado', 'aprobado');
    }

    public function scopeRechazados($query)
    {
        return $query->where('estado', 'rechazado');
    }

    public function scopeDeUsuario($query, $usuarioId)
    {
        return $query->where('usuario_id', $usuarioId);
    }

    public function scopeDeSucursal($query, $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopeEntradas($query)
    {
        // Considerar también ingresos por cierre diario como entradas
        return $query->whereIn('tipo_movimiento', [
            'entrada',
            'transferencia_entrada',
            'ingreso_cierre_diario',
        ]);
    }

    public function scopeSalidas($query)
    {
        return $query->whereIn('tipo_movimiento', ['salida', 'transferencia_salida']);
    }

    public function scopeTransferencias($query)
    {
        return $query->whereIn('tipo_movimiento', ['transferencia_entrada', 'transferencia_salida']);
    }

    // Métodos de negocio
    public function esTransferencia()
    {
        return in_array($this->tipo_movimiento, ['transferencia_entrada', 'transferencia_salida']);
    }

    public function esEntrada()
    {
        // Los ingresos por cierre diario se tratan contablemente como entradas
        return in_array($this->tipo_movimiento, [
            'entrada',
            'transferencia_entrada',
            'ingreso_cierre_diario',
        ]);
    }

    public function esSalida()
    {
        return in_array($this->tipo_movimiento, ['salida', 'transferencia_salida']);
    }

    public function puedeAprobar($usuario)
    {
        // Solo puede aprobar si está pendiente y el usuario tiene permisos
        if ($this->estado !== 'pendiente') {
            return false;
        }

        // El usuario que creó no puede aprobar su propio movimiento
        if ($this->usuario_id === $usuario->id) {
            return false;
        }

        // Verificar si el usuario tiene permisos de aprobación
        return $usuario->hasPermission('bovedas', 'aprobar') ||
               in_array($usuario->rol, ['administrador', 'gerente', 'supervisor']);
    }

    public function aprobar($usuario)
    {
        if (!$this->puedeAprobar($usuario)) {
            return false;
        }

        $this->estado = 'aprobado';
        $this->aprobado_por = $usuario->id;
        $this->fecha_aprobacion = now();
        $this->save();

        // Actualizar saldo de la bóveda
        $this->actualizarSaldoBoveda();

        return true;
    }

    public function rechazar($usuario, $motivo = null)
    {
        if (!$this->puedeAprobar($usuario)) {
            return false;
        }

        $this->estado = 'rechazado';
        $this->aprobado_por = $usuario->id;
        $this->fecha_aprobacion = now();
        $this->motivo_rechazo = $motivo;
        $this->save();

        return true;
    }

    private function actualizarSaldoBoveda()
    {
        $boveda = $this->boveda;

        if ($this->esEntrada()) {
            $boveda->saldo_actual += $this->monto;
        } elseif ($this->esSalida()) {
            $boveda->saldo_actual -= $this->monto;
        }

        $boveda->save();
    }

    public function getMontoFormateadoAttribute()
    {
        return 'Q' . number_format($this->monto, 2);
    }

    public function getTipoMovimientoLabelAttribute()
    {
        $labels = [
            'entrada' => 'Entrada',
            'salida' => 'Salida',
            'transferencia_entrada' => 'Transferencia Entrada',
            'transferencia_salida' => 'Transferencia Salida',
            'ingreso_cierre_diario' => 'Ingreso por Cierre Diario',
        ];

        return $labels[$this->tipo_movimiento] ?? $this->tipo_movimiento;
    }

    public function getEstadoLabelAttribute()
    {
        $labels = [
            'pendiente' => 'Pendiente',
            'aprobado' => 'Aprobado',
            'rechazado' => 'Rechazado',
        ];

        return $labels[$this->estado] ?? $this->estado;
    }
}
