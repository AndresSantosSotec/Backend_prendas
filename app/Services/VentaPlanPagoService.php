<?php

namespace App\Services;

use App\Models\Venta;
use App\Models\VentaCredito;
use App\Models\VentaCreditoPlanPago;
use App\Models\VentaCreditoMovimiento;
use App\Models\Caja;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * Servicio para gestionar planes de pago de ventas a crédito
 * Reutiliza la arquitectura del módulo de créditos prendarios
 */
class VentaPlanPagoService
{
    protected ContabilidadAutomaticaService $contabilidadService;
    protected CajaService $cajaService;

    public function __construct(
        ContabilidadAutomaticaService $contabilidadService,
        CajaService $cajaService
    ) {
        $this->contabilidadService = $contabilidadService;
        $this->cajaService = $cajaService;
    }

    /**
     * Generar plan de pagos para una venta
     * Crea el registro en venta_creditos y genera cuotas en venta_credito_plan_pagos
     *
     * @param Venta $venta
     * @param array $config {
     *   enganche: float,
     *   numero_cuotas: int,
     *   tasa_interes: float (% mensual),
     *   frecuencia_pago: string (semanal|quincenal|mensual),
     *   fecha_primera_cuota: date|null,
     *   dias_gracia: int,
     *   tasa_mora: float,
     *   caja_id: int (para registrar enganche),
     *   observaciones: string
     * }
     * @return VentaCredito
     */
    public function generarPlan(Venta $venta, array $config): VentaCredito
    {
        return DB::transaction(function () use ($venta, $config) {
            // 1. Validaciones
            $this->validarVentaParaPlan($venta);

            // 2. Extraer configuración
            $enganche = (float) ($config['enganche'] ?? 0);
            $numeroCuotas = (int) ($config['numero_cuotas'] ?? 1);
            $tasaInteres = (float) ($config['tasa_interes'] ?? 0); // % mensual
            $frecuenciaPago = $config['frecuencia_pago'] ?? 'mensual';
            $fechaPrimeraCuota = isset($config['fecha_primera_cuota'])
                ? Carbon::parse($config['fecha_primera_cuota'])
                : null;
            $diasGracia = (int) ($config['dias_gracia'] ?? 0);
            $tasaMora = (float) ($config['tasa_mora'] ?? 2.0); // % mensual por defecto
            $cajaId = $config['caja_id'] ?? null;

            // 3. Validar enganche
            if ($enganche < 0 || $enganche >= $venta->total_final) {
                throw new Exception("El enganche debe ser entre 0 y el total de la venta");
            }

            // 4. Calcular montos
            $montoVenta = $venta->total_final;
            $saldoFinanciar = $montoVenta - $enganche;

            // Interés FLAT: Interés_total = Saldo × (Tasa/100)
            $interesTotal = $saldoFinanciar * ($tasaInteres / 100);
            $totalCredito = $saldoFinanciar + $interesTotal;

            // Monto de cada cuota (fijo)
            $montoCuota = $numeroCuotas > 0 ? round($totalCredito / $numeroCuotas, 2) : 0;

            // 5. Generar número de crédito único
            $numeroCredito = $this->generarNumeroCredito($venta->sucursal_id);

            // 6. Calcular fechas
            $fechaCredito = now();
            $fechaPrimerPago = $fechaPrimeraCuota ?? $this->calcularPrimeraFechaPago($frecuenciaPago, $diasGracia);
            $fechaVencimiento = $this->calcularFechaVencimiento($fechaPrimerPago, $numeroCuotas, $frecuenciaPago);

            // 7. Crear registro de venta_credito
            $ventaCredito = VentaCredito::create([
                'numero_credito' => $numeroCredito,
                'venta_id' => $venta->id,
                'cliente_id' => $venta->cliente_id,
                'sucursal_id' => $venta->sucursal_id,
                'vendedor_id' => $venta->vendedor_id ?? Auth::id(),
                'aprobado_por_id' => Auth::id(),
                'estado' => 'vigente',
                'fecha_credito' => $fechaCredito,
                'fecha_aprobacion' => $fechaCredito,
                'fecha_primer_pago' => $fechaPrimerPago,
                'fecha_vencimiento' => $fechaVencimiento,
                'monto_venta' => $montoVenta,
                'enganche' => $enganche,
                'saldo_financiar' => $saldoFinanciar,
                'interes_total' => $interesTotal,
                'total_credito' => $totalCredito,
                'capital_pendiente' => $saldoFinanciar,
                'capital_pagado' => 0,
                'interes_pendiente' => $interesTotal,
                'interes_pagado' => 0,
                'mora_generada' => 0,
                'mora_pagada' => 0,
                'saldo_actual' => $totalCredito,
                'tasa_interes' => $tasaInteres,
                'tasa_mora' => $tasaMora,
                'tipo_interes' => 'flat',
                'frecuencia_pago' => $frecuenciaPago,
                'numero_cuotas' => $numeroCuotas,
                'monto_cuota' => $montoCuota,
                'dias_gracia' => $diasGracia,
                'dias_mora' => 0,
                'cuotas_vencidas' => 0,
                'cuotas_pagadas' => 0,
                'observaciones' => $config['observaciones'] ?? null,
            ]);

            // 8. Generar cuotas
            $this->generarCuotas($ventaCredito, $fechaPrimerPago, $frecuenciaPago);

            // 9. Actualizar venta
            $venta->update([
                'tipo_venta' => 'credito',
                'estado' => 'plan_pagos',
                'enganche' => $enganche,
                'total_pagado' => $enganche,
                'saldo_pendiente' => $totalCredito,
                'numero_cuotas' => $numeroCuotas,
                'monto_cuota' => $montoCuota,
                'tasa_interes' => $tasaInteres,
                'interes_total' => $interesTotal,
                'total_credito' => $totalCredito,
                'fecha_vencimiento' => $fechaVencimiento,
                'observaciones' => ($venta->observaciones ?? '') . "\nPlan de pagos creado: {$numeroCredito}",
            ]);

            // 10. Registrar enganche en caja si se proporcionó
            if ($enganche > 0 && $cajaId) {
                try {
                    CajaService::registrarIngreso(
                        $enganche,
                        "Enganche de venta a crédito {$numeroCredito}",
                        [
                            'tipo_movimiento' => 'enganche',
                            'venta_id' => $venta->id,
                            'numero_credito' => $numeroCredito,
                        ]
                    );
                } catch (Exception $e) {
                    Log::warning("No se pudo registrar enganche en caja: {$e->getMessage()}");
                }
            }

            // 11. Registrar asiento contable
            try {
                $this->contabilidadService->registrarAsiento('venta_credito_enganche', [
                    'sucursal_id' => $venta->sucursal_id,
                    'usuario_id' => Auth::id(),
                    'venta_id' => $venta->id,
                    'numero_documento' => $numeroCredito,
                    'glosa' => "Venta a crédito {$numeroCredito} - Enganche Q" . number_format($enganche, 2),
                    'fecha_documento' => $fechaCredito,
                    'monto_efectivo' => $enganche,
                    'monto_credito' => $saldoFinanciar,
                    'monto_total' => $montoVenta,
                    'caja_id' => $cajaId,
                ]);
            } catch (Exception $e) {
                Log::warning("No se pudo registrar asiento contable: {$e->getMessage()}");
            }

            Log::info("Plan de pagos generado exitosamente", [
                'venta_id' => $venta->id,
                'numero_credito' => $numeroCredito,
                'enganche' => $enganche,
                'saldo_financiar' => $saldoFinanciar,
                'numero_cuotas' => $numeroCuotas,
            ]);

            return $ventaCredito->fresh(['planPagos', 'venta', 'cliente']);
        });
    }

    /**
     * Registrar pago a una cuota específica
     *
     * @param VentaCreditoPlanPago $cuota
     * @param array $datos {
     *   monto: float,
     *   caja_id: int,
     *   observacion: string
     * }
     * @return array
     */
    public function registrarPago(VentaCreditoPlanPago $cuota, array $datos): array
    {
        return DB::transaction(function () use ($cuota, $datos) {
            $monto = (float) $datos['monto'];
            $cajaId = $datos['caja_id'];

            // 1. Validaciones
            if ($monto <= 0) {
                throw new Exception("El monto debe ser mayor a cero");
            }

            if ($cuota->estado === 'pagada') {
                throw new Exception("Esta cuota ya está completamente pagada");
            }

            $ventaCredito = $cuota->ventaCredito;

            // 2. Recalcular mora si aplica
            $this->recalcularMoraCuota($cuota);

            // 3. Determinar cuánto se debe en total en esta cuota
            $totalAdeudar = $cuota->monto_pendiente;

            if ($monto > $totalAdeudar) {
                throw new Exception("El monto Q{$monto} excede lo adeudado Q{$totalAdeudar} en esta cuota");
            }

            // 4. Aplicar pago con prioridad: MORA → INTERÉS → CAPITAL
            $montoRestante = $monto;

            // a) Aplicar a mora
            $pagoMora = min($montoRestante, $cuota->mora_pendiente);
            $cuota->mora_pagada += $pagoMora;
            $cuota->mora_pendiente -= $pagoMora;
            $montoRestante -= $pagoMora;

            // b) Aplicar a interés
            $pagoInteres = min($montoRestante, $cuota->interes_pendiente);
            $cuota->interes_pagado += $pagoInteres;
            $cuota->interes_pendiente -= $pagoInteres;
            $montoRestante -= $pagoInteres;

            // c) Aplicar a capital
            $pagoCapital = min($montoRestante, $cuota->capital_pendiente);
            $cuota->capital_pagado += $pagoCapital;
            $cuota->capital_pendiente -= $pagoCapital;
            $montoRestante -= $pagoCapital;

            // 5. Actualizar totales de cuota
            $cuota->monto_total_pagado += $monto;
            $cuota->monto_pendiente = $cuota->capital_pendiente + $cuota->interes_pendiente + $cuota->mora_pendiente;

            // 6. Actualizar estado de cuota
            if ($cuota->monto_pendiente <= 0.01) { // Tolerancia de 1 centavo
                $cuota->estado = 'pagada';
                $cuota->fecha_pago = now();
                $cuota->monto_pendiente = 0; // Limpiar residuos
            } elseif ($cuota->monto_total_pagado > 0) {
                $cuota->estado = 'pagada_parcial';
            }

            $cuota->usuario_pago_id = Auth::id();
            $cuota->save();

            // 7. Crear movimiento
            $movimiento = VentaCreditoMovimiento::create([
                'venta_credito_id' => $ventaCredito->id,
                'venta_credito_plan_pago_id' => $cuota->id,
                'tipo_movimiento' => 'abono',
                'monto_abono' => $monto,
                'capital_abonado' => $pagoCapital,
                'interes_abonado' => $pagoInteres,
                'mora_abonada' => $pagoMora,
                'saldo_anterior' => $ventaCredito->saldo_actual,
                'saldo_nuevo' => $ventaCredito->saldo_actual - $monto,
                'fecha_movimiento' => now(),
                'usuario_id' => Auth::id(),
                'caja_id' => $cajaId,
                'observaciones' => $datos['observacion'] ?? "Pago cuota #{$cuota->numero_cuota}",
            ]);

            // 8. Actualizar venta_credito
            $ventaCredito->capital_pagado += $pagoCapital;
            $ventaCredito->capital_pendiente -= $pagoCapital;
            $ventaCredito->interes_pagado += $pagoInteres;
            $ventaCredito->interes_pendiente -= $pagoInteres;
            $ventaCredito->mora_pagada += $pagoMora;
            $ventaCredito->mora_generada = max(0, $ventaCredito->mora_generada - $pagoMora);
            $ventaCredito->saldo_actual -= $monto;
            $ventaCredito->fecha_ultimo_pago = now();

            // Contar cuotas pagadas
            $cuotasPagadas = $ventaCredito->planPagos()->where('estado', 'pagada')->count();
            $ventaCredito->cuotas_pagadas = $cuotasPagadas;

            // 9. Verificar si se liquidó completamente
            if ($ventaCredito->saldo_actual <= 0.01) {
                $ventaCredito->estado = 'pagado';
                $ventaCredito->fecha_liquidacion = now();
                $ventaCredito->saldo_actual = 0;

                // Marcar venta como pagada y prendas como vendidas
                $venta = $ventaCredito->venta;
                $venta->update([
                    'estado' => 'pagada',
                    'fecha_liquidacion' => now(),
                    'saldo_pendiente' => 0,
                    'total_pagado' => $venta->total_credito ?? $venta->total_final,
                ]);

                // Marcar prendas como vendidas
                foreach ($venta->detalles as $detalle) {
                    if ($detalle->prenda && $detalle->prenda->estado !== 'vendida') {
                        $detalle->prenda->update([
                            'estado' => 'vendida',
                            'fecha_venta' => now(),
                        ]);
                    }
                }
            }

            $ventaCredito->save();

            // 10. Registrar en caja
            try {
                CajaService::registrarIngreso(
                    $monto,
                    "Pago cuota #{$cuota->numero_cuota} - Crédito {$ventaCredito->numero_credito}",
                    [
                        'tipo_movimiento' => 'pago_cuota',
                        'venta_id' => $ventaCredito->venta_id,
                        'numero_credito' => $ventaCredito->numero_credito,
                        'cuota_numero' => $cuota->numero_cuota,
                    ]
                );
            } catch (Exception $e) {
                Log::warning("No se pudo registrar pago en caja: {$e->getMessage()}");
            }

            // 11. Registrar asiento contable
            try {
                $this->contabilidadService->registrarAsiento('venta_credito_abono', [
                    'sucursal_id' => $ventaCredito->sucursal_id,
                    'usuario_id' => Auth::id(),
                    'venta_id' => $ventaCredito->venta_id,
                    'numero_documento' => $ventaCredito->numero_credito,
                    'glosa' => "Abono cuota #{$cuota->numero_cuota} - Crédito {$ventaCredito->numero_credito}",
                    'fecha_documento' => now(),
                    'monto' => $monto,
                    'monto_capital' => $pagoCapital,
                    'monto_interes' => $pagoInteres,
                    'monto_mora' => $pagoMora,
                    'caja_id' => $cajaId,
                ]);
            } catch (Exception $e) {
                Log::warning("No se pudo registrar asiento contable: {$e->getMessage()}");
            }

            Log::info("Pago de cuota registrado exitosamente", [
                'cuota_id' => $cuota->id,
                'numero_cuota' => $cuota->numero_cuota,
                'monto' => $monto,
                'capital' => $pagoCapital,
                'interes' => $pagoInteres,
                'mora' => $pagoMora,
                'saldo_nuevo' => $ventaCredito->saldo_actual,
                'liquidado' => $ventaCredito->estado === 'pagado',
            ]);

            return [
                'cuota' => $cuota->fresh(),
                'movimiento' => $movimiento,
                'ventaCredito' => $ventaCredito->fresh(['planPagos']),
                'desglose' => [
                    'monto_total' => $monto,
                    'aplicado_mora' => $pagoMora,
                    'aplicado_interes' => $pagoInteres,
                    'aplicado_capital' => $pagoCapital,
                ],
                'saldo_anterior' => $movimiento->saldo_anterior,
                'saldo_nuevo' => $movimiento->saldo_nuevo,
                'liquidado' => $ventaCredito->estado === 'pagado',
            ];
        });
    }

    /**
     * Obtener detalle completo del plan de pagos
     */
    public function obtenerDetallePlan(Venta $venta): ?array
    {
        $ventaCredito = VentaCredito::where('venta_id', $venta->id)
            ->with(['planPagos' => function ($query) {
                $query->orderBy('numero_cuota');
            }, 'cliente', 'vendedor'])
            ->first();

        if (!$ventaCredito) {
            return null;
        }

        // Recalcular mora de cuotas pendientes
        foreach ($ventaCredito->planPagos as $cuota) {
            if ($cuota->estado !== 'pagada') {
                $this->recalcularMoraCuota($cuota);
            }
        }

        $ventaCredito->refresh();

        return [
            'credito' => $ventaCredito,
            'cuotas' => $ventaCredito->planPagos,
            'resumen' => [
                'monto_venta' => $ventaCredito->monto_venta,
                'enganche' => $ventaCredito->enganche,
                'saldo_financiado' => $ventaCredito->saldo_financiar,
                'interes_total' => $ventaCredito->interes_total,
                'total_credito' => $ventaCredito->total_credito,
                'capital_pagado' => $ventaCredito->capital_pagado,
                'capital_pendiente' => $ventaCredito->capital_pendiente,
                'interes_pagado' => $ventaCredito->interes_pagado,
                'interes_pendiente' => $ventaCredito->interes_pendiente,
                'mora_generada' => $ventaCredito->mora_generada,
                'mora_pagada' => $ventaCredito->mora_pagada,
                'saldo_actual' => $ventaCredito->saldo_actual,
                'cuotas_totales' => $ventaCredito->numero_cuotas,
                'cuotas_pagadas' => $ventaCredito->cuotas_pagadas,
                'cuotas_pendientes' => $ventaCredito->numero_cuotas - $ventaCredito->cuotas_pagadas,
                'porcentaje_pagado' => $ventaCredito->numero_cuotas > 0
                    ? round(($ventaCredito->cuotas_pagadas / $ventaCredito->numero_cuotas) * 100, 2)
                    : 0,
            ],
        ];
    }

    // ==================== MÉTODOS PRIVADOS ====================

    /**
     * Validar que la venta pueda generar plan de pagos
     */
    private function validarVentaParaPlan(Venta $venta): void
    {
        // Verificar si ya tiene plan
        if (VentaCredito::where('venta_id', $venta->id)->exists()) {
            throw new Exception("Esta venta ya tiene un plan de pagos generado");
        }

        // Verificar estado
        if (in_array($venta->estado, ['cancelada', 'anulada', 'devuelta'])) {
            throw new Exception("No se puede generar plan de pagos para ventas en estado: {$venta->estado}");
        }

        // Verificar que tenga cliente
        if (!$venta->cliente_id) {
            throw new Exception("La venta debe tener un cliente asignado para generar plan de pagos");
        }
    }

    /**
     * Generar cuotas del plan de pagos
     */
    private function generarCuotas(VentaCredito $ventaCredito, Carbon $fechaPrimerPago, string $frecuencia): void
    {
        $numeroCuotas = $ventaCredito->numero_cuotas;
        $saldoFinanciar = $ventaCredito->saldo_financiar;
        $interesTotal = $ventaCredito->interes_total;
        $totalCredito = $ventaCredito->total_credito;

        // Capital fijo por cuota
        $capitalPorCuota = $saldoFinanciar / $numeroCuotas;

        // Interés fijo por cuota (método FLAT)
        $interesPorCuota = $interesTotal / $numeroCuotas;

        $saldoCapitalRestante = $saldoFinanciar;

        for ($i = 1; $i <= $numeroCuotas; $i++) {
            // Calcular fecha de vencimiento
            $fechaVencimiento = $this->calcularFechaVencimientoCuota($fechaPrimerPago, $i, $frecuencia);

            // Ajustar última cuota para evitar desfases por redondeo
            if ($i === $numeroCuotas) {
                $capitalProyectado = round($saldoCapitalRestante, 2);
            } else {
                $capitalProyectado = round($capitalPorCuota, 2);
            }

            $interesProyectado = round($interesPorCuota, 2);
            $montoCuotaProyectado = round($capitalProyectado + $interesProyectado, 2);

            VentaCreditoPlanPago::create([
                'venta_credito_id' => $ventaCredito->id,
                'numero_cuota' => $i,
                'fecha_vencimiento' => $fechaVencimiento,
                'estado' => 'pendiente',
                'capital_proyectado' => $capitalProyectado,
                'interes_proyectado' => $interesProyectado,
                'mora_proyectada' => 0,
                'otros_cargos_proyectados' => 0,
                'monto_cuota_proyectado' => $montoCuotaProyectado,
                'capital_pendiente' => $capitalProyectado,
                'interes_pendiente' => $interesProyectado,
                'mora_pendiente' => 0,
                'otros_cargos_pendientes' => 0,
                'monto_pendiente' => $montoCuotaProyectado,
                'saldo_capital_credito' => $saldoCapitalRestante - $capitalProyectado,
                'tasa_mora_aplicada' => $ventaCredito->tasa_mora,
                'permite_pago_parcial' => true,
            ]);

            $saldoCapitalRestante -= $capitalProyectado;
        }
    }

    /**
     * Recalcular mora de una cuota
     */
    private function recalcularMoraCuota(VentaCreditoPlanPago $cuota): void
    {
        if ($cuota->estado === 'pagada') {
            return; // No recalcular si ya está pagada
        }

        $hoy = Carbon::now();
        $fechaVencimiento = Carbon::parse($cuota->fecha_vencimiento);

        // Calcular días de mora
        if ($hoy->gt($fechaVencimiento)) {
            $diasGracia = $cuota->ventaCredito->dias_gracia ?? 0;
            $diasMora = max(0, $hoy->diffInDays($fechaVencimiento) - $diasGracia);

            if ($diasMora > 0) {
                $tasaMoraDiaria = ($cuota->tasa_mora_aplicada / 100) / 30; // Convertir mensual a diaria
                $capitalPendiente = $cuota->capital_pendiente;

                $moraCalculada = $capitalPendiente * $tasaMoraDiaria * $diasMora;
                $moraCalculada = round($moraCalculada, 2);

                // Actualizar mora solo si cambió
                if ($moraCalculada != $cuota->mora_proyectada) {
                    $diferenciaMora = $moraCalculada - $cuota->mora_proyectada;

                    $cuota->mora_proyectada = $moraCalculada;
                    $cuota->mora_pendiente += $diferenciaMora;
                    $cuota->monto_pendiente += $diferenciaMora;
                    $cuota->dias_mora = $diasMora;

                    if ($cuota->dias_mora > 0 && !$cuota->fecha_inicio_mora) {
                        $cuota->fecha_inicio_mora = $fechaVencimiento->addDays($diasGracia);
                    }

                    $cuota->estado = 'en_mora';
                    $cuota->save();

                    // Actualizar mora en venta_credito
                    $ventaCredito = $cuota->ventaCredito;
                    $ventaCredito->mora_generada += $diferenciaMora;
                    $ventaCredito->saldo_actual += $diferenciaMora;
                    $ventaCredito->dias_mora = $diasMora;
                    $ventaCredito->estado = 'en_mora';
                    $ventaCredito->save();
                }
            }
        }
    }

    /**
     * Generar número único de crédito
     */
    private function generarNumeroCredito(int $sucursalId): string
    {
        $prefijo = 'VC-' . str_pad($sucursalId, 3, '0', STR_PAD_LEFT);
        $anio = date('Y');
        $mes = date('m');

        $ultimo = VentaCredito::where('numero_credito', 'like', "{$prefijo}-{$anio}{$mes}%")
            ->orderBy('id', 'desc')
            ->first();

        $consecutivo = $ultimo ? (intval(substr($ultimo->numero_credito, -4)) + 1) : 1;

        return sprintf('%s-%s%s-%04d', $prefijo, $anio, $mes, $consecutivo);
    }

    /**
     * Calcular primera fecha de pago
     */
    private function calcularPrimeraFechaPago(string $frecuencia, int $diasGracia = 0): Carbon
    {
        $fecha = now()->addDays($diasGracia);

        return match ($frecuencia) {
            'semanal' => $fecha->addWeek(),
            'quincenal' => $fecha->addWeeks(2),
            'mensual' => $fecha->addMonth(),
            default => $fecha->addMonth(),
        };
    }

    /**
     * Calcular fecha de vencimiento final
     */
    private function calcularFechaVencimiento(Carbon $fechaInicio, int $numeroCuotas, string $frecuencia): Carbon
    {
        return match ($frecuencia) {
            'semanal' => $fechaInicio->copy()->addWeeks($numeroCuotas - 1),
            'quincenal' => $fechaInicio->copy()->addWeeks(($numeroCuotas - 1) * 2),
            'mensual' => $fechaInicio->copy()->addMonths($numeroCuotas - 1),
            default => $fechaInicio->copy()->addMonths($numeroCuotas - 1),
        };
    }

    /**
     * Calcular fecha de vencimiento de una cuota específica
     */
    private function calcularFechaVencimientoCuota(Carbon $fechaInicio, int $numeroCuota, string $frecuencia): Carbon
    {
        // Primera cuota usa fecha de inicio
        if ($numeroCuota === 1) {
            return $fechaInicio->copy();
        }

        // Cuotas subsecuentes
        return match ($frecuencia) {
            'semanal' => $fechaInicio->copy()->addWeeks($numeroCuota - 1),
            'quincenal' => $fechaInicio->copy()->addWeeks(($numeroCuota - 1) * 2),
            'mensual' => $fechaInicio->copy()->addMonths($numeroCuota - 1),
            default => $fechaInicio->copy()->addMonths($numeroCuota - 1),
        };
    }
}
