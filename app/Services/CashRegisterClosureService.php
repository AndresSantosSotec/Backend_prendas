<?php

namespace App\Services;

use App\Models\CajaAperturaCierre;
use App\Models\MovimientoCaja;
use App\Models\Boveda;
use App\Models\BovedaMovimiento;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CashRegisterClosureService
{
    /**
     * Cierra una caja y, opcionalmente, transfiere el saldo final a una bóveda.
     *
     * Retorna un arreglo con:
     *  - [0] => instancia de CajaAperturaCierre ya cerrada
     *  - [1] => instancia de BovedaMovimiento creada (o null si no hubo transferencia)
     */
    public function closeAndTransferToVault(CajaAperturaCierre $caja, array $data): array
    {
        return DB::transaction(function () use ($caja, $data) {
            // Recalcular saldo esperado del sistema basado en movimientos aplicados
            $totalMovimientos = MovimientoCaja::where('caja_id', $caja->id)
                ->where('estado', 'aplicado')
                ->selectRaw("
                    SUM(
                        CASE
                            WHEN tipo IN ('incremento', 'ingreso_pago') THEN monto
                            ELSE -monto
                        END
                    ) as total
                ")
                ->value('total');

            $saldoSistema = (float) $caja->saldo_inicial + (float) ($totalMovimientos ?? 0);

            $montoContado = (float) ($data['monto_total_efectivo'] ?? 0);
            $diferencia = $montoContado - $saldoSistema;

            $resultado = 'Cuadra perfectamente';
            if ($diferencia > 0) {
                $resultado = 'Sobrante';
            } elseif ($diferencia < 0) {
                $resultado = 'Faltante';
            }

            // Armar detalles de arqueo unificados (manteniendo compatibilidad con lo existente)
            $detallesArqueo = $caja->detalles_arqueo ?? [];
            if (!is_array($detallesArqueo)) {
                $detallesArqueo = [];
            }

            $detallesArqueo = array_merge($detallesArqueo, [
                'monto_total_efectivo' => $montoContado,
                'saldo_esperado_sistema' => $saldoSistema,
                'diferencia_calculada' => $diferencia,
                'resultado_arqueo' => $resultado,
                'desglose_denominaciones' => $data['desglose_denominaciones'] ?? null,
                'enviar_a_boveda' => (bool) ($data['enviar_a_boveda'] ?? false),
                'boveda_destino_id' => $data['boveda_destino_id'] ?? null,
                'observaciones_cierre' => $data['observaciones'] ?? null,
            ]);

            // Actualizar datos de cierre de caja
            $caja->saldo_final = $montoContado;
            $caja->fecha_cierre = now();
            $caja->diferencia = $diferencia;
            $caja->resultado_arqueo = $resultado;
            $caja->detalles_arqueo = $detallesArqueo;
            $caja->estado = 'cerrada';

            // Solo guardar boveda_destino_id cuando realmente se solicita transferencia
            $enviarABoveda = (bool) ($data['enviar_a_boveda'] ?? false);
            $bovedaDestinoId = $enviarABoveda ? ($data['boveda_destino_id'] ?? null) : null;
            if ($enviarABoveda && $bovedaDestinoId) {
                $caja->boveda_destino_id = $bovedaDestinoId;
            }

            $caja->save();

            $movimientoBoveda = null;

            // Si se solicita transferencia a bóveda, registrar movimiento de ingreso por cierre diario
            if ($enviarABoveda && $bovedaDestinoId) {
                /** @var Boveda $boveda */
                $boveda = Boveda::lockForUpdate()->findOrFail($bovedaDestinoId);

                // Validar que la bóveda pueda recibir el monto final
                if (!$boveda->activa) {
                    throw new \RuntimeException('La bóveda destino no está activa.');
                }

                if (!$boveda->puedeRecibirMonto($montoContado)) {
                    throw new \RuntimeException('El monto excede el saldo máximo permitido de la bóveda destino.');
                }

                $usuario = Auth::user();
                $cajeroId = $data['cajero_id'] ?? $caja->user_id;

                // Registrar un movimiento de bóveda con tipo específico para cierres diarios
                $movimientoBoveda = BovedaMovimiento::create([
                    'boveda_id' => $boveda->id,
                    'usuario_id' => $cajeroId,
                    'sucursal_id' => $boveda->sucursal_id,
                    'tipo_movimiento' => 'ingreso_cierre_diario',
                    'monto' => $montoContado,
                    'concepto' => sprintf(
                        'Ingreso por cierre diario de caja #%d (cajero: %s)',
                        $caja->id,
                        $usuario?->name ?? 'N/D'
                    ),
                    'desglose_denominaciones' => $data['desglose_denominaciones'] ?? null,
                    'boveda_destino_id' => null,
                    'referencia' => 'cierre_caja:' . $caja->id,
                    'estado' => 'aprobado',
                    'aprobado_por' => $usuario?->id,
                    'fecha_aprobacion' => now(),
                ]);

                // Recalcular saldo de la bóveda en base a todos los movimientos aprobados
                try {
                    $boveda->actualizarSaldo();
                } catch (\Throwable $e) {
                    Log::error('Error actualizando saldo de bóveda en cierre de caja: ' . $e->getMessage());
                    throw $e;
                }
            }

            return [$caja->fresh(['user', 'sucursal', 'bovedaDestino']), $movimientoBoveda];
        });
    }
}

