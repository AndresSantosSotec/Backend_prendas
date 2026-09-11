<?php

namespace App\Services;

use App\Models\CajaAperturaCierre;
use App\Models\MovimientoCaja;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class CajaService
{
    /**
     * Obtener la caja abierta del usuario actual o de la sucursal indicada
     */
    public static function getCajaAbierta(?int $userId = null, ?int $sucursalId = null): ?CajaAperturaCierre
    {
        $userId = $userId ?? Auth::id();

        // 1. Buscar caja abierta del usuario específico (ordenada por la más reciente)
        if ($userId) {
            $caja = CajaAperturaCierre::where('user_id', $userId)
                ->where('estado', 'abierta')
                ->whereNull('fecha_cierre')
                ->orderBy('fecha_apertura', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($caja) {
                return $caja;
            }

            // Fallback usuario sin verificar fecha_cierre por posibles inconsistencias
            $caja = CajaAperturaCierre::where('user_id', $userId)
                ->where('estado', 'abierta')
                ->orderBy('fecha_apertura', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($caja) {
                return $caja;
            }
        }

        // 2. Si no hay caja del usuario, buscar cualquier caja abierta en la sucursal
        $sucursalId = $sucursalId ?? Auth::user()?->sucursal_id ?? request()->get('_sucursal_scope');
        if ($sucursalId) {
            $cajaSucursal = CajaAperturaCierre::where('sucursal_id', $sucursalId)
                ->where('estado', 'abierta')
                ->whereNull('fecha_cierre')
                ->orderBy('fecha_apertura', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($cajaSucursal) {
                return $cajaSucursal;
            }

            $cajaSucursal = CajaAperturaCierre::where('sucursal_id', $sucursalId)
                ->where('estado', 'abierta')
                ->orderBy('fecha_apertura', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($cajaSucursal) {
                return $cajaSucursal;
            }
        }

        // 3. Fallback general: la última caja abierta activa en el sistema
        return CajaAperturaCierre::where('estado', 'abierta')
            ->whereNull('fecha_cierre')
            ->orderBy('fecha_apertura', 'desc')
            ->orderBy('id', 'desc')
            ->first()
            ?? CajaAperturaCierre::where('estado', 'abierta')
                ->orderBy('fecha_apertura', 'desc')
                ->orderBy('id', 'desc')
                ->first();
    }

    /**
     * Verificar si el usuario o la sucursal tiene caja abierta
     */
    public static function tieneCajaAbierta(?int $userId = null, ?int $sucursalId = null): bool
    {
        return self::getCajaAbierta($userId, $sucursalId) !== null;
    }

    /**
     * Registrar un ingreso a caja (pago de crédito, venta, etc.)
     */
    public static function registrarIngreso(
        float $monto,
        string $concepto,
        ?array $detalles = null,
        ?int $userId = null,
        ?int $sucursalId = null
    ): ?MovimientoCaja {
        $caja = self::getCajaAbierta($userId, $sucursalId);

        if (!$caja) {
            Log::warning("Intento de registrar ingreso sin caja abierta. Usuario: " . ($userId ?? Auth::id()) . " Sucursal: {$sucursalId}");
            return null;
        }

        return MovimientoCaja::create([
            'caja_id' => $caja->id,
            'tipo' => 'ingreso_pago',
            'monto' => abs($monto),
            'concepto' => mb_substr($concepto, 0, 250),
            'detalles_movimiento' => $detalles,
            'estado' => 'aplicado',
            'user_id' => $userId ?? Auth::id() ?? $caja->user_id,
        ]);
    }

    /**
     * Registrar un egreso de caja (desembolso, devolución, compra, etc.)
     */
    public static function registrarEgreso(
        float $monto,
        string $concepto,
        ?array $detalles = null,
        ?int $userId = null,
        ?int $sucursalId = null
    ): ?MovimientoCaja {
        $caja = self::getCajaAbierta($userId, $sucursalId);

        if (!$caja) {
            Log::warning("Intento de registrar egreso sin caja abierta. Usuario: " . ($userId ?? Auth::id()) . " Sucursal: {$sucursalId}");
            return null;
        }

        return MovimientoCaja::create([
            'caja_id' => $caja->id,
            'tipo' => 'egreso_desembolso',
            'monto' => abs($monto),
            'concepto' => mb_substr($concepto, 0, 250),
            'detalles_movimiento' => $detalles,
            'estado' => 'aplicado',
            'user_id' => $userId ?? Auth::id() ?? $caja->user_id,
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
