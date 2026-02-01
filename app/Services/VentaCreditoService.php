<?php

namespace App\Services;

use App\Models\Venta;
use App\Models\VentaPago;
use App\Models\MetodoPago;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Servicio para gestionar ventas a crédito, apartados y plan de pagos
 * Maneja enganche, abonos y liquidación
 */
class VentaCreditoService
{
    /**
     * Registrar pago/abono a una venta a crédito
     *
     * @param Venta $venta
     * @param array $datos {
     *   metodo: string (efectivo|tarjeta|transferencia|cheque),
     *   monto: float,
     *   referencia: string,
     *   banco: string,
     *   observaciones: string
     * }
     * @return array
     */
    public function registrarAbono(Venta $venta, array $datos): array
    {
        return DB::transaction(function () use ($venta, $datos) {
            // 1. Validar que la venta permita pagos
            $this->validarVentaParaPago($venta);

            $monto = (float) $datos['monto'];

            // 2. Validar que el monto no exceda el saldo pendiente
            if ($monto > $venta->saldo_pendiente) {
                throw new \Exception("El monto Q{$monto} excede el saldo pendiente Q{$venta->saldo_pendiente}");
            }

            if ($monto <= 0) {
                throw new \Exception("El monto debe ser mayor a cero");
            }

            // 3. Obtener método de pago ID
            $metodoCodigo = $datos['metodo'] ?? 'efectivo';
            $metodoPago = MetodoPago::where('codigo', $metodoCodigo)->first();

            if (!$metodoPago) {
                $metodoPago = MetodoPago::where('codigo', 'efectivo')->first();
            }

            // 4. Registrar el pago
            $pago = VentaPago::create([
                'venta_id' => $venta->id,
                'metodo_pago_id' => $metodoPago->id,
                'monto' => $monto,
                'referencia' => $datos['referencia'] ?? null,
                'banco' => $datos['banco'] ?? null,
                'observaciones' => $datos['observaciones'] ?? 'Abono a venta ' . $venta->codigo_venta,
            ]);

            // 5. Actualizar totales de la venta
            $nuevoTotalPagado = $venta->total_pagado + $monto;
            $nuevoSaldoPendiente = $venta->total_final - $nuevoTotalPagado;

            $venta->total_pagado = $nuevoTotalPagado;
            $venta->saldo_pendiente = max(0, $nuevoSaldoPendiente);

            // 6. Determinar nuevo estado
            $nuevoEstado = $this->determinarEstadoVenta($venta);
            $venta->estado = $nuevoEstado;

            // 7. Si se liquidó, registrar fecha y actualizar prendas
            if ($nuevoEstado === 'pagada') {
                $venta->fecha_liquidacion = now();
                $this->marcarPrendasComoVendidas($venta);
            }

            // 8. Actualizar cuotas pagadas si es plan de pagos
            if ($venta->tipo_venta === 'plan_pagos' && $venta->monto_cuota > 0) {
                $venta->cuotas_pagadas = floor($nuevoTotalPagado / $venta->monto_cuota);

                // Calcular fecha próximo pago
                if ($venta->cuotas_pagadas < $venta->numero_cuotas) {
                    $venta->fecha_proximo_pago = $this->calcularProximaFechaPago($venta);
                }
            }

            $venta->save();

            Log::info("Abono registrado a venta {$venta->codigo_venta}", [
                'pago_id' => $pago->id,
                'monto' => $monto,
                'saldo_nuevo' => $nuevoSaldoPendiente,
                'estado_nuevo' => $nuevoEstado
            ]);

            return [
                'pago' => $pago,
                'venta' => $venta->fresh(['pagos', 'detalles.prenda']),
                'saldo_anterior' => $venta->saldo_pendiente + $monto,
                'saldo_nuevo' => $nuevoSaldoPendiente,
                'liquidada' => $nuevoEstado === 'pagada'
            ];
        });
    }

    /**
     * Configurar venta como apartado con enganche
     */
    public function configurarApartado(Venta $venta, array $config): Venta
    {
        return DB::transaction(function () use ($venta, $config) {
            $venta->update([
                'tipo_venta' => 'apartado',
                'enganche' => $config['enganche'] ?? 0,
                'plazo_dias' => $config['plazo_dias'] ?? 30,
                'fecha_vencimiento' => now()->addDays($config['plazo_dias'] ?? 30),
                'saldo_pendiente' => $venta->total_final - ($config['enganche'] ?? 0),
                'total_pagado' => $config['enganche'] ?? 0,
                'estado' => 'apartado',
            ]);

            return $venta->fresh();
        });
    }

    /**
     * Configurar venta como plan de pagos
     */
    public function configurarPlanPagos(Venta $venta, array $config): Venta
    {
        return DB::transaction(function () use ($venta, $config) {
            $numeroCuotas = $config['numero_cuotas'] ?? 1;
            $enganche = $config['enganche'] ?? 0;
            $tasaInteres = $config['tasa_interes'] ?? 0;

            // Calcular saldo a financiar
            $saldoFinanciar = $venta->total_final - $enganche;

            // Calcular intereses
            $intereses = ($saldoFinanciar * ($tasaInteres / 100));
            $totalConIntereses = $saldoFinanciar + $intereses;

            // Calcular cuota
            $montoCuota = $totalConIntereses / $numeroCuotas;

            $venta->update([
                'tipo_venta' => 'plan_pagos',
                'enganche' => $enganche,
                'total_pagado' => $enganche,
                'saldo_pendiente' => $totalConIntereses,
                'numero_cuotas' => $numeroCuotas,
                'monto_cuota' => $montoCuota,
                'frecuencia_pago' => $config['frecuencia_pago'] ?? 'mensual',
                'tasa_interes' => $tasaInteres,
                'intereses' => $intereses,
                'cuotas_pagadas' => 0,
                'fecha_proximo_pago' => $this->calcularPrimeraFechaPago($config['frecuencia_pago'] ?? 'mensual'),
                'fecha_vencimiento' => $this->calcularFechaVencimiento($numeroCuotas, $config['frecuencia_pago'] ?? 'mensual'),
                'estado' => 'plan_pagos',
            ]);

            return $venta->fresh();
        });
    }

    /**
     * Validar que la venta pueda recibir pagos
     */
    private function validarVentaParaPago(Venta $venta): void
    {
        if (!in_array($venta->tipo_venta, ['apartado', 'plan_pagos'])) {
            throw new \Exception("Solo se pueden registrar abonos a ventas de tipo apartado o plan de pagos");
        }

        if ($venta->estado === 'pagada') {
            throw new \Exception("Esta venta ya está completamente pagada");
        }

        if ($venta->estado === 'cancelada') {
            throw new \Exception("No se pueden registrar pagos a ventas canceladas");
        }

        if ($venta->saldo_pendiente <= 0) {
            throw new \Exception("Esta venta no tiene saldo pendiente");
        }
    }

    /**
     * Determinar estado de venta según saldo
     */
    private function determinarEstadoVenta(Venta $venta): string
    {
        if ($venta->saldo_pendiente <= 0) {
            return 'pagada';
        }

        return $venta->tipo_venta === 'apartado' ? 'apartado' : 'plan_pagos';
    }

    /**
     * Marcar prendas como vendidas cuando se liquida
     */
    private function marcarPrendasComoVendidas(Venta $venta): void
    {
        foreach ($venta->detalles as $detalle) {
            if ($detalle->prenda && $detalle->prenda->estado !== 'vendida') {
                $detalle->prenda->update([
                    'estado' => 'vendida',
                    'fecha_venta' => now()
                ]);
            }
        }
    }

    /**
     * Calcular próxima fecha de pago según frecuencia
     */
    private function calcularProximaFechaPago(Venta $venta): Carbon
    {
        $ultimaFecha = $venta->fecha_proximo_pago ?? $venta->fecha_venta;

        return match($venta->frecuencia_pago) {
            'semanal' => Carbon::parse($ultimaFecha)->addWeek(),
            'quincenal' => Carbon::parse($ultimaFecha)->addWeeks(2),
            'mensual' => Carbon::parse($ultimaFecha)->addMonth(),
            default => Carbon::parse($ultimaFecha)->addMonth(),
        };
    }

    /**
     * Calcular primera fecha de pago
     */
    private function calcularPrimeraFechaPago(string $frecuencia): Carbon
    {
        return match($frecuencia) {
            'semanal' => now()->addWeek(),
            'quincenal' => now()->addWeeks(2),
            'mensual' => now()->addMonth(),
            default => now()->addMonth(),
        };
    }

    /**
     * Calcular fecha de vencimiento final
     */
    private function calcularFechaVencimiento(int $numeroCuotas, string $frecuencia): Carbon
    {
        $multiplicador = match($frecuencia) {
            'semanal' => $numeroCuotas,
            'quincenal' => $numeroCuotas * 2,
            'mensual' => $numeroCuotas * 4,
            default => $numeroCuotas * 4,
        };

        return now()->addWeeks($multiplicador);
    }

    /**
     * Obtener resumen de pagos de una venta
     */
    public function obtenerResumenPagos(Venta $venta): array
    {
        $pagos = $venta->pagos()->with('metodoPago')->get();

        return [
            'total_final' => $venta->total_final,
            'enganche' => $venta->enganche,
            'intereses' => $venta->intereses,
            'total_con_intereses' => $venta->total_final + $venta->intereses,
            'total_pagado' => $venta->total_pagado,
            'saldo_pendiente' => $venta->saldo_pendiente,
            'numero_cuotas' => $venta->numero_cuotas,
            'monto_cuota' => $venta->monto_cuota,
            'cuotas_pagadas' => $venta->cuotas_pagadas,
            'cuotas_pendientes' => $venta->numero_cuotas - $venta->cuotas_pagadas,
            'fecha_proximo_pago' => $venta->fecha_proximo_pago,
            'fecha_vencimiento' => $venta->fecha_vencimiento,
            'dias_vencimiento' => $venta->fecha_vencimiento ? now()->diffInDays($venta->fecha_vencimiento, false) : null,
            'esta_vencida' => $venta->fecha_vencimiento ? now()->isAfter($venta->fecha_vencimiento) : false,
            'pagos' => $pagos->map(function($pago) {
                return [
                    'id' => $pago->id,
                    'fecha' => $pago->created_at,
                    'monto' => $pago->monto,
                    'metodo' => $pago->metodoPago->nombre ?? 'Desconocido',
                    'referencia' => $pago->referencia,
                    'observaciones' => $pago->observaciones,
                ];
            })
        ];
    }
}
