<?php

namespace App\Services;

use App\Models\CajaAperturaCierre;
use App\Models\MovimientoCaja;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CajaService
{
    /**
     * Obtener la caja abierta del usuario actual
     */
    public static function getCajaAbierta(?int $userId = null): ?CajaAperturaCierre
    {
        $userId = $userId ?? Auth::id();

        if (!$userId) {
            return null;
        }

        return CajaAperturaCierre::where('user_id', $userId)
            ->where('estado', 'abierta')
            ->first();
    }

    /**
     * Verificar si el usuario tiene caja abierta
     */
    public static function tieneCajaAbierta(?int $userId = null): bool
    {
        return self::getCajaAbierta($userId) !== null;
    }

    /**
     * Registrar un ingreso a caja (pago de crédito, venta, etc.)
     */
    public static function registrarIngreso(
        float $monto,
        string $concepto,
        ?array $detalles = null,
        ?int $userId = null
    ): ?MovimientoCaja {
        $caja = self::getCajaAbierta($userId);

        if (!$caja) {
            Log::warning("Intento de registrar ingreso sin caja abierta. Usuario: " . ($userId ?? Auth::id()));
            return null;
        }

        return MovimientoCaja::create([
            'caja_id' => $caja->id,
            'tipo' => 'ingreso_pago',
            'monto' => abs($monto),
            'concepto' => $concepto,
            'detalles_movimiento' => $detalles,
            'estado' => 'aplicado',
            'user_id' => $userId ?? Auth::id(),
        ]);
    }

    /**
     * Registrar un egreso de caja (desembolso, devolución, etc.)
     */
    public static function registrarEgreso(
        float $monto,
        string $concepto,
        ?array $detalles = null,
        ?int $userId = null
    ): ?MovimientoCaja {
        $caja = self::getCajaAbierta($userId);

        if (!$caja) {
            Log::warning("Intento de registrar egreso sin caja abierta. Usuario: " . ($userId ?? Auth::id()));
            return null;
        }

        return MovimientoCaja::create([
            'caja_id' => $caja->id,
            'tipo' => 'egreso_desembolso',
            'monto' => abs($monto),
            'concepto' => $concepto,
            'detalles_movimiento' => $detalles,
            'estado' => 'aplicado',
            'user_id' => $userId ?? Auth::id(),
        ]);
    }

    /**
     * Registrar ingreso por venta de prenda
     */
    public static function registrarVenta(
        float $monto,
        string $codigoVenta,
        string $codigoPrenda,
        string $metodoPago,
        ?string $clienteNombre = null
    ): ?MovimientoCaja {
        $concepto = "Venta #{$codigoVenta} - Prenda: {$codigoPrenda}";
        if ($clienteNombre) {
            $concepto .= " - Cliente: {$clienteNombre}";
        }

        return self::registrarIngreso($monto, $concepto, [
            'tipo_operacion' => 'venta_prenda',
            'codigo_venta' => $codigoVenta,
            'codigo_prenda' => $codigoPrenda,
            'metodo_pago' => $metodoPago,
            'cliente' => $clienteNombre,
            'fecha' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Registrar ingreso por pago de crédito
     */
    public static function registrarPagoCredito(
        float $monto,
        string $numeroCredito,
        string $tipoPago,
        ?string $numeroMovimiento = null,
        ?string $clienteNombre = null
    ): ?MovimientoCaja {
        $tipoLabel = match($tipoPago) {
            'RENOVACION' => 'Renovación',
            'PARCIAL' => 'Pago Parcial',
            'LIQUIDACION' => 'Liquidación',
            'INTERES_ADELANTADO' => 'Interés Adelantado',
            default => $tipoPago
        };

        $concepto = "Pago Crédito #{$numeroCredito} - {$tipoLabel}";
        if ($clienteNombre) {
            $concepto .= " - {$clienteNombre}";
        }

        return self::registrarIngreso($monto, $concepto, [
            'tipo_operacion' => 'pago_credito',
            'numero_credito' => $numeroCredito,
            'tipo_pago' => $tipoPago,
            'numero_movimiento' => $numeroMovimiento,
            'cliente' => $clienteNombre,
            'fecha' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Registrar egreso por desembolso de crédito
     */
    public static function registrarDesembolso(
        float $monto,
        string $numeroCredito,
        ?string $clienteNombre = null,
        ?string $formaDesembolso = 'efectivo'
    ): ?MovimientoCaja {
        // Solo registrar si es efectivo (transferencias no salen de caja física)
        if ($formaDesembolso !== 'efectivo') {
            return null;
        }

        $concepto = "Desembolso Crédito #{$numeroCredito}";
        if ($clienteNombre) {
            $concepto .= " - {$clienteNombre}";
        }

        return self::registrarEgreso($monto, $concepto, [
            'tipo_operacion' => 'desembolso_credito',
            'numero_credito' => $numeroCredito,
            'cliente' => $clienteNombre,
            'forma_desembolso' => $formaDesembolso,
            'fecha' => now()->toDateTimeString(),
        ]);
    }

    /**
     * Obtener resumen de caja actual
     */
    public static function getResumenCaja(?int $userId = null): array
    {
        $caja = self::getCajaAbierta($userId);

        if (!$caja) {
            return [
                'tiene_caja' => false,
                'mensaje' => 'No hay caja abierta'
            ];
        }

        $movimientos = MovimientoCaja::where('caja_id', $caja->id)
            ->where('estado', 'aplicado')
            ->get();

        $ingresos = $movimientos->whereIn('tipo', ['incremento', 'ingreso_pago'])->sum('monto');
        $egresos = $movimientos->whereIn('tipo', ['decremento', 'egreso_desembolso'])->sum('monto');

        $saldoActual = $caja->saldo_inicial + $ingresos - $egresos;

        return [
            'tiene_caja' => true,
            'caja_id' => $caja->id,
            'fecha_apertura' => $caja->fecha_apertura,
            'saldo_inicial' => (float) $caja->saldo_inicial,
            'total_ingresos' => $ingresos,
            'total_egresos' => $egresos,
            'saldo_actual' => $saldoActual,
            'cantidad_movimientos' => $movimientos->count(),
        ];
    }
}
