<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ParametrizacionMora extends Model
{
    use HasFactory;

    protected $table = 'parametrizacion_mora';

    protected $fillable = [
        'sucursal_id',
        'lunes',
        'martes',
        'miercoles',
        'jueves',
        'viernes',
        'sabado',
        'domingo',
        'max_dias_mora',
        'aplicar_tope_mora',
        'dias_tope_mora',
        'aplicar_mora_completa',
        'dias_para_mora_completa',
        'activo',
        'notas',
    ];

    protected $casts = [
        'lunes' => 'boolean',
        'martes' => 'boolean',
        'miercoles' => 'boolean',
        'jueves' => 'boolean',
        'viernes' => 'boolean',
        'sabado' => 'boolean',
        'domingo' => 'boolean',
        'aplicar_tope_mora' => 'boolean',
        'aplicar_mora_completa' => 'boolean',
        'activo' => 'boolean',
        'max_dias_mora' => 'integer',
        'dias_tope_mora' => 'integer',
        'dias_para_mora_completa' => 'integer',
    ];

    public function sucursal()
    {
        return $this->belongsTo(Sucursal::class);
    }

    /**
     * Obtener la configuración vigente para una sucursal (o la global si no hay sucursal).
     */
    public static function obtenerConfiguracion(?string $sucursalId = null): ?self
    {
        // Primero buscar configuración de la sucursal específica
        if ($sucursalId) {
            $config = static::where('sucursal_id', $sucursalId)->where('activo', true)->first();
            if ($config) {
                return $config;
            }
        }

        // Fallback: configuración global (sin sucursal)
        return static::whereNull('sucursal_id')->where('activo', true)->first();
    }

    /**
     * Devuelve un array con los números de día de la semana que son laborales.
     * Carbon: 0=domingo, 1=lunes, ..., 6=sábado
     */
    public function diasLaboralesArray(): array
    {
        $dias = [];
        if ($this->domingo)   $dias[] = 0;
        if ($this->lunes)     $dias[] = 1;
        if ($this->martes)    $dias[] = 2;
        if ($this->miercoles) $dias[] = 3;
        if ($this->jueves)    $dias[] = 4;
        if ($this->viernes)   $dias[] = 5;
        if ($this->sabado)    $dias[] = 6;
        return $dias;
    }
}
