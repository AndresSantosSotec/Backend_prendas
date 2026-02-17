<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MigracionDatosLog extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'migracion_datos_logs';

    protected $fillable = [
        'codigo_lote',
        'usuario_id',
        'tabla_destino',
        'archivo_original',
        'archivo_ruta',
        'estado',
        'total_filas',
        'filas_insertadas',
        'filas_actualizadas',
        'filas_con_error',
        'filas_con_advertencia',
        'errores',
        'advertencias',
        'resumen',
        'mapeo_columnas',
        'fecha_inicio',
        'fecha_fin',
        'tiempo_ejecucion_ms',
        'observaciones',
    ];

    protected $casts = [
        'errores' => 'array',
        'advertencias' => 'array',
        'resumen' => 'array',
        'mapeo_columnas' => 'array',
        'fecha_inicio' => 'datetime',
        'fecha_fin' => 'datetime',
    ];

    // ── Relaciones ──────────────────────────────────────────────

    public function usuario()
    {
        return $this->belongsTo(\App\Models\User::class, 'usuario_id');
    }

    // ── Scopes ──────────────────────────────────────────────────

    public function scopeDeTabla($query, string $tabla)
    {
        return $query->where('tabla_destino', $tabla);
    }

    public function scopeEstado($query, string $estado)
    {
        return $query->where('estado', $estado);
    }

    // ── Helpers ─────────────────────────────────────────────────

    public function puedeRevertirse(): bool
    {
        return in_array($this->estado, ['completado', 'completado_parcial']);
    }

    public function estaEnProceso(): bool
    {
        return in_array($this->estado, ['validando', 'importando']);
    }

    public function getTasaExitoAttribute(): float
    {
        if ($this->total_filas === 0) return 0;
        return round(($this->filas_insertadas / $this->total_filas) * 100, 2);
    }
}
