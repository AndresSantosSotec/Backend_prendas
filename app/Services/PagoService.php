<?php

namespace App\Services;

use App\Models\CreditoPrendario;
use App\Models\CreditoMovimiento;
use App\Models\CreditoPlanPago;
use App\Models\IdempotencyKey;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PagoService
{
    /**
     * Calcula la deuda actual y proyecciones de un crédito.
     */
    public function calcularDeudaAlDia(CreditoPrendario $credito, Carbon $fechaCalculo = null)
    {
        $fechaCalculo = $fechaCalculo ?? Carbon::now();
        
        // Lógica de cálculo: Periodo Completo (Mes iniciado, mes cobrado)
        $diasTranscurridos = $fechaCalculo->diffInDays($credito->fecha_ultimo_pago ?? $credito->fecha_desembolso);
        if ($diasTranscurridos == 0) $diasTranscurridos = 1; // Evitar división por cero o cobro cero

        // Determinar periodos a cobrar según tipo de interés
        $tasaInteres = $credito->tasa_interes / 100;
        $periodosCobrar = 0;

        switch ($credito->tipo_interes) {
            case 'semanal':
                $periodosCobrar = ceil($diasTranscurridos / 7);
                break;
            case 'quincenal':
                $periodosCobrar = ceil($diasTranscurridos / 15);
                break;
            case 'diario':
                $periodosCobrar = $diasTranscurridos;
                break;
            case 'mensual':
            default:
                // Por defecto mensual: si van 1 dia, cobra 1 mes. Si van 31 dias, cobra 2 meses.
                $periodosCobrar = ceil($diasTranscurridos / 30);
                break;
        }

        // Interés Devengado = Capital * Tasa * Periodos
        $interesDevengado = $credito->capital_pendiente * $tasaInteres * $periodosCobrar;

        // Mora (Se mantiene diaria si aplica, o se puede ajustar reglas después)
        // Por ahora mantenemos mora diaria para ser justos, o ajustamos si usuario pide cambios específicos en mora.
        $moraDevengada = 0;
        if ($fechaCalculo->gt($credito->fecha_vencimiento)) {
            $diasMora = $fechaCalculo->diffInDays($credito->fecha_vencimiento) - $credito->dias_gracia;
            if ($diasMora > 0) {
                 $tasaMoraDiaria = ($credito->tasa_mora / 100) / 30;
                 $moraDevengada = $credito->capital_pendiente * $tasaMoraDiaria * $diasMora;
            }
        }

        $totalPagar = $credito->capital_pendiente + $credito->interes_pendiente + $interesDevengado + $credito->mora_pendiente + $moraDevengada;

        return [
            'fecha_calculo' => $fechaCalculo->format('Y-m-d'),
            'dias_transcurridos' => $diasTranscurridos,
            'periodos_cobrados' => $periodosCobrar,
            'tipo_periodo' => $credito->tipo_interes,
            'capital_pendiente' => $credito->capital_pendiente,
            'interes_acumulado' => round($credito->interes_pendiente + $interesDevengado, 2),
            'mora_acumulada' => round($credito->mora_pendiente + $moraDevengada, 2),
            'total_para_liquidar' => round($totalPagar, 2),
            'minimo_renovacion' => round($credito->interes_pendiente + $interesDevengado + $credito->mora_pendiente + $moraDevengada, 2),
        ];
    }

    public function ejecutarPago(array $data)
    {
        return DB::transaction(function () use ($data) {
            // Idempotencia
            if (isset($data['idempotency_key'])) {
                // Fix: Columna es key_hash, no key
                $existe = IdempotencyKey::where('key_hash', $data['idempotency_key'])->first();
                if ($existe) {
                    throw new \Exception("Esta operación ya fue procesada.");
                }
                IdempotencyKey::create([
                    'key_hash' => $data['idempotency_key'], 
                    'payload' => json_encode($data),
                    'operacion' => 'pago' // Campo requerido según migración
                ]);
            }

            $credito = CreditoPrendario::lockForUpdate()->find($data['credito_id']); // Lock pesimista
            
            if (!$credito) throw new \Exception("Crédito no encontrado");
            if (in_array($credito->estado, ['liquidado', 'anulado', 'vendido'])) {
                throw new \Exception("El crédito no está en un estado válido para recibir pagos.");
            }

            $tipo = $data['tipo'];
            $monto = (float) $data['monto'];

            // Switch tipo pago
            if ($tipo === 'RENOVACION') {
                return $this->procesarRenovacion($credito, $monto, $data);
            } elseif ($tipo === 'PARCIAL') {
                return $this->procesarPagoParcial($credito, $monto, $data);
            } elseif ($tipo === 'LIQUIDACION') {
                return $this->procesarLiquidacion($credito, $monto, $data);
            }

            throw new \Exception("Tipo de pago no válido");
        });
    }

    private function procesarRenovacion(CreditoPrendario $credito, float $monto, array $data)
    {
        $calculo = $this->calcularDeudaAlDia($credito);
        $minimo = $calculo['minimo_renovacion'];

        // Permitir margen de error pequeño
        if ($monto < ($minimo - 0.10)) {
            throw new \Exception("El monto es insuficiente para renovación. Mínimo: Q{$minimo}");
        }

        // Registrar Movimiento
        $movimiento = CreditoMovimiento::create([
            'credito_prendario_id' => $credito->id,
            'tipo_movimiento' => 'pago_interes', // Específico para renovación
            'fecha_movimiento' => now(),
            'fecha_registro' => now(),
            'monto_total' => $monto,
            'capital' => 0, // Renovación pura no baja capital
            'interes' => $calculo['interes_acumulado'],
            'mora' => $calculo['mora_acumulada'],
            'otros_cargos' => 0,
            'saldo_capital' => $credito->capital_pendiente,
            'usuario_id' => auth()->id() ?? 1,
            'sucursal_id' => $credito->sucursal_id,
            'numero_movimiento' => Str::upper(Str::random(10)),
            'observaciones' => "RENOVACION - " . ($data['observaciones'] ?? ''),
            'estado' => 'activo'
        ]);

        // Actualizar Crédito
        $credito->interes_pagado += $calculo['interes_acumulado'];
        $credito->mora_pagada += $calculo['mora_acumulada'];
        $credito->interes_generado += $calculo['interes_acumulado']; // Sumar al generado histórico
        $credito->mora_generada += $calculo['mora_acumulada']; // Sumar al generado histórico
        $credito->fecha_ultimo_pago = now();
        
        // Extender fecha vencimiento
        $nuevoVencimiento = Carbon::parse($credito->fecha_vencimiento)->addDays($credito->plazo_dias);
        $credito->fecha_vencimiento = $nuevoVencimiento;
        $credito->estado = 'vigente'; 
        
        // Incrementar contador renovaciones
        $credito->renovaciones = ($credito->renovaciones ?? 0) + 1;
        $credito->save();

        // **Lógica Plan de Pagos: Historial de Renovación**
        
        // 1. Buscar cuota actual (la que se está renovando)
        $cuotaActual = CreditoPlanPago::where('credito_prendario_id', $credito->id)
                        ->where('estado', '!=', 'pagada')
                        ->orderBy('numero_cuota', 'asc')
                        ->first();

        $numeroNuevaCuota = 1;

        if ($cuotaActual) {
            // Marcar cuota actual como pagada (se pagaron los intereses exigibles)
            // No se paga capital, pero se "cierra" este periodo.
            $cuotaActual->estado = 'pagada';
            $cuotaActual->interes_pagado += $calculo['interes_acumulado'];
            $cuotaActual->mora_pagada += $calculo['mora_acumulada'];
            // El capital pagado es 0, pero el saldo pendiente de la cuota se asume 0 porque se "patea" al futuro
            $cuotaActual->capital_pendiente = 0; // Se transfiere a la nueva
            $cuotaActual->monto_pendiente = 0;
            $cuotaActual->observaciones .= " | Renovado el " . now()->format('d/m/Y');
            $cuotaActual->save();

            $numeroNuevaCuota = $cuotaActual->numero_cuota + 1;
        } else {
             // Si no hay cuota (caso raro), buscar max numero
             $max = CreditoPlanPago::where('credito_prendario_id', $credito->id)->max('numero_cuota');
             $numeroNuevaCuota = ($max ?? 0) + 1;
        }

        // 2. Crear nueva cuota para el nuevo periodo
        // Proyección básica: Mismo capital, Interés estimado para el nuevo plazo
        $tasaInteresDecimal = $credito->tasa_interes / 100;
        // Asumir mensual por defecto para la proyección
        $interesProyectadoNuevo = $credito->capital_pendiente * $tasaInteresDecimal; // 1 mes
        if ($credito->tipo_interes == 'diario') $interesProyectadoNuevo *= $credito->plazo_dias;
        // etc... simplificado

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

        return $movimiento;
    }

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
            'saldo_capital' => $credito->capital_pendiente - $pagoCapital,
            'usuario_id' => auth()->id() ?? 1,
            'sucursal_id' => $credito->sucursal_id,
            'numero_movimiento' => Str::upper(Str::random(10)),
            'observaciones' => "ABONO CAPITAL - " . ($data['observaciones'] ?? ''),
            'estado' => 'activo'
        ]);

        // Actualizar Crédito
        $credito->capital_pendiente -= $pagoCapital;
        $credito->capital_pagado += $pagoCapital;
        $credito->interes_pagado += $pagoInteres;
        $credito->mora_pagada += $pagoMora;
        $credito->fecha_ultimo_pago = now();
        $credito->save();

        // **NUEVO**: Actualizar Plan de Pagos
        // Intentar cubrir la cuota pendiente más antigua con el pago realizado
        $this->actualizarPlanPagos($credito, $monto);

        return $movimiento;
    }

    private function procesarLiquidacion(CreditoPrendario $credito, float $monto, array $data)
    {
        $calculo = $this->calcularDeudaAlDia($credito);
        $totalDeuda = $calculo['total_para_liquidar'];
        
        // Permitir un margen pequeño por redondeo (e.g. 0.10)
        if ($monto < ($totalDeuda - 0.10)) {
            throw new \Exception("Monto insuficiente para liquidar. Total requerido: Q{$totalDeuda}");
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
            'saldo_capital' => 0,
            'usuario_id' => auth()->id() ?? 1,
            'sucursal_id' => $credito->sucursal_id,
            'numero_movimiento' => Str::upper(Str::random(10)),
            'observaciones' => "LIQUIDACION TOTAL - " . ($data['observaciones'] ?? ''),
            'estado' => 'activo'
        ]);

        // Actualizar Crédito
        $credito->capital_pendiente = 0;
        $credito->capital_pagado += $pagoCapital;
        $credito->interes_pagado += $pagoInteres;
        $credito->mora_pagada += $pagoMora;
        $credito->fecha_ultimo_pago = now();
        $credito->fecha_cancelacion = now();
        $credito->estado = 'pagado'; // FIX: Usar 'pagado' en lugar de 'liquidado'
        $credito->save();

        // Liberar Prendas (Lógica simplificada, actualizaría tabla prendas)
        $credito->prendas()->update(['estado' => 'recuperada']);

        // Marcar todas las cuotas pendientes como pagadas
        $this->liquidarPlanPagos($credito);

        return $movimiento;
    }

    /**
     * Aplica el monto pagado a las cuotas del plan de pagos.
     */
    private function actualizarPlanPagos(CreditoPrendario $credito, float $montoPagado)
    {
        // Buscar cuota pendiente más próxima
        $cuota = CreditoPlanPago::where('credito_prendario_id', $credito->id)
                    ->where('estado', '!=', 'pagada')
                    ->orderBy('numero_cuota', 'asc')
                    ->first();

        if ($cuota) {
            // Actualizar saldo del crédito en la cuota (foto del momento)
            $cuota->saldo_capital_credito = $credito->capital_pendiente;
            
            // Distribuir abono (simplificado)
            // Si hubo pago de intereses, sumar a lo pagado en cuota
            // Nota: Esto es imperfecto si no tenemos el desglose exacto del pago actual aqui,
            // pero asumimos que el saldo del crédito ya bajó.
            
            // Si el saldo de capital del crédito es menor al proyectado inicial de la cuota + margen,
            // significa que hemos pagado parte del capital de esta cuota.
            // Ojo: Esto asume cuota única o final.
            
            $capitalAmortizado = max(0, $cuota->capital_proyectado - $credito->capital_pendiente);
            
            // Solo actualizar si hay cambio positivo
            if ($capitalAmortizado > $cuota->capital_pagado) {
                 $cuota->capital_pagado = $capitalAmortizado;
                 $cuota->capital_pendiente = max(0, $cuota->capital_proyectado - $capitalAmortizado);
            }
            
            // Si saldo capital es 0 o muy bajo, pagada
            if ($credito->capital_pendiente <= 0.10) {
                $cuota->estado = 'pagada';
                $cuota->capital_pendiente = 0;
            } else {
                $cuota->estado = 'pendiente'; 
            }
            $cuota->save();
        }
    }

    public function reactivar(CreditoPrendario $credito, array $data)
    {
        return DB::transaction(function () use ($credito, $data) {
            if ($credito->estado !== 'pagado' && $credito->estado !== 'liquidado') {
                throw new \Exception("Solo se pueden reactivar créditos pagados o liquidados.");
            }

            // Datos para el nuevo ciclo
            $monto = $credito->monto_aprobado; // Asumimos mismo monto, o podría venir en $data
            
            // 1. Registrar Movimiento de Desembolso (Salida de dinero)
            $movimiento = CreditoMovimiento::create([
                'credito_prendario_id' => $credito->id,
                'tipo_movimiento' => 'desembolso',
                'fecha_movimiento' => now(),
                'fecha_registro' => now(),
                'monto_total' => $monto,
                'capital' => $monto,
                'interes' => 0,
                'mora' => 0,
                'saldo_capital' => $monto,
                'usuario_id' => auth()->id() ?? 1,
                'sucursal_id' => $credito->sucursal_id,
                'numero_movimiento' => Str::upper(Str::random(10)),
                'observaciones' => "REACTIVACIÓN / REEMPEÑO - " . ($data['observaciones'] ?? ''),
                'estado' => 'activo'
            ]);

            // 2. Actualizar Crédito (Resetear contadores del nuevo ciclo)
            // No reseteamos 'pagado' histórico, pero si el 'pendiente'
            $credito->capital_pendiente = $monto;
            // interes_pendiente y mora_pendiente se asumen 0 al inicio
            $credito->interes_pendiente = 0;
            $credito->mora_pendiente = 0;
            
            $credito->fecha_desembolso = now(); // Nueva fecha desembolso
            $credito->fecha_vencimiento = now()->addDays($credito->plazo_dias);
            $credito->fecha_ultimo_pago = null;
            $credito->fecha_cancelacion = null;
            $credito->estado = 'vigente';
            $credito->renovaciones = 0; // Reset renovaciones cycle
            $credito->save();

            // 3. Actualizar Prendas
            $credito->prendas()->update(['estado' => 'en_custodia']);

            // 4. Generar Nueva Cuota (Plan de Pagos)
            // Obtener siguiente numero
            $maxCuota = CreditoPlanPago::where('credito_prendario_id', $credito->id)->max('numero_cuota');
            $nuevoNumero = ($maxCuota ?? 0) + 1;

            $tasaInteresDecimal = $credito->tasa_interes / 100;
            $interesProyectado = $monto * $tasaInteresDecimal; 

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
                'tipo_modificacion' => 'reestructuracion', // o nuevo origen
                'motivo_modificacion' => 'Reactivación de crédito'
            ]);

            return $movimiento;
        });
    }

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
                'saldo_capital_credito' => 0 // Asumiendo que se liquidó todo
            ]);
    }
}
