<?php

namespace App\Services;

use App\Models\Prenda;
use App\Models\Venta;
use App\Models\VentaDetalle;
use App\Models\VentaPago;
use App\Models\MetodoPago;
use App\Models\Cliente;
use App\Models\Sucursal;
use App\Models\VentaCredito;
use App\Models\VentaCreditoPlanPago;
use App\Models\VentaCreditoMovimiento;
use App\Enums\EstadoPrenda;
use App\Enums\EstadoVenta;
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

            // 3.1. Obtener información del cliente
            $clienteId = $data['cliente_id'] ?? null;

            $clienteNombre = 'Consumidor Final';
            $clienteNit = 'C/F';
            $esConsumidorFinal = true;

            if ($clienteId) {
                $cliente = Cliente::find($clienteId);
                if ($cliente) {
                    $clienteNombre = $cliente->nombre_completo ?? (trim($cliente->nombres . ' ' . $cliente->apellidos));
                    $clienteNit = $cliente->nit ?? 'C/F';
                    $esConsumidorFinal = false;
                }
            } else {
                // Si no hay ID de cliente, pero enviaron NIT/Nombre manualmente
                $clienteNombre = $data['cliente_nombre'] ?? 'Consumidor Final';
                $clienteNit = $data['nit'] ?? ($data['cliente_nit'] ?? 'C/F');
                $esConsumidorFinal = $data['consumidor_final'] ?? true;
            }

            // 3.2. Determinar sucursal (prioridad: data > usuario > primera disponible)
            $sucursalId = $data['sucursal_id'] ?? null;
            if (!$sucursalId && Auth::user()) {
                $sucursalId = Auth::user()->sucursal_id;
            }
            if (!$sucursalId) {
                // Fallback: usar primera sucursal disponible
                $sucursalId = Sucursal::first()?->id;
                if (!$sucursalId) {
                    throw new \Exception('No hay sucursales disponibles en el sistema');
                }
            }

            // 4. Crear registro de venta
            $venta = Venta::create([
                'codigo_venta' => $this->generarCodigoVenta(),
                'cliente_id' => $data['cliente_id'] ?? null,
                'cliente_nombre' => $clienteNombre,
                'cliente_nit' => $clienteNit,
                'sucursal_id' => $sucursalId,
                'vendedor_id' => Auth::id(),
                'tipo_venta' => $data['tipo_venta'] ?? 'contado',
                'fecha_venta' => now(),

                // Totales recalculados
                'subtotal' => $totales['subtotal'],
                'total_descuentos' => $totales['total_descuentos'],
                'total_final' => $totales['total_final'],
                'total_pagado' => 0, // se actualiza después

                'estado' => 'pendiente', // se cambia después según pagos
                'observaciones' => $data['observaciones'] ?? 'Venta generada desde módulo NuevaVenta por ' . (Auth::user()->name ?? 'admin'),
                'consumidor_final' => $esConsumidorFinal,
                'tipo_documento' => in_array(
                    $data['tipo_comprobante'] ?? 'NOTA',
                    ['NOTA', 'FACTURA', 'RECIBO', 'COTIZACION', 'FEL']
                ) ? ($data['tipo_comprobante'] ?? 'NOTA') : 'NOTA',
                'moneda_id' => $data['moneda_id'] ?? 1, // Por defecto GTQ
            ]);

            // 5. Agregar items (detalles)
            $this->agregarItems($venta, $data['items'], $prendas);

            // 6. Registrar pagos
            $totalPagado = $this->registrarPagos($venta, $data['pagos'] ?? []);

            // 6.1. Configurar según tipo de venta
            $tipoVenta = $data['tipo_venta'] ?? 'contado';
            $saldoPendiente = $venta->total_final - $totalPagado;

            // CRÉDITO: enganche + cuotas con interés flat
            if ($tipoVenta === 'credito') {
                $this->configurarCredito($venta, $data, $totalPagado);
            }

            // APARTADO: anticipo + fecha límite
            if ($tipoVenta === 'apartado') {
                $this->configurarApartado($venta, $data, $totalPagado);
            }

            // PLAN_PAGOS (legacy, mantener compatibilidad)
            if ($tipoVenta === 'plan_pagos') {
                $this->configurarPlanPagos($venta, $data, $totalPagado, $saldoPendiente);
            }

            // Recalcular saldo pendiente después de configuración
            $venta->refresh();
            $saldoPendiente = ($venta->total_credito ?: $venta->total_final) - $venta->total_pagado;

            // 7. Determinar estado final
            $estado = $this->determinarEstadoVenta($venta, $totalPagado, $tipoVenta);

            $venta->update([
                'total_pagado' => $totalPagado,
                'saldo_pendiente' => $saldoPendiente,
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
    /**
     * Registrar múltiples métodos de pago
     */
    private function registrarPagos(Venta $venta, array $pagos): float
    {
        $totalPagado = 0;

        foreach ($pagos as $pago) {
            $monto = (float) $pago['monto'];

            if ($monto <= 0) {
                continue; // ignorar pagos inválidos
            }

            // Obtener ID del método de pago desde tabla metodos_pago
            $metodoCodigo = $pago['metodo'] ?? 'efectivo';
            $metodoPagoId = $this->obtenerMetodoPagoId($metodoCodigo);

            if (!$metodoPagoId) {
                Log::warning("Método de pago '{$metodoCodigo}' no encontrado, usando efectivo por defecto");
                $metodoPagoId = 1; // ID de efectivo
            }

            VentaPago::create([
                'venta_id' => $venta->id,
                'metodo_pago_id' => $metodoPagoId,
                'monto' => $monto,
                'referencia' => $pago['referencia'] ?? null,
                'banco' => $pago['banco'] ?? null,
            ]);

            $totalPagado += $monto;
        }

        return $totalPagado;
    }

    /**
     * Obtener ID de método de pago por código
     */
    private function obtenerMetodoPagoId(string $codigo): ?int
    {
        static $cache = null;

        if ($cache === null) {
            // Cargar todos los métodos de pago en cache (solo una vez)
            $cache = MetodoPago::pluck('id', 'codigo')->toArray();
        }

        return $cache[$codigo] ?? null;
    }

    /**
     * Determinar estado de venta según pagos y tipo
     */
    private function determinarEstadoVenta(Venta $venta, float $totalPagado, string $tipoVenta): string
    {
        // Si es apartado, usar estado apartado
        if ($tipoVenta === 'apartado') {
            return EstadoVenta::APARTADO->value;
        }

        // Si es crédito, usar estado plan_pagos (mismo concepto)
        if ($tipoVenta === 'credito') {
            return EstadoVenta::PLAN_PAGOS->value;
        }

        if ($tipoVenta === 'plan_pagos') {
            return EstadoVenta::PLAN_PAGOS->value;
        }

        // Contado: depende de si está totalmente pagada
        if ($totalPagado >= $venta->total_final) {
            return EstadoVenta::PAGADA->value;
        }

        return EstadoVenta::PENDIENTE->value;
    }

    /**
     * Actualizar estado de las prendas según tipo de venta
     */
    private function actualizarEstadoPrendas(Venta $venta, string $estadoVenta, $prendas): void
    {
        foreach ($prendas as $prenda) {
            // Solo estados válidos de la BD: en_custodia, recuperada, en_venta, vendida, perdida, deteriorada, devuelta
            $nuevoEstado = match($venta->tipo_venta) {
                'contado' => $estadoVenta === 'pagada' ? EstadoPrenda::VENDIDA->value : EstadoPrenda::EN_VENTA->value,
                'credito' => EstadoPrenda::EN_VENTA->value, // Sigue en venta hasta completar pago
                'apartado' => EstadoPrenda::EN_VENTA->value, // Sigue en venta hasta completar pago
                'plan_pagos' => EstadoPrenda::EN_VENTA->value, // Sigue en venta hasta completar pago
                default => EstadoPrenda::EN_VENTA->value
            };

            $datosActualizacion = [
                'estado' => $nuevoEstado,
            ];

            // Solo establecer fecha_venta cuando realmente se vende (pagada completamente)
            if ($estadoVenta === 'pagada' && $nuevoEstado === EstadoPrenda::VENDIDA->value) {
                $datosActualizacion['fecha_venta'] = now();
            }

            $prenda->update($datosActualizacion);
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

    /**
     * Configurar venta a CRÉDITO (enganche + cuotas con interés flat)
     *
     * Fórmula FLAT CON GASTOS:
     * saldoFinanciar = totalVenta - enganche
     * interesTotal = saldoFinanciar × (tasaMensual/100) × numeroCuotas
     * totalGastos = seguro + estudio + apertura + otros
     * totalCredito = saldoFinanciar + interesTotal + totalGastos
     * cuotaMensual = totalCredito / numeroCuotas
     *
     * Crea:
     * 1. Registro en venta_creditos
     * 2. Plan de pagos en venta_credito_plan_pagos
     * 3. Movimiento de enganche en venta_credito_movimientos
     */
    private function configurarCredito(Venta $venta, array $data, float $enganchePagado): void
    {
        $enganche = $data['enganche'] ?? $enganchePagado;
        $numeroCuotas = $data['numero_cuotas'] ?? 3;
        $tasaInteresMensual = $data['tasa_interes_mensual'] ?? 5;
        $frecuenciaPago = $data['frecuencia_pago'] ?? 'mensual';

        // Gastos adicionales
        $gastoSeguro = $data['gasto_seguro'] ?? 0;
        $gastoEstudio = $data['gasto_estudio'] ?? 0;
        $gastoApertura = $data['gasto_apertura'] ?? 0;
        $gastoOtros = $data['gasto_otros'] ?? 0;
        $totalGastos = $gastoSeguro + $gastoEstudio + $gastoApertura + $gastoOtros;

        // Cálculos FLAT CON GASTOS
        $saldoFinanciar = $venta->total_final - $enganche;
        $interesTotal = $saldoFinanciar * ($tasaInteresMensual / 100) * $numeroCuotas;
        $totalCredito = $saldoFinanciar + $interesTotal + $totalGastos;
        $cuotaMensual = $numeroCuotas > 0 ? $totalCredito / $numeroCuotas : $totalCredito;

        // Actualizar la venta con datos del crédito
        $venta->update([
            'enganche' => $enganche,
            'numero_cuotas' => $numeroCuotas,
            'tasa_interes' => $tasaInteresMensual,
            'intereses' => $interesTotal,
            'interes_total' => $interesTotal,
            'total_credito' => $totalCredito,
            'monto_cuota' => $cuotaMensual,
            'frecuencia_pago' => $frecuenciaPago,
            'cuotas_pagadas' => 0,
            'total_pagado' => $enganchePagado,
            'saldo_pendiente' => $totalCredito,
            'fecha_proximo_pago' => now()->addMonth(),
            'fecha_vencimiento' => now()->addMonths($numeroCuotas),
        ]);

        // ========== CREAR VENTA_CREDITO ==========
        $ventaCredito = VentaCredito::create([
            'numero_credito' => VentaCredito::generarNumeroCredito(),
            'venta_id' => $venta->id,
            'cliente_id' => $venta->cliente_id,
            'sucursal_id' => $venta->sucursal_id,
            'vendedor_id' => $venta->vendedor_id ?? Auth::id(),
            'aprobado_por_id' => Auth::id(),
            'estado' => 'vigente',
            'fecha_credito' => now(),
            'fecha_aprobacion' => now(),
            'fecha_primer_pago' => now()->addMonth(),
            'fecha_vencimiento' => now()->addMonths($numeroCuotas),
            'monto_venta' => $venta->total_final,
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
            'tasa_interes' => $tasaInteresMensual,
            'tasa_mora' => 0, // Se puede configurar
            'gasto_seguro' => $gastoSeguro,
            'gasto_estudio' => $gastoEstudio,
            'gasto_apertura' => $gastoApertura,
            'gasto_otros' => $gastoOtros,
            'total_gastos' => $totalGastos,
            'tipo_interes' => 'flat',
            'frecuencia_pago' => $frecuenciaPago,
            'numero_cuotas' => $numeroCuotas,
            'monto_cuota' => $cuotaMensual,
            'dias_gracia' => 0,
            'dias_mora' => 0,
            'cuotas_vencidas' => 0,
            'cuotas_pagadas' => 0,
        ]);

        // ========== CREAR PLAN DE PAGOS ==========
        $capitalPorCuota = $saldoFinanciar / $numeroCuotas;
        $interesPorCuota = $interesTotal / $numeroCuotas;
        $gastosPorCuota = $totalGastos / $numeroCuotas;
        $saldoCapitalRestante = $saldoFinanciar;

        for ($i = 1; $i <= $numeroCuotas; $i++) {
            $fechaVencimiento = match($frecuenciaPago) {
                'semanal' => now()->addWeeks($i),
                'quincenal' => now()->addWeeks($i * 2),
                'mensual' => now()->addMonths($i),
                default => now()->addMonths($i),
            };

            $saldoCapitalRestante -= $capitalPorCuota;

            VentaCreditoPlanPago::create([
                'venta_credito_id' => $ventaCredito->id,
                'numero_cuota' => $i,
                'fecha_vencimiento' => $fechaVencimiento,
                'estado' => 'pendiente',
                'capital_proyectado' => round($capitalPorCuota, 2),
                'interes_proyectado' => round($interesPorCuota, 2),
                'mora_proyectada' => 0,
                'otros_cargos_proyectados' => round($gastosPorCuota, 2),
                'monto_cuota_proyectado' => round($cuotaMensual, 2),
                'capital_pagado' => 0,
                'interes_pagado' => 0,
                'mora_pagada' => 0,
                'otros_cargos_pagados' => 0,
                'monto_total_pagado' => 0,
                'capital_pendiente' => round($capitalPorCuota, 2),
                'interes_pendiente' => round($interesPorCuota, 2),
                'mora_pendiente' => 0,
                'otros_cargos_pendientes' => round($gastosPorCuota, 2),
                'monto_pendiente' => round($cuotaMensual, 2),
                'saldo_capital_credito' => round(max($saldoCapitalRestante, 0), 2),
                'dias_mora' => 0,
                'permite_pago_parcial' => true,
                'tipo_modificacion' => 'original',
            ]);
        }

        // ========== REGISTRAR MOVIMIENTO DE ENGANCHE ==========
        if ($enganchePagado > 0) {
            VentaCreditoMovimiento::create([
                'venta_credito_id' => $ventaCredito->id,
                'usuario_id' => Auth::id(),
                'sucursal_id' => $venta->sucursal_id,
                'numero_movimiento' => VentaCreditoMovimiento::generarNumeroMovimiento(),
                'tipo_movimiento' => 'enganche',
                'fecha_movimiento' => now(),
                'fecha_registro' => now(),
                'monto_total' => $enganchePagado,
                'capital' => 0, // el enganche no aplica a capital del crédito
                'interes' => 0,
                'mora' => 0,
                'otros_cargos' => 0,
                'saldo_capital' => $saldoFinanciar,
                'saldo_interes' => $interesTotal,
                'saldo_mora' => 0,
                'saldo_total' => $totalCredito,
                'forma_pago' => 'efectivo', // Se puede mejorar para usar el real
                'concepto' => "Enganche de venta a crédito {$venta->codigo_venta}",
                'estado' => 'activo',
                'moneda' => 'GTQ',
                'tipo_cambio' => 1,
            ]);
        }

        Log::info("Crédito de venta creado: {$ventaCredito->numero_credito}", [
            'venta_id' => $venta->id,
            'enganche' => $enganche,
            'saldo_financiar' => $saldoFinanciar,
            'interes_total' => $interesTotal,
            'total_credito' => $totalCredito,
            'cuota_mensual' => $cuotaMensual,
            'num_cuotas' => $numeroCuotas,
            'plan_pagos_creados' => $numeroCuotas
        ]);
    }

    /**
     * Configurar venta como APARTADO (anticipo + fecha límite)
     */
    private function configurarApartado(Venta $venta, array $data, float $anticipoPagado): void
    {
        $anticipo = $data['anticipo'] ?? $anticipoPagado;
        $diasApartado = $data['dias_apartado'] ?? 15;

        $saldoPendiente = $venta->total_final - $anticipo;
        $fechaLimite = now()->addDays($diasApartado);

        $venta->update([
            'anticipo_apartado' => $anticipo,
            'enganche' => $anticipo, // También guardamos como enganche para compatibilidad
            'dias_apartado' => $diasApartado,
            'plazo_dias' => $diasApartado,
            'saldo_pendiente' => $saldoPendiente,
            'total_pagado' => $anticipoPagado,
            'fecha_vencimiento' => $fechaLimite,
        ]);

        Log::info("Apartado configurado para venta {$venta->codigo_venta}", [
            'anticipo' => $anticipo,
            'dias' => $diasApartado,
            'saldo_pendiente' => $saldoPendiente,
            'fecha_limite' => $fechaLimite->format('Y-m-d')
        ]);
    }

    /**
     * Configurar venta como plan de pagos (legacy)
     */
    private function configurarPlanPagos(Venta $venta, array $data, float $enganche, float $saldo): void
    {
        $numeroCuotas = $data['numero_cuotas'] ?? 1;
        $tasaInteres = $data['tasa_interes'] ?? 0;
        $frecuencia = $data['frecuencia_pago'] ?? 'mensual';

        // Calcular intereses
        $intereses = ($saldo * ($tasaInteres / 100));
        $totalConIntereses = $saldo + $intereses;

        // Calcular cuota
        $montoCuota = $numeroCuotas > 0 ? $totalConIntereses / $numeroCuotas : $totalConIntereses;

        // Calcular fechas
        $fechaPrimerPago = match($frecuencia) {
            'semanal' => now()->addWeek(),
            'quincenal' => now()->addWeeks(2),
            'mensual' => now()->addMonth(),
            default => now()->addMonth(),
        };

        $semanasPorCuota = match($frecuencia) {
            'semanal' => 1,
            'quincenal' => 2,
            'mensual' => 4,
            default => 4,
        };

        $venta->update([
            'enganche' => $enganche,
            'numero_cuotas' => $numeroCuotas,
            'monto_cuota' => $montoCuota,
            'frecuencia_pago' => $frecuencia,
            'tasa_interes' => $tasaInteres,
            'intereses' => $intereses,
            'cuotas_pagadas' => 0,
            'fecha_proximo_pago' => $fechaPrimerPago,
            'fecha_vencimiento' => now()->addWeeks($numeroCuotas * $semanasPorCuota),
        ]);
    }
}
