<?php

namespace App\Services;

use App\Models\CreditoPrendario;
use App\Models\CreditoMovimiento;
use App\Models\CreditoPlanPago;
use App\Models\PlanInteresCategoria;
use App\Services\MoraService;
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

        $diasTranscurridos = max(1, abs($fechaCalculo->diffInDays($fechaBase)));

        // ============== CÁLCULO DE INTERÉS ==============
        $tasaInteres = $credito->tasa_interes / 100;
        $periodosCobrar = $this->calcularPeriodos($diasTranscurridos, $credito->tipo_interes);

        // Interés Devengado = Capital * Tasa * Periodos
        $interesDevengado = $credito->capital_pendiente * $tasaInteres * $periodosCobrar;

        // ============== CÁLCULO DE MORA ==============
        $moraDevengada = 0;
        $diasMora = 0;

        if ($credito->fecha_vencimiento && $fechaCalculo->gt($credito->fecha_vencimiento)) {
            $diasDesdeVencimiento = (int) abs($fechaCalculo->diffInDays($credito->fecha_vencimiento));
            $diasGracia = $credito->dias_gracia ?? 0;
            $diasMora = max(0, $diasDesdeVencimiento - $diasGracia);

            if ($diasMora > 0) {
                $tipoMora = $credito->tipo_mora ?? 'porcentaje';
                $tasaMoraCredito = (float) ($credito->tasa_mora ?? 0);

                // Fallback: obtener tasa del plan de interés si el crédito no la tiene
                if ($tasaMoraCredito <= 0 && $tipoMora === 'porcentaje' && $credito->plan_interes_id) {
                    $plan = PlanInteresCategoria::find($credito->plan_interes_id);
                    if ($plan && (float)($plan->tasa_moratorios ?? 0) > 0) {
                        $tasaMoraCredito = (float) $plan->tasa_moratorios;
                    }
                }

                if ($tipoMora === 'monto_fijo' && ($credito->mora_monto_fijo ?? 0) > 0) {
                    $moraDevengada = (float) $credito->mora_monto_fijo * $diasMora;
                } elseif ($tipoMora === 'porcentaje' && $tasaMoraCredito > 0) {
                    $tasaMoraDiaria = ($tasaMoraCredito / 100) / 30;
                    $moraDevengada = $credito->capital_pendiente * $tasaMoraDiaria * $diasMora;
                }
            }
        }

        // ============== TOTALES ==============
        // Sumar intereses/mora históricos no pagados
        $interesHistorico = max(0, ($credito->interes_generado ?? 0) - ($credito->interes_pagado ?? 0));
        $moraHistorica = max(0, ($credito->mora_generada ?? 0) - ($credito->mora_pagada ?? 0));

        $interesTotal = $interesHistorico + $interesDevengado;
        $moraTotal = $moraHistorica + $moraDevengada;

        $totalPagar = $credito->capital_pendiente + $interesTotal + $moraTotal;

        // Interés por un periodo (para cálculo de adelantos y renovación)
        // Usar tasa convertida al período correcto (mensual, semanal, etc.)
        $tasaPorPeriodo = $this->calcularTasaPorPeriodo($credito->tasa_interes, $credito->tipo_interes);
        $interesPorPeriodo = $credito->capital_pendiente * $tasaPorPeriodo;

        // Renovación = solo pagar interés de nuevos períodos, NO deuda acumulada
        $minimoRenovacion = $interesPorPeriodo;

        // Primera cuota pendiente (para tipo CUOTA)
        $primeraCuota = CreditoPlanPago::where('credito_prendario_id', $credito->id)
            ->where('estado', 'pendiente')
            ->orderBy('numero_cuota', 'asc')
            ->first();

        $montoCuotaActual = 0;
        if ($primeraCuota) {
            $montoCuotaActual = $primeraCuota->capital_proyectado + $primeraCuota->interes_proyectado +
                              ($primeraCuota->mora_proyectada ?? 0) + ($primeraCuota->otros_cargos_proyectados ?? 0);
        }

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
            'monto_cuota_actual' => round($montoCuotaActual, 2),
            'fecha_vencimiento' => $credito->fecha_vencimiento?->format('Y-m-d'),
            'en_mora' => $diasMora > 0,
            // Opciones para renovación (pagos de interés adelantado)
            'opciones_adelanto' => $this->calcularOpcionesRenovacion($credito, $interesPorPeriodo, $minimoRenovacion),
            // Opciones para adelanto de cuotas completas
            'opciones_cuotas_adelanto' => $this->calcularOpcionesCuotasAdelanto($credito),
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
     * Convierte la tasa anual a tasa por período
     */
    private function calcularTasaPorPeriodo(float $tasaAnual, string $tipoInteres): float
    {
        switch ($tipoInteres) {
            case 'diario':
                return $tasaAnual / 100 / 365;
            case 'semanal':
                return $tasaAnual / 100 / 52;
            case 'catorcenal':
                return $tasaAnual / 100 / 26; // 26 catorcenas al año
            case 'quincenal':
                return $tasaAnual / 100 / 24;
            case 'cada_28_dias':
                return $tasaAnual / 100 / 13; // 13 períodos de 28 días al año
            case 'mensual':
            default:
                return $tasaAnual / 100 / 12;
        }
    }

    /**
     * Calcula opciones de renovación (pago de interés para 1, 2, 3 periodos)
     */
    private function calcularOpcionesRenovacion(CreditoPrendario $credito, float $interesPorPeriodo, float $minimoActual): array
    {
        $opciones = [];

        for ($i = 1; $i <= 3; $i++) {
            $opciones[] = [
                'periodos' => $i,
                'descripcion' => $i === 1 ? '1 periodo' : "$i periodos",
                'monto_interes' => round($interesPorPeriodo * $i, 2),
                'monto_total' => round($minimoActual + ($interesPorPeriodo * ($i - 1)), 2),
            ];
        }

        return $opciones;
    }

    /**
     * Calcula opciones de adelanto de cuotas completas (1, 2, 3, 4, 5 cuotas)
     */
    private function calcularOpcionesCuotasAdelanto(CreditoPrendario $credito): array
    {
        // 1. Encontrar la última cuota pagada completamente
        $ultimaCuotaPagada = CreditoPlanPago::where('credito_prendario_id', $credito->id)
            ->where('estado', 'pagada')
            ->orderBy('numero_cuota', 'desc')
            ->first();

        $numeroCuotaInicio = $ultimaCuotaPagada ? $ultimaCuotaPagada->numero_cuota : 0;

        // 2. Obtener cuotas pendientes DESPUÉS de la última pagada (incluye parciales)
        $cuotas = CreditoPlanPago::where('credito_prendario_id', $credito->id)
            ->where('numero_cuota', '>', $numeroCuotaInicio)
            ->whereIn('estado', ['pendiente', 'pagada_parcial'])
            ->where(function($q) {
                $q->where('monto_pendiente', '>', 0.01)
                  ->orWhereNull('monto_pendiente');
            })
            ->orderBy('numero_cuota', 'asc')
            ->limit(5) // Máximo 5 cuotas adelantadas
            ->get();

        $opciones = [];
        $acumulado = 0;
        $capitalAcumulado = 0;

        foreach ($cuotas as $index => $cuota) {
            // Usar montos pendientes para cuotas parciales
            $capitalCuota = $cuota->capital_pendiente ?? $cuota->capital_proyectado;
            $interesCuota = $cuota->interes_pendiente ?? $cuota->interes_proyectado;
            $moraCuota = $cuota->mora_pendiente ?? $cuota->mora_proyectada ?? 0;
            $otrosCuota = $cuota->otros_cargos_pendientes ?? $cuota->otros_cargos_proyectados ?? 0;

            $totalCuota = $capitalCuota + $interesCuota + $moraCuota + $otrosCuota;
            $acumulado += $totalCuota;
            $capitalAcumulado += $capitalCuota;

            $opciones[] = [
                'cuotas' => $index + 1,
                'descripcion' => ($index + 1) === 1 ? 'Cuota #' . $cuota->numero_cuota : 'Cuotas #' . ($cuotas->first()->numero_cuota) . ' a #' . $cuota->numero_cuota,
                'monto_total' => round($acumulado, 2),
                'capital_total' => round($capitalAcumulado, 2)
            ];
        }

        return $opciones;
    }

    /**
     * ============================================================================
     * EJECUTAR PAGO
     * ============================================================================
     * Tipos de pago soportados:
     * - CUOTA: Paga una cuota completa programada
     * - RENOVACION: Paga intereses y mora, extiende plazo, corre fechas, agrega cuotas
     * - ADELANTO: Paga cuotas futuras completas (reduce saldo)
     * - PARCIAL: Abono libre con prelación (mora → interés → capital)
     * - LIQUIDACION: Paga todo y cierra el crédito
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

            // Recalcular mora en cuotas vencidas antes de cualquier tipo de pago
            app(MoraService::class)->recalcularMoraCredito($credito);
            $credito->refresh();

            if (in_array($credito->estado, ['liquidado', 'anulado', 'vendido', 'pagado'])) {
                throw new \Exception("El crédito no está en un estado válido para recibir pagos.");
            }

            $tipo = $data['tipo'];
            $monto = (float) $data['monto'];

            return match ($tipo) {
                'CUOTA' => $this->procesarPagoCuotaCompleta($credito, $monto, $data),
                'RENOVACION' => $this->procesarRenovacion($credito, $monto, $data, $data['periodos'] ?? 1),
                'ADELANTO' => $this->procesarAdelanto($credito, $monto, $data, $data['cuotas'] ?? 1),
                'PARCIAL' => $this->procesarPagoParcial($credito, $monto, $data),
                'LIQUIDACION' => $this->procesarLiquidacion($credito, $monto, $data),
                default => throw new \Exception("Tipo de pago no válido: $tipo"),
            };
        });
    }

    /**
     * ============================================================================
     * PAGO CUOTA COMPLETA
     * ============================================================================
     * - Paga una cuota programada completa (capital + interés + mora + otros)
     * - Marca la cuota como PAGADA
     * - Reduce el saldo del crédito (por el capital pagado)
     * - NO modifica el número de cuotas ni fechas
     */
    private function procesarPagoCuotaCompleta(CreditoPrendario $credito, float $monto, array $data)
    {
        // 1. Buscar primera cuota PENDIENTE (saltar pagadas y renovadas)
        $cuota = CreditoPlanPago::where('credito_prendario_id', $credito->id)
            ->where('estado', 'pendiente')
            ->orderBy('numero_cuota', 'asc')
            ->first();

        if (!$cuota) {
            throw new \Exception("No hay cuotas pendientes para pagar");
        }

        // 2. Calcular total de la cuota
        $totalCuota = $cuota->capital_proyectado + $cuota->interes_proyectado +
                      ($cuota->mora_proyectada ?? 0) + ($cuota->otros_cargos_proyectados ?? 0);

        // 3. Validar monto suficiente
        if ($monto < ($totalCuota - 0.10)) {
            throw new \Exception("Monto insuficiente. Total cuota: Q" . number_format($totalCuota, 2));
        }

        // 4. Registrar movimiento
        $formaPago = $data['forma_pago'] ?? $data['metodo_pago'] ?? 'efectivo';
        $movimiento = CreditoMovimiento::create([
            'credito_prendario_id' => $credito->id,
            'tipo_movimiento' => 'pago',
            'fecha_movimiento' => now(),
            'fecha_registro' => now(),
            'monto_total' => $monto,
            'capital' => $cuota->capital_proyectado,
            'interes' => $cuota->interes_proyectado,
            'mora' => $cuota->mora_proyectada ?? 0,
            'otros_cargos' => $cuota->otros_cargos_proyectados ?? 0,
            'saldo_capital' => $credito->capital_pendiente - $cuota->capital_proyectado,
            'usuario_id' => Auth::id() ?? 1,
            'sucursal_id' => $credito->sucursal_id,
            'numero_movimiento' => Str::upper(Str::random(10)),
            'forma_pago' => $formaPago,
            'observaciones' => "PAGO CUOTA #{$cuota->numero_cuota} - " . ($data['observaciones'] ?? ''),
            'estado' => 'activo'
        ]);

        // 5. Marcar cuota como pagada
        $cuota->estado = 'pagada';
        $cuota->fecha_pago = now();
        $cuota->capital_pagado = $cuota->capital_proyectado;
        $cuota->interes_pagado = $cuota->interes_proyectado;
        $cuota->mora_pagada = $cuota->mora_proyectada ?? 0;
        $cuota->otros_cargos_pagados = $cuota->otros_cargos_proyectados ?? 0;
        $cuota->monto_total_pagado = $totalCuota;
        $cuota->capital_pendiente = 0;
        $cuota->interes_pendiente = 0;
        $cuota->mora_pendiente = 0;
        $cuota->otros_cargos_pendientes = 0;
        $cuota->monto_pendiente = 0;
        $cuota->save();

        // 6. Actualizar crédito
        $credito->capital_pendiente -= $cuota->capital_proyectado;
        $credito->capital_pagado += $cuota->capital_proyectado;
        $credito->interes_pagado += $cuota->interes_proyectado;
        $credito->mora_pagada += ($cuota->mora_proyectada ?? 0);
        $credito->fecha_ultimo_pago = now();

        // Si fue la última cuota, marcar como pagado
        // Solo considerar cuotas que aún requieren pago (no renovadas ni pagadas)
        $cuotasPendientes = CreditoPlanPago::where('credito_prendario_id', $credito->id)
            ->whereIn('estado', ['pendiente', 'pagada_parcial'])
            ->count();

        if ($cuotasPendientes == 0) {
            $credito->estado = 'pagado';
            $credito->fecha_cancelacion = now();
            $credito->prendas()->update(['estado' => 'recuperada']);
        }

        $credito->save();

        // 7. Registrar en caja
        $clienteNombre = $credito->cliente ? ($credito->cliente->nombres . ' ' . $credito->cliente->apellidos) : null;
        CajaService::registrarPagoCredito(
            $monto,
            $credito->numero_credito,
            'PAGO_CUOTA',
            $movimiento->numero_movimiento,
            $clienteNombre
        );

        return $movimiento;
    }

    /**
     * ============================================================================
     * RENOVACIÓN (CORREGIDA)
     * ============================================================================
     * - Paga intereses + mora para extender el plazo
     * - NO reduce el saldo (capital NO se paga)
     * - Corre todas las fechas del plan +N períodos hacia adelante
     * - Agrega N cuotas nuevas al final del plan
     */
    private function procesarRenovacion(CreditoPrendario $credito, float $monto, array $data, int $periodosRenovar = 1)
    {
        $calculo = $this->calcularDeudaAlDia($credito);

        // Calcular monto necesario para N períodos
        $interesPorPeriodo = $calculo['interes_por_periodo'];
        $minimoNecesario = $calculo['mora_acumulada'] + ($interesPorPeriodo * $periodosRenovar);

        if ($monto < ($minimoNecesario - 0.10)) {
            throw new \Exception("Monto insuficiente. Necesario para {$periodosRenovar} período(s): Q" . number_format($minimoNecesario, 2));
        }

        // 1. Registrar Movimiento
        $formaPago = $data['forma_pago'] ?? $data['metodo_pago'] ?? 'efectivo';
        $movimiento = CreditoMovimiento::create([
            'credito_prendario_id' => $credito->id,
            'tipo_movimiento' => 'renovacion',
            'fecha_movimiento' => now(),
            'fecha_registro' => now(),
            'monto_total' => $monto,
            'capital' => 0, // NO SE PAGA CAPITAL
            'interes' => min($monto - $calculo['mora_acumulada'], $interesPorPeriodo * $periodosRenovar),
            'mora' => $calculo['mora_acumulada'],
            'otros_cargos' => 0,
            'saldo_capital' => $credito->capital_pendiente, // SALDO NO CAMBIA
            'usuario_id' => Auth::id() ?? 1,
            'sucursal_id' => $credito->sucursal_id,
            'numero_movimiento' => Str::upper(Str::random(10)),
            'forma_pago' => $formaPago,
            'observaciones' => "RENOVACIÓN {$periodosRenovar} período(s) - " . ($data['observaciones'] ?? ''),
            'estado' => 'activo'
        ]);

        // 2. Actualizar contadores del crédito (pero NO el saldo de capital)
        $credito->interes_pagado = (float)$credito->interes_pagado + ($interesPorPeriodo * $periodosRenovar);
        $credito->mora_pagada = (float)$credito->mora_pagada + (float)$calculo['mora_acumulada'];
        $credito->interes_generado = (float)$credito->interes_generado + ($interesPorPeriodo * $periodosRenovar);
        $credito->mora_generada = (float)$credito->mora_generada + (float)$calculo['mora_acumulada'];
        $credito->fecha_ultimo_pago = now();
        $credito->dias_mora = 0;

        // 3. Calcular días por período según tipo de interés
        // Esto se usa para calcular las fechas de las nuevas cuotas

        $diasPorPeriodo = match($credito->tipo_interes) {
            'diario' => 1,
            'semanal' => 7,
            'catorcenal' => 14,
            'quincenal' => 15,
            'cada_28_dias' => 28,
            'mensual' => 30,
            default => 30
        };

        // 4. MARCAR cuotas pendientes como RENOVADAS
        // Las primeras N cuotas pendientes se marcan como "renovadas" porque
        // estás pagando el interés para extender su plazo
        $cuotasAMarcarRenovadas = CreditoPlanPago::where('credito_prendario_id', $credito->id)
            ->whereIn('estado', ['pendiente', 'pagada_parcial'])
            ->orderBy('numero_cuota', 'asc')
            ->limit($periodosRenovar)
            ->get();

        foreach ($cuotasAMarcarRenovadas as $cuota) {
            $cuota->estado = 'renovada';

            if ($cuota->tipo_modificacion === 'original') {
                $cuota->tipo_modificacion = 'refinanciamiento';
                $cuota->motivo_modificacion = "Renovada el " . now()->format('d/m/Y');
            } else {
                $cuota->motivo_modificacion .= " | Renovada el " . now()->format('d/m/Y');
            }

            $cuota->fecha_modificacion = now();
            $cuota->modificado_por = Auth::id() ?? 1;
            $cuota->save();
        }

        // 5. CREAR NUEVAS CUOTAS: Agregar N cuotas al final
        // Obtener la última cuota (puede ser renovada anterior)
        $ultimaCuotaExistente = CreditoPlanPago::where('credito_prendario_id', $credito->id)
            ->orderBy('numero_cuota', 'desc')
            ->first();

        if (!$ultimaCuotaExistente) {
            throw new \Exception("No se encontró ninguna cuota en el plan de pagos");
        }

        $numeroBase = $ultimaCuotaExistente->numero_cuota;
        $interesProyectado = $this->calcularInteresProyectado($credito);

        // Contar cuotas pendientes para redistribuir capital
        $cuotasPendientesCount = CreditoPlanPago::where('credito_prendario_id', $credito->id)
            ->whereIn('estado', ['pendiente', 'pagada_parcial'])
            ->count();

        // Capital por cuota = Capital pendiente / (Cuotas pendientes + Nuevas cuotas)
        $totalCuotasFuturas = $cuotasPendientesCount + $periodosRenovar;
        $capitalPorCuota = $totalCuotasFuturas > 0
            ? round($credito->capital_pendiente / $totalCuotasFuturas, 2)
            : $credito->capital_pendiente;

        // Calcular fecha base para las nuevas cuotas
        $fechaBaseNuevasCuotas = Carbon::parse($ultimaCuotaExistente->fecha_vencimiento);

        for ($i = 1; $i <= $periodosRenovar; $i++) {
            $numeroNuevaCuota = $numeroBase + $i;
            $fechaVencimiento = $fechaBaseNuevasCuotas->copy()->addDays($diasPorPeriodo * $i);

            CreditoPlanPago::create([
                'credito_prendario_id' => $credito->id,
                'numero_cuota' => $numeroNuevaCuota,
                'fecha_vencimiento' => $fechaVencimiento,
                'estado' => 'pendiente',
                'capital_proyectado' => $capitalPorCuota,
                'interes_proyectado' => $interesProyectado,
                'mora_proyectada' => 0,
                'otros_cargos_proyectados' => 0,
                'monto_cuota_proyectado' => $capitalPorCuota + $interesProyectado,
                'capital_pendiente' => $capitalPorCuota,
                'interes_pendiente' => $interesProyectado,
                'mora_pendiente' => 0,
                'otros_cargos_pendientes' => 0,
                'monto_pendiente' => $capitalPorCuota + $interesProyectado,
                'saldo_capital_credito' => $credito->capital_pendiente,
                'tipo_modificacion' => 'refinanciamiento',
                'motivo_modificacion' => "Renovación #{$i} - {$periodosRenovar} período(s) - " . now()->format('d/m/Y')
            ]);
        }

        // 6. Actualizar fecha de vencimiento del crédito y número de cuotas
        // La nueva fecha de vencimiento es la de la última cuota creada
        $ultimaCuotaCreada = CreditoPlanPago::where('credito_prendario_id', $credito->id)
            ->orderBy('numero_cuota', 'desc')
            ->first();

        if ($ultimaCuotaCreada) {
            $credito->fecha_vencimiento = $ultimaCuotaCreada->fecha_vencimiento;
        }
        $credito->numero_cuotas = (int)$credito->numero_cuotas + $periodosRenovar;
        $credito->estado = 'vigente';
        $credito->save();

        // 7. Registrar ingreso en caja
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
     * ============================================================================
     * ADELANTO (NUEVO)
     * ============================================================================
     * - Paga cuotas completas de períodos futuros
     * - SÍ reduce el saldo (se paga capital)
     * - Marca esas cuotas como PAGADAS
     * - NO agrega cuotas nuevas ni corre fechas
     */
    private function procesarAdelanto(CreditoPrendario $credito, float $monto, array $data, int $cuotasAdelantar = 1)
    {
        // 1. Encontrar la última cuota pagada completamente para empezar después de ella
        $ultimaCuotaPagada = CreditoPlanPago::where('credito_prendario_id', $credito->id)
            ->where('estado', 'pagada')
            ->orderBy('numero_cuota', 'desc')
            ->first();

        $numeroCuotaInicio = $ultimaCuotaPagada ? $ultimaCuotaPagada->numero_cuota : 0;

        // 2. Obtener cuotas pendientes DESPUÉS de la última pagada
        $cuotas = CreditoPlanPago::where('credito_prendario_id', $credito->id)
            ->where('numero_cuota', '>', $numeroCuotaInicio)
            ->whereIn('estado', ['pendiente', 'pagada_parcial'])
            ->where(function($q) {
                $q->where('monto_pendiente', '>', 0.01)
                  ->orWhereNull('monto_pendiente');
            })
            ->orderBy('numero_cuota', 'asc')
            ->limit($cuotasAdelantar)
            ->get();

        if ($cuotas->count() < $cuotasAdelantar) {
            $numeroCuotas = $cuotas->pluck('numero_cuota')->join(', ') ?: 'ninguna';
            throw new \Exception("No hay suficientes cuotas pendientes para adelantar después de la cuota #{$numeroCuotaInicio}. Disponibles: " . $cuotas->count() . " (Cuotas: {$numeroCuotas})");
        }

        // 2. Calcular total necesario (usar montos pendientes para cuotas parciales)
        $totalNecesario = $cuotas->sum(function($c) {
            return ($c->capital_pendiente ?? $c->capital_proyectado)
                 + ($c->interes_pendiente ?? $c->interes_proyectado)
                 + ($c->mora_pendiente ?? $c->mora_proyectada ?? 0)
                 + ($c->otros_cargos_pendientes ?? $c->otros_cargos_proyectados ?? 0);
        });

        if ($monto < ($totalNecesario - 0.10)) {
            throw new \Exception("Monto insuficiente. Necesario para {$cuotasAdelantar} cuota(s): Q" . number_format($totalNecesario, 2));
        }

        $capitalTotal = 0;
        $interesTotal = 0;
        $moraTotal = 0;
        $otrosTotal = 0;
        $cuotasProcesadas = [];

        // 3. Marcar cuotas como pagadas
        foreach ($cuotas as $cuota) {
            // Calcular montos a pagar (lo que falta)
            $capitalAPagar = $cuota->capital_pendiente ?? $cuota->capital_proyectado;
            $interesAPagar = $cuota->interes_pendiente ?? $cuota->interes_proyectado;
            $moraAPagar = $cuota->mora_pendiente ?? $cuota->mora_proyectada ?? 0;
            $otrosAPagar = $cuota->otros_cargos_pendientes ?? $cuota->otros_cargos_proyectados ?? 0;

            // Actualizar campos
            $cuota->estado = 'pagada';
            $cuota->fecha_pago = now();
            $cuota->capital_pagado = ($cuota->capital_pagado ?? 0) + $capitalAPagar;
            $cuota->interes_pagado = ($cuota->interes_pagado ?? 0) + $interesAPagar;
            $cuota->mora_pagada = ($cuota->mora_pagada ?? 0) + $moraAPagar;
            $cuota->otros_cargos_pagados = ($cuota->otros_cargos_pagados ?? 0) + $otrosAPagar;
            $cuota->monto_total_pagado = ($cuota->monto_total_pagado ?? 0) + $capitalAPagar + $interesAPagar + $moraAPagar + $otrosAPagar;
            $cuota->capital_pendiente = 0;
            $cuota->interes_pendiente = 0;
            $cuota->mora_pendiente = 0;
            $cuota->otros_cargos_pendientes = 0;
            $cuota->monto_pendiente = 0;
            $cuota->observaciones = ($cuota->observaciones ?? '') . " | Adelanto de {$cuotasAdelantar} cuota(s) - " . now()->format('Y-m-d H:i:s');

            // Guardar
            if (!$cuota->save()) {
                throw new \Exception("Error al guardar cuota #{$cuota->numero_cuota}");
            }

            $cuotasProcesadas[] = $cuota->numero_cuota;

            $capitalTotal += $capitalAPagar;
            $interesTotal += $interesAPagar;
            $capitalTotal += $capitalAPagar;
            $interesTotal += $interesAPagar;
            $moraTotal += $moraAPagar;
            $otrosTotal += $otrosAPagar;
        }

        // Verificar que se procesaron todas las cuotas solicitadas
        if (count($cuotasProcesadas) !== $cuotasAdelantar) {
            throw new \Exception("Error: Solo se procesaron " . count($cuotasProcesadas) . " de {$cuotasAdelantar} cuotas solicitadas");
        }

        // 4. Registrar movimiento
        $cuotasString = implode(', ', $cuotasProcesadas);
        $formaPago = $data['forma_pago'] ?? $data['metodo_pago'] ?? 'efectivo';
        $movimiento = CreditoMovimiento::create([
            'credito_prendario_id' => $credito->id,
            'tipo_movimiento' => 'pago_adelantado',
            'fecha_movimiento' => now(),
            'fecha_registro' => now(),
            'monto_total' => $monto,
            'capital' => $capitalTotal,
            'interes' => $interesTotal,
            'mora' => $moraTotal,
            'otros_cargos' => $otrosTotal,
            'saldo_capital' => $credito->capital_pendiente - $capitalTotal,
            'usuario_id' => Auth::id() ?? 1,
            'sucursal_id' => $credito->sucursal_id,
            'numero_movimiento' => Str::upper(Str::random(10)),
            'forma_pago' => $formaPago,
            'observaciones' => "ADELANTO {$cuotasAdelantar} cuota(s) - Cuotas procesadas: {$cuotasString} - " . ($data['observaciones'] ?? ''),
            'estado' => 'activo'
        ]);

        // 5. Actualizar crédito
        $credito->capital_pendiente -= $capitalTotal;
        $credito->capital_pagado += $capitalTotal;
        $credito->interes_pagado += $interesTotal;
        $credito->mora_pagada += $moraTotal;
        $credito->fecha_ultimo_pago = now();

        // Si no quedan cuotas pendientes, marcar como pagado
        $pendientes = CreditoPlanPago::where('credito_prendario_id', $credito->id)
            ->where('estado', 'pendiente')
            ->count();

        if ($pendientes == 0) {
            $credito->estado = 'pagado';
            $credito->fecha_cancelacion = now();
            $credito->prendas()->update(['estado' => 'recuperada']);
        }

        $credito->save();

        // 6. Registrar en caja
        $clienteNombre = $credito->cliente ? ($credito->cliente->nombres . ' ' . $credito->cliente->apellidos) : null;
        CajaService::registrarPagoCredito(
            $monto,
            $credito->numero_credito,
            'ADELANTO',
            $movimiento->numero_movimiento,
            $clienteNombre
        );

        return $movimiento;
    }

    /**
     * Actualiza el plan de pagos al renovar (MÉTODO OBSOLETO - Ya no se usa)
     * @deprecated Reemplazado por lógica directa en procesarRenovacion()
     */
    private function actualizarPlanPagosPorRenovacion(CreditoPrendario $credito, array $calculo, Carbon $nuevoVencimiento)
    {
        // Buscar cuota actual (primera pendiente)
        $cuotaActual = CreditoPlanPago::where('credito_prendario_id', $credito->id)
            ->where('estado', '!=', 'pagada')
            ->orderBy('numero_cuota', 'asc')
            ->first();

        $numeroNuevaCuota = 1;

        if ($cuotaActual) {
            // Marcar cuota actual como pagada (solo intereses y mora)
            $cuotaActual->estado = 'pagada';
            $cuotaActual->interes_pagado = (float)($cuotaActual->interes_pagado ?? 0) + (float)$calculo['interes_acumulado'];
            $cuotaActual->mora_pagada = (float)($cuotaActual->mora_pagada ?? 0) + (float)$calculo['mora_acumulada'];
            $cuotaActual->fecha_pago = now();
            $cuotaActual->interes_pendiente = 0;
            $cuotaActual->mora_pendiente = 0;
            // NO se paga capital, solo se marca como pagada por los intereses
            $cuotaActual->observaciones = ($cuotaActual->observaciones ?? '') . " | Renovado (pago de intereses) el " . now()->format('d/m/Y');
            $cuotaActual->save();

            $numeroNuevaCuota = $cuotaActual->numero_cuota + 1;
        } else {
            $max = CreditoPlanPago::where('credito_prendario_id', $credito->id)->max('numero_cuota');
            $numeroNuevaCuota = ($max ?? 0) + 1;
        }

        // Calcular interés proyectado para el nuevo periodo
        $interesProyectadoNuevo = $this->calcularInteresProyectado($credito);

        // Buscar si ya existe una cuota con el siguiente número
        $cuotaSiguiente = CreditoPlanPago::where('credito_prendario_id', $credito->id)
            ->where('numero_cuota', $numeroNuevaCuota)
            ->first();

        if ($cuotaSiguiente) {
            // Si ya existe, actualizar la cuota existente
            $cuotaSiguiente->fecha_vencimiento = $nuevoVencimiento;
            $cuotaSiguiente->estado = 'pendiente';
            $cuotaSiguiente->capital_proyectado = $credito->capital_pendiente;
            $cuotaSiguiente->interes_proyectado = $interesProyectadoNuevo;
            $cuotaSiguiente->mora_proyectada = 0;
            $cuotaSiguiente->monto_cuota_proyectado = $credito->capital_pendiente + $interesProyectadoNuevo;
            $cuotaSiguiente->capital_pendiente = $credito->capital_pendiente;
            $cuotaSiguiente->interes_pendiente = $interesProyectadoNuevo;
            $cuotaSiguiente->monto_pendiente = $credito->capital_pendiente + $interesProyectadoNuevo;
            $cuotaSiguiente->saldo_capital_credito = $credito->capital_pendiente;
            $cuotaSiguiente->tipo_modificacion = 'refinanciamiento';
            $cuotaSiguiente->motivo_modificacion = 'Renovación de plazo - Actualización de cuota existente';
            $cuotaSiguiente->save();
        } else {
            // Si no existe, crear nueva cuota
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
        $formaPago = $data['forma_pago'] ?? $data['metodo_pago'] ?? 'efectivo';
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
            'forma_pago' => $formaPago,
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
     * PAGO PARCIAL (ABONO) - MEJORADO
     * ============================================================================
     * Aplica prelación: 1. Mora, 2. Interés, 3. Capital
     * Distribuye el pago entre TODAS las cuotas vencidas si hay varias
     */
    private function procesarPagoParcial(CreditoPrendario $credito, float $monto, array $data)
    {
        // 1. Obtener todas las cuotas con montos pendientes
        $cuotasPendientes = CreditoPlanPago::where('credito_prendario_id', $credito->id)
            ->where('estado', '!=', 'pagada')
            ->orderBy('numero_cuota', 'asc')
            ->get();

        if ($cuotasPendientes->isEmpty()) {
            throw new \Exception("No hay cuotas pendientes para aplicar el abono");
        }

        // 2. Calcular deuda total por concepto (prelación)
        $moraTotalPendiente = 0;
        $interesTotalPendiente = 0;
        $capitalTotalPendiente = 0;

        foreach ($cuotasPendientes as $cuota) {
            // Recalcular mora en memoria si está vencida (por si no se persigió antes)
            if ($cuota->fecha_vencimiento && now()->gt($cuota->fecha_vencimiento)) {
                $diasMora = (int) abs(now()->diffInDays($cuota->fecha_vencimiento));
                $diasGracia = $credito->dias_gracia ?? 0;
                $diasMoraEfectivos = max(0, $diasMora - $diasGracia);
                if ($diasMoraEfectivos > 0) {
                    $tipoMora = $credito->tipo_mora ?? 'porcentaje';
                    if ($tipoMora === 'monto_fijo' && ($credito->mora_monto_fijo ?? 0) > 0) {
                        $cuota->mora_proyectada = (float) $credito->mora_monto_fijo * $diasMoraEfectivos;
                    } elseif ($tipoMora === 'porcentaje' && ($credito->tasa_mora ?? 0) > 0) {
                        $tasaMoraDiaria = ($credito->tasa_mora / 100) / 30;
                        $cuota->mora_proyectada = $credito->capital_pendiente * $tasaMoraDiaria * $diasMoraEfectivos;
                    }
                }
            }

            $moraTotalPendiente += max(0, ($cuota->mora_proyectada ?? 0) - ($cuota->mora_pagada ?? 0));
            $interesTotalPendiente += max(0, $cuota->interes_proyectado - ($cuota->interes_pagado ?? 0));
            $capitalTotalPendiente += max(0, $cuota->capital_proyectado - ($cuota->capital_pagado ?? 0));
        }

        // 3. Aplicar prelación al monto del abono
        $pagoMora = min($monto, $moraTotalPendiente);
        $remanente = $monto - $pagoMora;

        $pagoInteres = min($remanente, $interesTotalPendiente);
        $remanente = $remanente - $pagoInteres;

        $pagoCapital = min($remanente, $capitalTotalPendiente);

        // 4. Registrar Movimiento
        $formaPago = $data['forma_pago'] ?? $data['metodo_pago'] ?? 'efectivo';
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
            'forma_pago' => $formaPago,
            'observaciones' => "ABONO - Mora: Q" . number_format($pagoMora, 2) .
                             ", Interés: Q" . number_format($pagoInteres, 2) .
                             ", Capital: Q" . number_format($pagoCapital, 2) .
                             " - " . ($data['observaciones'] ?? ''),
            'estado' => 'activo'
        ]);

        // 5. Actualizar Crédito
        $credito->capital_pendiente = max(0, (float)$credito->capital_pendiente - (float)$pagoCapital);
        $credito->capital_pagado = (float)$credito->capital_pagado + (float)$pagoCapital;
        $credito->interes_pagado = (float)$credito->interes_pagado + (float)$pagoInteres;
        $credito->mora_pagada = (float)$credito->mora_pagada + (float)$pagoMora;
        $credito->fecha_ultimo_pago = now();

        // Si cubrió toda la mora, resetear días mora
        if ($pagoMora >= $moraTotalPendiente - 0.10) {
            $credito->dias_mora = 0;
        }

        $credito->save();

        // 6. Distribuir el pago entre las cuotas (prelación por cuota)
        $this->distribuirAbonoPrelacion($cuotasPendientes, $pagoMora, $pagoInteres, $pagoCapital, $credito);

        // 6.5 IMPORTANTE: Si se pagó capital, recalcular cuotas futuras con el nuevo saldo
        if ($pagoCapital > 0.10) {
            $this->recalcularCuotasFuturas($credito);
        }

        // 7. Registrar en caja
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
     * Distribuye el abono entre cuotas aplicando prelación
     * Primero cubre toda la mora de todas las cuotas,
     * luego todo el interés, y finalmente el capital
     */
    private function distribuirAbonoPrelacion($cuotas, float $pagoMora, float $pagoInteres, float $pagoCapital, CreditoPrendario $credito)
    {
        $moraRestante = $pagoMora;
        $interesRestante = $pagoInteres;
        $capitalRestante = $pagoCapital;

        foreach ($cuotas as $cuota) {
            // 1. Aplicar MORA
            $moraPendiente = max(0, ($cuota->mora_proyectada ?? 0) - ($cuota->mora_pagada ?? 0));
            $moraAAplicar = min($moraRestante, $moraPendiente);

            if ($moraAAplicar > 0) {
                $cuota->mora_pagada = (float)($cuota->mora_pagada ?? 0) + $moraAAplicar;
                $cuota->mora_pendiente = max(0, ($cuota->mora_proyectada ?? 0) - $cuota->mora_pagada);
                $moraRestante -= $moraAAplicar;
            }

            // 2. Aplicar INTERÉS
            $interesPendiente = max(0, $cuota->interes_proyectado - ($cuota->interes_pagado ?? 0));
            $interesAAplicar = min($interesRestante, $interesPendiente);

            if ($interesAAplicar > 0) {
                $cuota->interes_pagado = (float)($cuota->interes_pagado ?? 0) + $interesAAplicar;
                $cuota->interes_pendiente = max(0, $cuota->interes_proyectado - $cuota->interes_pagado);
                $interesRestante -= $interesAAplicar;
            }

            // 3. Aplicar CAPITAL
            $capitalPendiente = max(0, $cuota->capital_proyectado - ($cuota->capital_pagado ?? 0));
            $capitalAAplicar = min($capitalRestante, $capitalPendiente);

            if ($capitalAAplicar > 0) {
                $cuota->capital_pagado = (float)($cuota->capital_pagado ?? 0) + $capitalAAplicar;
                $cuota->capital_pendiente = max(0, $cuota->capital_proyectado - $cuota->capital_pagado);
                $capitalRestante -= $capitalAAplicar;
            }

            // 4. Actualizar totales y estado
            $cuota->monto_total_pagado = ($cuota->capital_pagado ?? 0) +
                                         ($cuota->interes_pagado ?? 0) +
                                         ($cuota->mora_pagada ?? 0);

            $cuota->monto_pendiente = $cuota->capital_pendiente +
                                     $cuota->interes_pendiente +
                                     ($cuota->mora_pendiente ?? 0);

            $cuota->saldo_capital_credito = $credito->capital_pendiente;

            // Determinar estado
            if ($cuota->monto_pendiente <= 0.10) {
                $cuota->estado = 'pagada';
                $cuota->fecha_pago = now();
            } elseif ($cuota->monto_total_pagado > 0.10) {
                $cuota->estado = 'pagada_parcial';
            }

            $cuota->save();

            // Si ya no queda nada por aplicar, salir del loop
            if ($moraRestante <= 0.01 && $interesRestante <= 0.01 && $capitalRestante <= 0.01) {
                break;
            }
        }
    }

    /**
     * Recalcula las cuotas futuras con el nuevo capital pendiente del crédito
     * Esto se ejecuta después de un abono que reduce el capital
     */
    private function recalcularCuotasFuturas(CreditoPrendario $credito)
    {
        // Obtener cuotas futuras que NO han sido pagadas (pendiente o renovada)
        $cuotasFuturas = CreditoPlanPago::where('credito_prendario_id', $credito->id)
            ->whereIn('estado', ['pendiente', 'renovada'])
            ->orderBy('numero_cuota', 'asc')
            ->get();

        foreach ($cuotasFuturas as $cuota) {
            // Actualizar capital proyectado con el nuevo saldo del crédito
            $cuota->capital_proyectado = $credito->capital_pendiente;
            $cuota->capital_pendiente = max(0, $credito->capital_pendiente - ($cuota->capital_pagado ?? 0));

            // Recalcular monto total de la cuota
            $cuota->monto_cuota_proyectado = $cuota->capital_proyectado +
                                            $cuota->interes_proyectado +
                                            ($cuota->mora_proyectada ?? 0) +
                                            ($cuota->otros_cargos_proyectados ?? 0);

            // Recalcular monto pendiente
            $cuota->monto_pendiente = $cuota->capital_pendiente +
                                     $cuota->interes_pendiente +
                                     ($cuota->mora_pendiente ?? 0) +
                                     ($cuota->otros_cargos_pendientes ?? 0);

            // Actualizar saldo de capital del crédito en esta cuota
            $cuota->saldo_capital_credito = $credito->capital_pendiente;

            $cuota->save();
        }
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
        $formaPago = $data['forma_pago'] ?? $data['metodo_pago'] ?? 'efectivo';
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
            'forma_pago' => $formaPago,
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
            // Actualizar saldo de capital del crédito
            $cuota->saldo_capital_credito = $credito->capital_pendiente;

            // Calcular capital amortizado (diferencia entre proyectado y pendiente actual)
            $capitalAmortizado = max(0, $cuota->capital_proyectado - $credito->capital_pendiente);

            if ($capitalAmortizado > ($cuota->capital_pagado ?? 0)) {
                $cuota->capital_pagado = $capitalAmortizado;
                $cuota->capital_pendiente = max(0, $cuota->capital_proyectado - $capitalAmortizado);
            }

            // Determinar estado de la cuota
            if ($credito->capital_pendiente <= 0.10) {
                $cuota->estado = 'pagada';
                $cuota->fecha_pago = now();
                $cuota->capital_pendiente = 0;
            } elseif ($cuota->capital_pagado > 0.10) {
                $cuota->estado = 'pagada_parcial';
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
            $formaPago = $data['forma_pago'] ?? $data['metodo_pago'] ?? 'efectivo';
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
                'forma_pago' => $formaPago,
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
