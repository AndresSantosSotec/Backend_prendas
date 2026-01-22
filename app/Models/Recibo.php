<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Recibo extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'recibos';

    protected $fillable = [
        'numero_recibo',
        'tipo',
        'fecha',
        'serie',
        'cliente_id',
        'credito_id',
        'caja_id',
        'monto',
        'desglose_denominaciones',
        'concepto',
        'observaciones',
        'user_id',
        'sucursal_id',
        'estado',
        'fecha_anulacion',
        'motivo_anulacion',
        'anulado_por',
    ];

    protected $casts = [
        'fecha' => 'date',
        'monto' => 'decimal:2',
        'desglose_denominaciones' => 'array',
        'fecha_anulacion' => 'datetime',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    // Relaciones
    public function cliente()
    {
        return $this->belongsTo(Cliente::class);
    }

    public function credito()
    {
        return $this->belongsTo(CreditoPrendario::class, 'credito_id');
    }

    public function caja()
    {
        return $this->belongsTo(CajaAperturaCierre::class, 'caja_id');
    }

    public function usuario()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    public function anulador()
    {
        return $this->belongsTo(User::class, 'anulado_por');
    }

    // Scopes
    public function scopeIngresos($query)
    {
        return $query->where('tipo', 'ingreso');
    }

    public function scopeEgresos($query)
    {
        return $query->where('tipo', 'egreso');
    }

    public function scopeEmitidos($query)
    {
        return $query->where('estado', 'emitido');
    }

    public function scopeAnulados($query)
    {
        return $query->where('estado', 'anulado');
    }

    public function scopeDeSucursal($query, $sucursalId)
    {
        return $query->where('sucursal_id', $sucursalId);
    }

    public function scopeEnFecha($query, $fecha)
    {
        return $query->whereDate('fecha', $fecha);
    }

    public function scopeEnRango($query, $fechaInicio, $fechaFin)
    {
        return $query->whereBetween('fecha', [$fechaInicio, $fechaFin]);
    }

    // Métodos de negocio
    public static function generarNumeroRecibo($sucursalId, $serie = null)
    {
        $year = date('Y');
        $prefix = $serie ?? 'R';

        // Obtener el último número del año actual para esta sucursal
        $ultimo = self::where('sucursal_id', $sucursalId)
            ->whereYear('created_at', $year)
            ->max('numero_recibo');

        if ($ultimo) {
            // Extraer el número del formato: R-2026-00001
            preg_match('/(\d+)$/', $ultimo, $matches);
            $numero = isset($matches[1]) ? intval($matches[1]) + 1 : 1;
        } else {
            $numero = 1;
        }

        return sprintf('%s-%s-%05d', $prefix, $year, $numero);
    }

    public function esIngreso()
    {
        return $this->tipo === 'ingreso';
    }

    public function esEgreso()
    {
        return $this->tipo === 'egreso';
    }

    public function estaEmitido()
    {
        return $this->estado === 'emitido';
    }

    public function estaAnulado()
    {
        return $this->estado === 'anulado';
    }

    public function puedeAnular()
    {
        return $this->estado === 'emitido';
    }

    public function anular($usuario, $motivo)
    {
        if (!$this->puedeAnular()) {
            return false;
        }

        $this->estado = 'anulado';
        $this->fecha_anulacion = now();
        $this->motivo_anulacion = $motivo;
        $this->anulado_por = $usuario->id;
        $this->save();

        return true;
    }

    // Accessors
    public function getMontoFormateadoAttribute()
    {
        return 'Q' . number_format($this->monto, 2);
    }

    public function getTipoLabelAttribute()
    {
        return $this->tipo === 'ingreso' ? 'Ingreso' : 'Egreso';
    }

    public function getEstadoLabelAttribute()
    {
        $labels = [
            'emitido' => 'Emitido',
            'anulado' => 'Anulado',
            'reimpreso' => 'Reimpreso',
        ];

        return $labels[$this->estado] ?? $this->estado;
    }
}
