<?php

namespace App\Services;

use App\Models\Prenda;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;
use App\Models\MetodoPago;
use App\Services\ContabilidadService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;

/**
 * Servicio para ventas multi-prenda (1 venta = N prendas + N pagos)
 * Previene doble venta con SELECT FOR UPDATE y validaciones estrictas
 */
class VentaMultiPrendaService
{
    /**
     * Validar y bloquear prendas para prevenir concurrencia
     *
     * @param array $prendasIds [10, 11, 12]
     * @return array ['prendas' => Collection, 'errores' => []]
     */
    public function validarYBloquearPrendas(array $prendasIds): array
    {
        $errores = [];

        // LOCK PARA EVITAR RACE CONDITIONS
        $prendas = Prenda::whereIn('id', $prendasIds)
            ->lockForUpdate() // SELECT ... FOR UPDATE
            ->get();

        // Validar cada prenda
        foreach ($prendasIds as $id) {
            $prenda = $prendas->firstWhere('id', $id);

            if (!$prenda) {
                $errores[] = "Prenda ID {$id} no existe";
                continue;
            }

            // Solo permitir vender si estado = en_venta
            if ($prenda->estado !== 'en_venta') {
                $errores[] = "Prenda {$prenda->codigo_prenda} no está disponible (estado: {$prenda->estado})";
            }

            // Validar que tenga precio de venta
            if (!$prenda->precio_venta || $prenda->precio_venta <= 0) {
                $errores[] = "Prenda {$prenda->codigo_prenda} no tiene precio de venta configurado";
            }

            // Verificar que no esté ya vendida en otra transacción concurrente
            $yaVendida = VentaDetalle::where('prenda_id', $id)
                ->whereHas('venta', function($q) {
                    $q->whereNotIn('estado', ['cancelada']);
                })
                ->exists();

            if ($yaVendida) {
                $errores[] = "Prenda {$prenda->codigo_prenda} ya fue vendida";
            }
        }

        return [
            'prendas' => $prendas,
            'errores' => $errores
        ];
    }

    /**
     * Crear venta completa con múltiples prendas y pagos
     *
     * @param array $data {
     *   cliente_id: int,
     *   sucursal_id: int,
     *   tipo_venta: 'contado'|'apartado'|'plan_pago',
     *   items: [
     *     { prenda_id: 10, precio_unitario: 1500, descuento: 100, subtotal: 1400 },
     *     { prenda_id: 11, precio_unitario: 500, descuento: 0, subtotal: 500 }
     *   ],
     *   pagos: [
     *     { metodo: 'efectivo', monto: 1000, referencia: null },
     *     { metodo: 'tarjeta', monto: 900, referencia: 'TRX-123' }
     *   ],
     *   observaciones: string
     * }
     */
    public function crearVenta(array $data): Venta
    {
        return DB::transaction(function () use ($data) {

            // 1. Validar y bloquear prendas
            $prendasIds = collect($data['items'])->pluck('prenda_id')->toArray();
            $validacion = $this->validarYBloquearPrendas($prendasIds);

            if (!empty($validacion['errores'])) {
                throw new \Exception("Errores de validación:\n" . implode("\n", $validacion['errores']));
            }

            $prendas = $validacion['prendas'];

            // 2. Recalcular totales SERVER-SIDE (no confiar en cliente)
            $totales = $this->calcularTotales($data['items'], $prendas);

            // 3. Validar descuentos por prenda
            foreach ($data['items'] as $item) {
                $prenda = $prendas->firstWhere('id', $item['prenda_id']);
                $this->validarDescuento($prenda, $item['descuento'], $item['precio_unitario']);
            }

            // 4. Crear registro de venta
            $venta = Venta::create([
                'codigo_venta' => $this->generarCodigoVenta(),
                'cliente_id' => $data['cliente_id'] ?? null,
                'sucursal_id' => $data['sucursal_id'] ?? Auth::user()->sucursal_id,
                'vendedor_id' => Auth::id(),
                'tipo_venta' => $data['tipo_venta'] ?? 'contado',
                'fecha_venta' => now(),

                // Totales recalculados
                'subtotal' => $totales['subtotal'],
                'total_descuentos' => $totales['total_descuentos'],
                'total_final' => $totales['total_final'],
                'total_pagado' => 0, // se actualiza después

                'estado' => 'pendiente', // se cambia después según pagos
                'observaciones' => $data['observaciones'] ?? null,
                'consumidor_final' => $data['consumidor_final'] ?? true,
            ]);

            // 5. Agregar items (detalles)
            $this->agregarItems($venta, $data['items'], $prendas);

            // 6. Registrar pagos
            $totalPagado = $this->registrarPagos($venta, $data['pagos'] ?? []);

            // 7. Determinar estado final
            $estado = $this->determinarEstadoVenta($venta, $totalPagado, $data['tipo_venta']);

            $venta->update([
                'total_pagado' => $totalPagado,
                'estado' => $estado,
                'cambio_devuelto' => max(0, $totalPagado - $venta->total_final)
            ]);

            // 8. Actualizar estado de prendas
            $this->actualizarEstadoPrendas($venta, $estado, $prendas);

            // 9. Integración con otros módulos (si están activos)
            if ($estado === 'pagada') {
                $this->integrarConModulos($venta);
            }

            // 10. Log de auditoría
            Log::info("Venta creada: {$venta->codigo_venta}", [
                'venta_id' => $venta->id,
                'prendas' => $prendasIds,
                'total' => $venta->total_final,
                'estado' => $estado
            ]);

            return $venta->load(['detalles.prenda', 'pagos', 'cliente', 'vendedor']);
        });
    }

    /**
     * Integrar venta con módulos de Caja y Contabilidad
     */
    private function integrarConModulos(Venta $venta): void
    {
        try {
            // INTEGRACIÓN CON CAJA (si existe MovimientoCaja)
            if (class_exists('\App\Models\MovimientoCaja')) {
                $this->registrarEnCaja($venta);
            }

            // INTEGRACIÓN CON CONTABILIDAD (si está activo)
            if (config('contabilidad.auto_asientos_por_operacion.venta_prenda', false)) {
                $this->generarAsientoContable($venta);
            }
        } catch (\Exception $e) {
            // No fallar la venta si la integración falla
            Log::warning("Error en integración de venta {$venta->codigo_venta}: " . $e->getMessage());
        }
    }

    /**
     * Registrar movimiento en caja (solo pagos en efectivo)
     */
    private function registrarEnCaja(Venta $venta): void
    {
        $pagosEfectivo = $venta->pagos()
            ->where('metodo', 'efectivo')
            ->sum('monto');

        if ($pagosEfectivo > 0) {
            $MovimientoCaja = app('\App\Models\MovimientoCaja');

            $MovimientoCaja::create([
                'caja_apertura_cierre_id' => session('caja_abierta_id'), // Asume que hay sesión de caja
                'tipo' => 'ingreso',
                'concepto' => 'venta_prenda',
                'descripcion' => "Venta {$venta->codigo_venta} - {$venta->detalles->count()} prendas",
                'monto' => $pagosEfectivo,
                'referencia' => $venta->codigo_venta,
                'usuario_id' => Auth::id(),
                'created_at' => now()
            ]);

            Log::info("Movimiento de caja registrado para venta {$venta->codigo_venta}: Q{$pagosEfectivo}");
        }
    }

    /**
     * Generar asiento contable automático
     */
    private function generarAsientoContable(Venta $venta): void
    {
        if (!class_exists('\App\Services\ContabilidadService')) {
            return;
        }

        $contabilidadService = app('\App\Services\ContabilidadService');

        // Asiento: Caja (debe) / Ventas (haber)
        $contabilidadService->registrarAsiento([
            'tipo' => 'ingreso',
            'concepto' => "Venta de prendas {$venta->codigo_venta}",
            'referencia' => $venta->codigo_venta,
            'fecha' => $venta->fecha_venta,
            'movimientos' => [
                [
                    'cuenta' => config('contabilidad.cuentas.caja', '1101'),
                    'debe' => $venta->total_final,
                    'haber' => 0,
                    'descripcion' => 'Ingreso por venta de prendas'
                ],
                [
                    'cuenta' => config('contabilidad.cuentas.ventas', '4101'),
                    'debe' => 0,
                    'haber' => $venta->total_final,
                    'descripcion' => "{$venta->detalles->count()} prendas vendidas"
                ]
            ]
        ]);

        Log::info("Asiento contable generado para venta {$venta->codigo_venta}");
    }

    /**
     * Calcular totales server-side (nunca confiar en frontend)
     */
    private function calcularTotales(array $items, $prendas): array
    {
        $subtotal = 0;
        $totalDescuentos = 0;

        foreach ($items as $item) {
            $prenda = $prendas->firstWhere('id', $item['prenda_id']);

            // Recalcular en servidor
            $precioBase = (float) $prenda->precio_venta;
            $descuento = (float) ($item['descuento'] ?? 0);
            $subtotalItem = $precioBase - $descuento;

            $subtotal += $precioBase;
            $totalDescuentos += $descuento;
        }

        return [
            'subtotal' => $subtotal,
            'total_descuentos' => $totalDescuentos,
            'total_final' => $subtotal - $totalDescuentos,
        ];
    }

    /**
     * Validar que descuento no exceda límites de la prenda
     */
    private function validarDescuento(Prenda $prenda, float $descuento, float $precioUnitario): void
    {
        // Validar precio mínimo
        if ($prenda->precio_minimo && ($precioUnitario - $descuento) < $prenda->precio_minimo) {
            throw new \Exception(
                "Prenda {$prenda->codigo_prenda}: precio final no puede ser menor a Q" .
                number_format($prenda->precio_minimo, 2)
            );
        }

        // Validar descuento máximo en porcentaje
        if ($prenda->descuento_max_pct) {
            $descuentoMaxQ = $precioUnitario * ($prenda->descuento_max_pct / 100);

            if ($descuento > $descuentoMaxQ) {
                throw new \Exception(
                    "Prenda {$prenda->codigo_prenda}: descuento máximo permitido es {$prenda->descuento_max_pct}% (Q" .
                    number_format($descuentoMaxQ, 2) . ")"
                );
            }
        }
    }

    /**
     * Agregar items (detalles) a la venta
     */
    private function agregarItems(Venta $venta, array $items, $prendas): void
    {
        foreach ($items as $item) {
            $prenda = $prendas->firstWhere('id', $item['prenda_id']);

            $precioBase = (float) $prenda->precio_venta;
            $descuento = (float) ($item['descuento'] ?? 0);
            $subtotal = $precioBase - $descuento;

            VentaDetalle::create([
                'venta_id' => $venta->id,
                'prenda_id' => $prenda->id,
                'codigo' => $prenda->codigo_prenda,
                'descripcion' => $prenda->descripcion,
                'cantidad' => 1, // prendas son únicas
                'precio_unitario' => $precioBase,
                'descuento' => $descuento,
                'descuento_porcentaje' => $precioBase > 0 ? ($descuento / $precioBase * 100) : 0,
                'subtotal' => $subtotal,
                'total' => $subtotal,
            ]);
        }
    }

    /**
     * Registrar pagos y devolver total pagado
     */
    private function registrarPagos(Venta $venta, array $pagos): float
    {
        $totalPagado = 0;

        foreach ($pagos as $pago) {
            $monto = (float) $pago['monto'];

            if ($monto <= 0) {
                continue; // ignorar pagos inválidos
            }

            VentaPago::create([
                'venta_id' => $venta->id,
                'metodo' => $pago['metodo'] ?? 'efectivo',
                'monto' => $monto,
                'referencia' => $pago['referencia'] ?? null,
                'banco' => $pago['banco'] ?? null,
                'fecha_pago' => now(),
            ]);

            $totalPagado += $monto;
        }

        return $totalPagado;
    }

    /**
     * Determinar estado de venta según pagos y tipo
     */
    private function determinarEstadoVenta(Venta $venta, float $totalPagado, string $tipoVenta): string
    {
        // Si es apartado o plan de pago, siempre queda pendiente al inicio
        if (in_array($tipoVenta, ['apartado', 'plan_pago'])) {
            return 'pendiente';
        }

        // Contado: depende de si está totalmente pagada
        if ($totalPagado >= $venta->total_final) {
            return 'pagada';
        }

        return 'pendiente';
    }

    /**
     * Actualizar estado de las prendas según tipo de venta
     */
    private function actualizarEstadoPrendas(Venta $venta, string $estadoVenta, $prendas): void
    {
        foreach ($prendas as $prenda) {
            $nuevoEstado = match($venta->tipo_venta) {
                'contado' => $estadoVenta === 'pagada' ? 'vendida' : 'en_venta',
                'apartado' => 'apartada',
                'plan_pago' => 'en_plan_pago',
                default => 'en_venta'
            };

            $prenda->update([
                'estado' => $nuevoEstado,
                'fecha_venta' => $estadoVenta === 'pagada' ? now() : null,
            ]);
        }
    }

    /**
     * Generar código único de venta
     */
    private function generarCodigoVenta(): string
    {
        do {
            $codigo = 'VEN-' . now()->format('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
            $existe = Venta::where('codigo_venta', $codigo)->exists();
        } while ($existe);

        return $codigo;
    }

    /**
     * Cancelar venta y devolver prendas a inventario
     */
    public function cancelarVenta(Venta $venta, string $motivo): Venta
    {
        return DB::transaction(function () use ($venta, $motivo) {
            if ($venta->estado === 'cancelada') {
                throw new \Exception("Esta venta ya fue cancelada");
            }

            // Devolver prendas a estado en_venta
            foreach ($venta->detalles as $detalle) {
                $detalle->prenda->update([
                    'estado' => 'en_venta',
                    'fecha_venta' => null,
                ]);
            }

            // Marcar venta como cancelada
            $venta->update([
                'estado' => 'cancelada',
                'fecha_cancelacion' => now(),
                'motivo_cancelacion' => $motivo
            ]);

            // Soft delete de pagos (auditoría)
            $venta->pagos()->delete();

            Log::info("Venta cancelada: {$venta->codigo_venta}", [
                'motivo' => $motivo,
                'user_id' => Auth::id()
            ]);

            return $venta->fresh(['detalles.prenda']);
        });
    }

    /**
     * Registrar pago adicional (para apartados/planes de pago)
     */
    public function registrarPagoAdicional(Venta $venta, array $pagoData): Venta
    {
        return DB::transaction(function () use ($venta, $pagoData) {

            if ($venta->estado === 'pagada') {
                throw new \Exception("Esta venta ya está completamente pagada");
            }

            // Crear nuevo pago
            $monto = (float) $pagoData['monto'];

            VentaPago::create([
                'venta_id' => $venta->id,
                'metodo' => $pagoData['metodo'] ?? 'efectivo',
                'monto' => $monto,
                'referencia' => $pagoData['referencia'] ?? null,
                'fecha_pago' => now(),
            ]);

            // Recalcular total pagado
            $totalPagado = $venta->pagos()->sum('monto');

            $nuevoEstado = $totalPagado >= $venta->total_final ? 'pagada' : 'pendiente';

            $venta->update([
                'total_pagado' => $totalPagado,
                'estado' => $nuevoEstado,
            ]);

            // Si se completó el pago, actualizar prendas a vendidas
            if ($nuevoEstado === 'pagada') {
                foreach ($venta->detalles as $detalle) {
                    $detalle->prenda->update([
                        'estado' => 'vendida',
                        'fecha_venta' => now(),
                    ]);
                }
            }

            return $venta->fresh(['pagos', 'detalles.prenda']);
        });
    }
}
