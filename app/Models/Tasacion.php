<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tasacion extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'tasaciones';

    protected $fillable = [
        'prenda_id',
        'tasador_id',
        'credito_prendario_id',
        'numero_tasacion',
        'fecha_tasacion',
        'valor_mercado',
        'valor_comercial',
        'valor_liquidacion',
        'valor_final_asignado',
        'porcentaje_depreciacion',
        'condicion_fisica',
        'antiguedad_estimada',
        'tiene_accesorios',
        'tiene_documentos',
        'tiene_caja_original',
        'funciona_correctamente',
        'descripcion_detallada',
        'defectos_encontrados',
        'caracteristicas_positivas',
        'observaciones',
        'metodo_tasacion',
        'referencias_mercado',
        'estado',
        'aprobado_por',
        'fecha_aprobacion',
        'motivo_rechazo',
        'es_retasacion',
        'tasacion_anterior_id',
        'motivo_retasacion',
        'documentos_soporte',
        'fotos_tasacion',
    ];

    protected $casts = [
        'fecha_tasacion' => 'date',
        'fecha_aprobacion' => 'date',
        'valor_mercado' => 'decimal:2',
        'valor_comercial' => 'decimal:2',
        'valor_liquidacion' => 'decimal:2',
        'valor_final_asignado' => 'decimal:2',
        'porcentaje_depreciacion' => 'decimal:2',
        'tiene_accesorios' => 'boolean',
        'tiene_documentos' => 'boolean',
        'tiene_caja_original' => 'boolean',
        'funciona_correctamente' => 'boolean',
        'es_retasacion' => 'boolean',
        'referencias_mercado' => 'array',
        'documentos_soporte' => 'array',
        'fotos_tasacion' => 'array',
    ];

    // Relaciones
    public function prenda()
    {
        return $this->belongsTo(Prenda::class, 'prenda_id');
    }

    public function tasador()
    {
        return $this->belongsTo(User::class, 'tasador_id');
    }

    public function creditoPrendario()
    {
        return $this->belongsTo(CreditoPrendario::class, 'credito_prendario_id');
    }

    public function aprobadoPor()
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }

    public function tasacionAnterior()
    {
        return $this->belongsTo(Tasacion::class, 'tasacion_anterior_id');
    }

    public function retasaciones()
    {
        return $this->hasMany(Tasacion::class, 'tasacion_anterior_id');
    }

    // Scopes
    public function scopeAprobadas($query)
    {
        return $query->where('estado', 'aprobada');
    }

    public function scopePendientes($query)
    {
        return $query->where('estado', 'pendiente_revision');
    }

    public function scopePorTasador($query, $tasadorId)
    {
        return $query->where('tasador_id', $tasadorId);
    }

    // Métodos auxiliares
    public function puedeAprobarse()
    {
        return in_array($this->estado, ['pendiente_revision', 'modificada']);
    }

    public function requiereRevision()
    {
        return $this->estado === 'pendiente_revision';
    }
}
