<?php

namespace App\Services;

use App\Models\CreditoPrendario;
use App\Models\CreditoMovimiento;
use App\Models\CreditoPlanPago;
use App\Models\IdempotencyKey;
use App\Services\CajaService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class PagoService
{
    /**
     * ============================================================================
     * CÁLCULO DE DEUDA AL DÍA
     * ============================================================================
     *
     * LÓGICA DE MORA:
     * - La mora se genera cuando: fecha_actual > fecha_vencimiento + días_gracia
     * - Cálculo: Capital * (TasaMora% / 100 / 30) * DíasMora
     * - Los días de mora = max(0, días desde vencimiento - días de gracia)
     *
     * LÓGICA DE INTERÉS:
     * - El interés se calcula por periodo completo (mes/quincena/semana iniciada = cobrada)
     * - Interés = Capital * TasaInterés% * NúmeroPeriodos
     *
     * @param CreditoPrendario $credito
     * @param Carbon|null $fechaCalculo
     * @return array
     */
    public function calcularDeudaAlDia(CreditoPrendario $credito, ?Carbon $fechaCalculo = null): array
    {
        $fechaCalculo = $fechaCalculo ?? Carbon::now();
        $fechaBase = $credito->fecha_ultimo_pago ?? $credito->fecha_desembolso;

        if (!$fechaBase) {
            $fechaBase = $credito->fecha_solicitud ?? Carbon::now();
        }

        $diasTranscurridos = max(1, $fechaCalculo->diffInDays($fechaBase));

        // ============== CÁLCULO DE INTERÉS ==============
        $tasaInteres = $credito->tasa_interes / 100;
        $periodosCobrar = $this->calcularPeriodos($diasTranscurridos, $credito->tipo_interes);

        // Interés Devengado = Capital * Tasa * Periodos
        $interesDevengado = $credito->capital_pendiente * $tasaInteres * $periodosCobrar;

        // ============== CÁLCULO DE MORA ==============
        $moraDevengada = 0;
        $diasMora = 0;

        if ($credito->fecha_vencimiento && $fechaCalculo->gt($credito->fecha_vencimiento)) {
            $diasDesdeVencimiento = $fechaCalculo->diffInDays($credito->fecha_vencimiento);
            $diasGracia = $credito->dias_gracia ?? 0;
            $diasMora = max(0, $diasDesdeVencimiento - $diasGracia);

            if ($diasMora > 0 && $credito->tasa_mora > 0) {
                // Mora diaria: TasaMora% mensual convertida a diaria
                $tasaMoraDiaria = ($credito->tasa_mora / 100) / 30;
                $moraDevengada = $credito->capital_pendiente * $tasaMoraDiaria * $diasMora;
            }
        }

        // ============== TOTALES ==============
        // Sumar intereses/mora históricos no pagados
        $interesHistorico = max(0, ($credito->interes_generado ?? 0) - ($credito->interes_pagado ?? 0));
        $moraHistorica = max(0, ($credito->mora_generada ?? 0) - ($credito->mora_pagada ?? 0));

        $interesTotal = $interesHistorico + $interesDevengado;
        $moraTotal = $moraHistorica + $moraDevengada;

        $totalPagar = $credito->capital_pendiente + $interesTotal + $moraTotal;
        $minimoRenovacion = $interesTotal + $moraTotal;

        // Interés por un periodo (para cálculo de adelantos)
        $interesPorPeriodo = $credito->capital_pendiente * $tasaInteres;

        return [
            'fecha_calculo' => $fechaCalculo->format('Y-m-d'),
            'dias_transcurridos' => $diasTranscurridos,
            'periodos_cobrados' => $periodosCobrar,
            'tipo_periodo' => $credito->tipo_interes,
            'capital_pendiente' => round($credito->capital_pendiente, 2),
            'interes_acumulado' => round($interesTotal, 2),
            'interes_periodo_actual' => round($interesDevengado, 2),
            'mora_acumulada' => round($moraTotal, 2),
            'dias_mora' => $diasMora,
            'total_para_liquidar' => round($totalPagar, 2),
            'minimo_renovacion' => round($minimoRenovacion, 2),
            'interes_por_periodo' => round($interesPorPeriodo, 2),
            'fecha_vencimiento' => $credito->fecha_vencimiento?->format('Y-m-d'),
            'en_mora' => $diasMora > 0,
            // Opciones para pagos adelantados
            'opciones_adelanto' => $this->calcularOpcionesAdelanto($credito, $interesPorPeriodo, $minimoRenovacion),
        ];
    }

    /**
     * Calcula los periodos a cobrar según el tipo de interés
     */
    private function calcularPeriodos(int $dias, string $tipoInteres): int
    {
        return match ($tipoInteres) {
            'semanal' => max(1, ceil($dias / 7)),
            'quincenal' => max(1, ceil($dias / 15)),
            'diario' => $dias,
            default => max(1, ceil($dias / 30)), // mensual por defecto
        };
    }

    /**
     * Calcula opciones de pago adelantado (1, 2, 3 periodos hacia adelante)
     */
    private function calcularOpcionesAdelanto(CreditoPrendario $credito, float $interesPorPeriodo, float $minimoActual): array
    {
        $opciones = [];

        for ($i = 1; $i <= 3; $i++) {
            $opciones[] = [
                'periodos' => $i,
                'descripcion' => $i === 1 ? '1 periodo adelantado' : "$i periodos adelantados",
                'monto_interes' => round($interesPorPeriodo * $i, 2),
                'monto_total' => round($minimoActual + ($interesPorPeriodo * ($i - 1)), 2),
            ];
        }

        return $opciones;
    }

    /**
     * ============================================================================
     * EJECUTAR PAGO
     * ============================================================================
     * Tipos de pago soportados:
     * - RENOVACION: Paga intereses y mora, extiende plazo
     * - PARCIAL: Abono libre con prelación (mora → interés → capital)
     * - LIQUIDACION: Paga todo y cierra el crédito
     * - INTERES_ADELANTADO: Paga intereses actual + periodos adelantados
     */
    public function ejecutarPago(array $data)
    {
        return DB::transaction(function () use ($data) {
            // Verificar idempotencia
            if (isset($data['idempotency_key'])) {
                $existe = IdempotencyKey::where('key_hash', $data['idempotency_key'])->first();
                if ($existe) {
                    throw new \Exception("Esta operación ya fue procesada.");
                }
                IdempotencyKey::create([
                    'key_hash' => $data['idempotency_key'],
                    'payload' => json_encode($data),
                    'operacion' => 'pago'
                ]);
            }

            $credito = CreditoPrendario::lockForUpdate()->find($data['credito_id']);

            if (!$credito) {
                throw new \Exception("Crédito no encontrado");
            }

            if (in_array($credito->estado, ['liquidado', 'anulado', 'vendido', 'pagado'])) {
                throw new \Exception("El crédito no está en un estado válido para recibir pagos.");
            }

            $tipo = $data['tipo'];
            $monto = (float) $data['monto'];

            return match ($tipo) {
                'RENOVACION' => $this->procesarRenovacion($credito, $monto, $data, $data['periodos_adelanto'] ?? 0),
                'PARCIAL' => $this->procesarPagoParcial($credito, $monto, $data),
                'LIQUIDACION' => $this->procesarLiquidacion($credito, $monto, $data),
                'INTERES_ADELANTADO' => $this->procesarPagoInteresAdelantado($credito, $monto, $data, $data['periodos'] ?? 1),
                default => throw new \Exception("Tipo de pago no válido: $tipo"),
            };
        });
    }

    /**
     * ============================================================================
     * RENOVACIÓN
     * ============================================================================
     * - Paga intereses acumulados y mora
     * - Extiende la fecha de vencimiento
     * - Crea nueva cuota en el plan de pagos con interés proyectado calculado
     */
    private function procesarRenovacion(CreditoPrendario $credito, float $monto, array $data, int $periodosAdelanto = 0)
    {
        $calculo = $this->calcularDeudaAlDia($credito);
        $minimo = $calculo['minimo_renovacion'];

        if ($monto < ($minimo - 0.10)) {
            throw new \Exception("El monto es insuficiente para renovación. Mínimo: Q" . number_format($minimo, 2));
        }

        // Registrar Movimiento
        $movimiento = CreditoMovimiento::create([
            'credito_prendario_id' => $credito->id,
            'tipo_movimiento' => 'pago',
            'fecha_movimiento' => now(),
            'fecha_registro' => now(),
            'monto_total' => $monto,
            'capital' => 0,
            'interes' => $calculo['interes_acumulado'],
            'mora' => $calculo['mora_acumulada'],
            'otros_cargos' => 0,
            'saldo_capital' => $credito->capital_pendiente,
            'usuario_id' => Auth::id() ?? 1,
            'sucursal_id' => $credito->sucursal_id,
            'numero_movimiento' => Str::upper(Str::random(10)),
            'observaciones' => "RENOVACIÓN" . ($periodosAdelanto > 0 ? " (+$periodosAdelanto periodos adelantados)" : "") . " - " . ($data['observaciones'] ?? ''),
            'estado' => 'activo'
        ]);

        // Actualizar Crédito
        $credito->interes_pagado = (float)$credito->interes_pagado + (float)$calculo['interes_acumulado'];
        $credito->mora_pagada = (float)$credito->mora_pagada + (float)$calculo['mora_acumulada'];
        $credito->interes_generado = (float)$credito->interes_generado + (float)$calculo['interes_acumulado'];
        $credito->mora_generada = (float)$credito->mora_generada + (float)$calculo['mora_acumulada'];
        $credito->fecha_ultimo_pago = now();
        $credito->dias_mora = 0;

        // Calcular nueva fecha de vencimiento
        $diasExtension = $credito->plazo_dias * (1 + $periodosAdelanto);
        $nuevoVencimiento = now()->addDays($diasExtension);
        $credito->fecha_vencimiento = $nuevoVencimiento;
        $credito->estado = 'vigente';
        $credito->save();

        // Actualizar Plan de Pagos
        $this->actualizarPlanPagosPorRenovacion($credito, $calculo, $nuevoVencimiento);

        // ========================================
        // REGISTRAR INGRESO A CAJA
        // ========================================
        $clienteNombre = $credito->cliente ? ($credito->cliente->nombres . ' ' . $credito->cliente->apellidos) : null;
        CajaService::registrarPagoCredito(
            $monto,
            $credito->numero_credito,
            'RENOVACION',
            $movimiento->numero_movimiento,
            $clienteNombre
        );

        return $movimiento;
    }

    /**
     * Actualiza el plan de pagos al renovar
     */
    private function actualizarPlanPagosPorRenovacion(CreditoPrendario $credito, array $calculo, Carbon $nuevoVencimiento)
    {
        // Buscar cuota actual
        $cuotaActual = CreditoPlanPago::where('credito_prendario_id', $credito->id)
            ->where('estado', '!=', 'pagada')
            ->orderBy('numero_cuota', 'asc')
            ->first();

        $numeroNuevaCuota = 1;

        if ($cuotaActual) {
            // Marcar cuota actual como pagada
            $cuotaActual->estado = 'pagada';
            $cuotaActual->interes_pagado = (float)($cuotaActual->interes_pagado ?? 0) + (float)$calculo['interes_acumulado'];
            $cuotaActual->mora_pagada = (float)($cuotaActual->mora_pagada ?? 0) + (float)$calculo['mora_acumulada'];
            $cuotaActual->capital_pendiente = 0;
            $cuotaActual->monto_pendiente = 0;
            $cuotaActual->observaciones = ($cuotaActual->observaciones ?? '') . " | Renovado el " . now()->format('d/m/Y');
            $cuotaActual->save();

            $numeroNuevaCuota = $cuotaActual->numero_cuota + 1;
        } else {
            $max = CreditoPlanPago::where('credito_prendario_id', $credito->id)->max('numero_cuota');
            $numeroNuevaCuota = ($max ?? 0) + 1;
        }

        // Calcular interés proyectado para el nuevo periodo
        $interesProyectadoNuevo = $this->calcularInteresProyectado($credito);

        // Crear nueva cuota
        CreditoPlanPago::create([
            'credito_prendario_id' => $credito->id,
            'numero_cuota' => $numeroNuevaCuota,
            'fecha_vencimiento' => $nuevoVencimiento,
            'estado' => 'pendiente',
            'capital_proyectado' => $credito->capital_pendiente,
            'interes_proyectado' => $interesProyectadoNuevo,
            'mora_proyectada' => 0,
            'monto_cuota_proyectado' => $credito->capital_pendiente + $interesProyectadoNuevo,
            'capital_pendiente' => $credito->capital_pendiente,
            'interes_pendiente' => $interesProyectadoNuevo,
            'monto_pendiente' => $credito->capital_pendiente + $interesProyectadoNuevo,
            'saldo_capital_credito' => $credito->capital_pendiente,
            'tipo_modificacion' => 'refinanciamiento',
            'motivo_modificacion' => 'Renovación de plazo'
        ]);
    }

    /**
     * Calcula el interés proyectado según el tipo de interés y plazo
     */
    private function calcularInteresProyectado(CreditoPrendario $credito): float
    {
        $capital = $credito->capital_pendiente;
        $tasaDecimal = $credito->tasa_interes / 100;
        $plazo = $credito->plazo_dias;

        return match ($credito->tipo_interes) {
            'diario' => $capital * $tasaDecimal * $plazo,
            'semanal' => $capital * $tasaDecimal * ceil($plazo / 7),
            'quincenal' => $capital * $tasaDecimal * ceil($plazo / 15),
            default => $capital * $tasaDecimal * ceil($plazo / 30), // mensual
        };
    }

    /**
     * ============================================================================
     * PAGO DE INTERESES ADELANTADOS
     * ============================================================================
     * Permite pagar intereses del periodo actual + periodos adelantados
     * sin necesidad de renovar (el capital sigue igual)
     */
    private function procesarPagoInteresAdelantado(CreditoPrendario $credito, float $monto, array $data, int $periodos)
    {
        $calculo = $this->calcularDeudaAlDia($credito);

        // El monto mínimo es el interés actual + mora
        $montoMinimo = $calculo['minimo_renovacion'];

        if ($monto < ($montoMinimo - 0.10)) {
            throw new \Exception("El monto es insuficiente. Mínimo para 1 periodo: Q" . number_format($montoMinimo, 2));
        }

        // Calcular cuánto interés estamos pagando
        $interesPorPeriodo = $calculo['interes_por_periodo'];
        $totalInteresesPagados = $calculo['interes_acumulado'] + ($interesPorPeriodo * max(0, $periodos - 1));

        // Registrar Movimiento
        $movimiento = CreditoMovimiento::create([
            'credito_prendario_id' => $credito->id,
            'tipo_movimiento' => 'pago_interes',
            'fecha_movimiento' => now(),
            'fecha_registro' => now(),
            'monto_total' => $monto,
            'capital' => 0,
            'interes' => min($monto - $calculo['mora_acumulada'], $totalInteresesPagados),
            'mora' => $calculo['mora_acumulada'],
            'otros_cargos' => 0,
            'saldo_capital' => $credito->capital_pendiente,
            'usuario_id' => Auth::id() ?? 1,
            'sucursal_id' => $credito->sucursal_id,
            'numero_movimiento' => Str::upper(Str::random(10)),
            'observaciones' => "PAGO INTERESES ({$periodos} periodo(s)) - " . ($data['observaciones'] ?? ''),
            'estado' => 'activo'
        ]);

        // Actualizar Crédito
        $credito->interes_pagado = (float)$credito->interes_pagado + (float)$calculo['interes_acumulado'];
        $credito->mora_pagada = (float)$credito->mora_pagada + (float)$calculo['mora_acumulada'];
        $credito->interes_generado = (float)$credito->interes_generado + (float)$calculo['interes_acumulado'];
        $credito->mora_generada = (float)$credito->mora_generada + (float)$calculo['mora_acumulada'];
        $credito->fecha_ultimo_pago = now();
        $credito->dias_mora = 0;

        // Extender fecha de vencimiento según periodos pagados
        $diasExtension = $this->calcularDiasExtension($credito->tipo_interes, $credito->plazo_dias) * $periodos;
        $credito->fecha_vencimiento = now()->addDays($diasExtension);
        $credito->estado = 'vigente';
        $credito->save();

        // ========================================
        // REGISTRAR INGRESO A CAJA
        // ========================================
        $clienteNombre = $credito->cliente ? ($credito->cliente->nombres . ' ' . $credito->cliente->apellidos) : null;
        CajaService::registrarPagoCredito(
            $monto,
            $credito->numero_credito,
            'INTERES_ADELANTADO',
            $movimiento->numero_movimiento,
            $clienteNombre
        );

        return $movimiento;
    }

    /**
     * Calcula días de extensión según tipo de interés
     */
    private function calcularDiasExtension(string $tipoInteres, int $plazoDias): int
    {
        return match ($tipoInteres) {
            'diario' => 1,
            'semanal' => 7,
            'quincenal' => 15,
            default => $plazoDias, // mensual usa el plazo definido
        };
    }

    /**
     * ============================================================================
     * PAGO PARCIAL (ABONO)
     * ============================================================================
     * Aplica prelación: 1. Mora, 2. Interés, 3. Capital
     */
    private function procesarPagoParcial(CreditoPrendario $credito, float $monto, array $data)
    {
        $calculo = $this->calcularDeudaAlDia($credito);

        // Prelación: 1. Mora, 2. Interés, 3. Capital
        $pagoMora = min($monto, $calculo['mora_acumulada']);
        $remanente = $monto - $pagoMora;

        $pagoInteres = min($remanente, $calculo['interes_acumulado']);
        $remanente = $remanente - $pagoInteres;

        $pagoCapital = $remanente;

        // Registrar Movimiento
        $movimiento = CreditoMovimiento::create([
            'credito_prendario_id' => $credito->id,
            'tipo_movimiento' => 'pago_parcial',
            'fecha_movimiento' => now(),
            'fecha_registro' => now(),
            'monto_total' => $monto,
            'capital' => $pagoCapital,
            'interes' => $pagoInteres,
            'mora' => $pagoMora,
            'otros_cargos' => 0,
            'saldo_capital' => $credito->capital_pendiente - $pagoCapital,
            'usuario_id' => Auth::id() ?? 1,
            'sucursal_id' => $credito->sucursal_id,
            'numero_movimiento' => Str::upper(Str::random(10)),
            'observaciones' => "ABONO - " . ($data['observaciones'] ?? ''),
            'estado' => 'activo'
        ]);

        // Actualizar Crédito
        $credito->capital_pendiente = (float)$credito->capital_pendiente - (float)$pagoCapital;
        $credito->capital_pagado = (float)$credito->capital_pagado + (float)$pagoCapital;
        $credito->interes_pagado = (float)$credito->interes_pagado + (float)$pagoInteres;
        $credito->mora_pagada = (float)$credito->mora_pagada + (float)$pagoMora;
        $credito->interes_generado = (float)$credito->interes_generado + (float)$calculo['interes_acumulado'];
        $credito->mora_generada = (float)$credito->mora_generada + (float)$calculo['mora_acumulada'];
        $credito->fecha_ultimo_pago = now();

        // Si cubrió los intereses, resetear días mora
        if ($pagoInteres >= $calculo['interes_acumulado']) {
            $credito->dias_mora = 0;
        }

        $credito->save();

        // Actualizar Plan de Pagos
        $this->actualizarPlanPagos($credito, $monto);

        // ========================================
        // REGISTRAR INGRESO A CAJA
        // ========================================
        $clienteNombre = $credito->cliente ? ($credito->cliente->nombres . ' ' . $credito->cliente->apellidos) : null;
        CajaService::registrarPagoCredito(
            $monto,
            $credito->numero_credito,
            'PARCIAL',
            $movimiento->numero_movimiento,
            $clienteNombre
        );

        return $movimiento;
    }

    /**
     * ============================================================================
     * LIQUIDACIÓN
     * ============================================================================
     * Paga todo: Capital + Interés + Mora y cierra el crédito
     */
    private function procesarLiquidacion(CreditoPrendario $credito, float $monto, array $data)
    {
        $calculo = $this->calcularDeudaAlDia($credito);
        $totalDeuda = $calculo['total_para_liquidar'];

        if ($monto < ($totalDeuda - 0.10)) {
            throw new \Exception("Monto insuficiente para liquidar. Total requerido: Q" . number_format($totalDeuda, 2));
        }

        $pagoCapital = $credito->capital_pendiente;
        $pagoInteres = $calculo['interes_acumulado'];
        $pagoMora = $calculo['mora_acumulada'];

        // Registrar Movimiento
        $movimiento = CreditoMovimiento::create([
            'credito_prendario_id' => $credito->id,
            'tipo_movimiento' => 'pago_total',
            'fecha_movimiento' => now(),
            'fecha_registro' => now(),
            'monto_total' => $monto,
            'capital' => $pagoCapital,
            'interes' => $pagoInteres,
            'mora' => $pagoMora,
            'otros_cargos' => 0,
            'saldo_capital' => 0,
            'usuario_id' => Auth::id() ?? 1,
            'sucursal_id' => $credito->sucursal_id,
            'numero_movimiento' => Str::upper(Str::random(10)),
            'observaciones' => "LIQUIDACIÓN TOTAL - " . ($data['observaciones'] ?? ''),
            'estado' => 'activo'
        ]);

        // Actualizar Crédito
        $credito->capital_pendiente = 0;
        $credito->capital_pagado = (float)$credito->capital_pagado + (float)$pagoCapital;
        $credito->interes_pagado = (float)$credito->interes_pagado + (float)$pagoInteres;
        $credito->mora_pagada = (float)$credito->mora_pagada + (float)$pagoMora;
        $credito->interes_generado = (float)$credito->interes_generado + (float)$calculo['interes_acumulado'];
        $credito->mora_generada = (float)$credito->mora_generada + (float)$calculo['mora_acumulada'];
        $credito->fecha_ultimo_pago = now();
        $credito->fecha_cancelacion = now();
        $credito->estado = 'pagado';
        $credito->save();

        // Liberar Prendas
        $credito->prendas()->update(['estado' => 'recuperada']);

        // Marcar todas las cuotas pendientes como pagadas
        $this->liquidarPlanPagos($credito);

        // ========================================
        // REGISTRAR INGRESO A CAJA
        // ========================================
        $clienteNombre = $credito->cliente ? ($credito->cliente->nombres . ' ' . $credito->cliente->apellidos) : null;
        CajaService::registrarPagoCredito(
            $monto,
            $credito->numero_credito,
            'LIQUIDACION',
            $movimiento->numero_movimiento,
            $clienteNombre
        );

        return $movimiento;
    }

    /**
     * Aplica el monto pagado a las cuotas del plan de pagos.
     */
    private function actualizarPlanPagos(CreditoPrendario $credito, float $montoPagado)
    {
        $cuota = CreditoPlanPago::where('credito_prendario_id', $credito->id)
            ->where('estado', '!=', 'pagada')
            ->orderBy('numero_cuota', 'asc')
            ->first();

        if ($cuota) {
            $cuota->saldo_capital_credito = $credito->capital_pendiente;

            $capitalAmortizado = max(0, $cuota->capital_proyectado - $credito->capital_pendiente);

            if ($capitalAmortizado > ($cuota->capital_pagado ?? 0)) {
                $cuota->capital_pagado = $capitalAmortizado;
                $cuota->capital_pendiente = max(0, $cuota->capital_proyectado - $capitalAmortizado);
            }

            if ($credito->capital_pendiente <= 0.10) {
                $cuota->estado = 'pagada';
                $cuota->capital_pendiente = 0;
            } else {
                $cuota->estado = 'pendiente';
            }

            $cuota->save();
        }
    }

    /**
     * Marca todas las cuotas pendientes como pagadas (liquidación)
     */
    private function liquidarPlanPagos(CreditoPrendario $credito)
    {
        CreditoPlanPago::where('credito_prendario_id', $credito->id)
            ->where('estado', '!=', 'pagada')
            ->update([
                'estado' => 'pagada',
                'monto_pendiente' => 0,
                'capital_pendiente' => 0,
                'interes_pendiente' => 0,
                'mora_pendiente' => 0,
                'saldo_capital_credito' => 0
            ]);
    }

    /**
     * ============================================================================
     * REACTIVACIÓN
     * ============================================================================
     * Reactiva un crédito liquidado para un nuevo ciclo
     */
    public function reactivar(CreditoPrendario $credito, array $data)
    {
        return DB::transaction(function () use ($credito, $data) {
            if ($credito->estado !== 'pagado' && $credito->estado !== 'liquidado') {
                throw new \Exception("Solo se pueden reactivar créditos pagados o liquidados.");
            }

            $monto = $credito->monto_aprobado;

            // Registrar Movimiento de Desembolso
            $movimiento = CreditoMovimiento::create([
                'credito_prendario_id' => $credito->id,
                'tipo_movimiento' => 'desembolso',
                'fecha_movimiento' => now(),
                'fecha_registro' => now(),
                'monto_total' => $monto,
                'capital' => $monto,
                'interes' => 0,
                'mora' => 0,
                'otros_cargos' => 0,
                'saldo_capital' => $monto,
                'usuario_id' => Auth::id() ?? 1,
                'sucursal_id' => $credito->sucursal_id,
                'numero_movimiento' => Str::upper(Str::random(10)),
                'observaciones' => "REACTIVACIÓN / REEMPEÑO - " . ($data['observaciones'] ?? ''),
                'estado' => 'activo'
            ]);

            // Actualizar Crédito
            $credito->capital_pendiente = $monto;
            $credito->interes_pendiente = 0;
            $credito->mora_pendiente = 0;
            $credito->fecha_desembolso = now();
            $credito->fecha_vencimiento = now()->addDays($credito->plazo_dias);
            $credito->fecha_ultimo_pago = null;
            $credito->fecha_cancelacion = null;
            $credito->estado = 'vigente';
            $credito->save();

            // Actualizar Prendas
            $credito->prendas()->update(['estado' => 'en_custodia']);

            // Generar Nueva Cuota
            $maxCuota = CreditoPlanPago::where('credito_prendario_id', $credito->id)->max('numero_cuota');
            $nuevoNumero = ($maxCuota ?? 0) + 1;

            $interesProyectado = $this->calcularInteresProyectado($credito);

            CreditoPlanPago::create([
                'credito_prendario_id' => $credito->id,
                'numero_cuota' => $nuevoNumero,
                'fecha_vencimiento' => $credito->fecha_vencimiento,
                'estado' => 'pendiente',
                'capital_proyectado' => $monto,
                'interes_proyectado' => $interesProyectado,
                'mora_proyectada' => 0,
                'monto_cuota_proyectado' => $monto + $interesProyectado,
                'capital_pendiente' => $monto,
                'interes_pendiente' => $interesProyectado,
                'monto_pendiente' => $monto + $interesProyectado,
                'saldo_capital_credito' => $monto,
                'tipo_modificacion' => 'reestructuracion',
                'motivo_modificacion' => 'Reactivación de crédito'
            ]);

            // ========================================
            // REGISTRAR DESEMBOLSO EN CAJA (EGRESO)
            // ========================================
            $clienteNombre = $credito->cliente ? ($credito->cliente->nombres . ' ' . $credito->cliente->apellidos) : null;
            $formaDesembolso = $data['forma_desembolso'] ?? 'efectivo';

            if ($formaDesembolso === 'efectivo') {
                try {
                    CajaService::registrarDesembolso(
                        $monto,
                        $credito->numero_credito,
                        $clienteNombre,
                        $formaDesembolso
                    );
                } catch (\Exception $e) {
                    // Log pero no fallar si no hay caja abierta
                    Log::warning('No se pudo registrar desembolso en caja (reactivación): ' . $e->getMessage());
                }
            }

            return $movimiento;
        });
    }
}
