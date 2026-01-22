<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BovedaDetalle extends Model
{
    use HasFactory;

    protected $table = 'boveda_detalles';

    protected $fillable = [
        'movimiento_id',
        'denominacion_id',
        'cantidad',
        'valor_denominacion',
        'subtotal',
    ];

    protected $casts = [
        'cantidad' => 'integer',
        'valor_denominacion' => 'decimal:2',
        'subtotal' => 'decimal:2',
    ];

    // Relaciones
    public function movimiento()
    {
        return $this->belongsTo(BovedaMovimiento::class, 'movimiento_id');
    }

    public function denominacion()
    {
        return $this->belongsTo(Denominacion::class);
    }

    // Métodos de negocio
    /**
     * Calcular y guardar el subtotal
     */
    public function calcularSubtotal()
    {
        $this->subtotal = $this->cantidad * $this->valor_denominacion;
        return $this->subtotal;
    }

    /**
     * Crear detalle desde un array de conteo
     */
    public static function crearDesdeDesglose(int $movimientoId, array $desglose)
    {
        $detalles = [];

        foreach ($desglose as $valor => $cantidad) {
            if ($cantidad <= 0) continue;

            // Buscar la denominación por valor
            $denominacion = Denominacion::where('valor', $valor)->first();

            if ($denominacion) {
                $detalles[] = self::create([
                    'movimiento_id' => $movimientoId,
                    'denominacion_id' => $denominacion->id,
                    'cantidad' => $cantidad,
                    'valor_denominacion' => $denominacion->valor,
                    'subtotal' => $cantidad * $denominacion->valor,
                ]);
            }
        }

        return $detalles;
    }
}
