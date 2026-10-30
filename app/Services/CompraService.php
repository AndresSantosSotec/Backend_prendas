<?php

namespace App\Services;

use App\Models\Prenda;
use App\Models\MovimientoCaja;
use App\Models\Cliente;
use App\Models\Sucursal;
use App\Models\CategoriaProducto;
use App\Models\Compra;
use App\Models\CompraCampoDinamico;
use App\Enums\EstadoPrenda;
use App\Services\CajaService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;
use Illuminate\Database\QueryException;

class CompraService
{
    /**
     * Procesar una compra directa de un artículo con tabla independiente
     *
     * @param array $data
     * @return Compra
     * @throws \Exception
     */
    public function procesarCompraDirecta(array $data): Compra
    {
        $intentos = 0;
        $maxIntentos = 5;

        while (true) {
            try {
                return DB::transaction(function () use ($data) {
                    $user = Auth::user();
                    $sucursalId = $data['sucursal_id'] ?? $data['_sucursal_scope'] ?? $user?->sucursal_id;

                    if (!$sucursalId) {
                        $sucursalId = Sucursal::first()?->id ?? 1;
                    }

                    // 1. Obtener datos del cliente (snapshot)
                    $cliente = isset($data['cliente_id']) && $data['cliente_id'] ? Cliente::find($data['cliente_id']) : null;
                    $categoria = CategoriaProducto::findOrFail($data['categoria_producto_id']);

                    // 2. Generar códigos únicos
                    $codigoCompra = $this->generarCodigoCompra($sucursalId);
                    $codigoPrenda = $this->generarCodigoPrenda($data['categoria_producto_id']);

                    // 3. Crear registro de compra (tabla independiente)
                    $compra = Compra::create([
                'cliente_id' => $cliente ? $cliente->id : null,
                'categoria_producto_id' => $categoria->id,
                'sucursal_id' => $sucursalId,
                'usuario_id' => $user->id,
                'codigo_compra' => $codigoCompra,

                // Snapshot del cliente
                'cliente_nombre' => $cliente ? trim($cliente->nombres . ' ' . $cliente->apellidos) : 'Consumidor Final',
                'cliente_documento' => $cliente ? ($cliente->numero_documento ?? $cliente->documento ?? $cliente->dpi) : 'C/F',
                'cliente_telefono' => $cliente ? $cliente->telefono : null,
                'cliente_codigo' => $cliente ? $cliente->codigo_cliente : null,

                // Snapshot de categoría
                'categoria_nombre' => $categoria->nombre,

                // Datos de la prenda
                'descripcion' => $data['descripcion'],
                'marca' => $data['marca'] ?? null,
                'modelo' => $data['modelo'] ?? null,
                'serie' => $data['serie'] ?? null,
                'color' => $data['color'] ?? null,
                'condicion' => $data['condicion'] ?? 'buena',

                // Valores económicos
                'valor_tasacion' => $data['valor_tasacion'] ?? $data['monto_pagado'],
                'monto_pagado' => $data['monto_pagado'],
                'precio_venta_sugerido' => $data['precio_venta'],

                // Método de pago
                'metodo_pago' => $data['metodo_pago'] ?? 'efectivo',
                'genera_egreso_caja' => ($data['metodo_pago'] ?? 'efectivo') === 'efectivo',

                // Estado y tracking
                'estado' => 'activa',
                'observaciones' => $data['observaciones'] ?? null,
                'fecha_compra' => now(),
                'codigo_prenda_generado' => $codigoPrenda,

                // Campos dinámicos (JSON en datos_adicionales)
                'datos_adicionales' => isset($data['campos_dinamicos']) && !empty($data['campos_dinamicos'])
                    ? $data['campos_dinamicos']
                    : null,
                    ]);

                    // 4. Crear la Prenda en inventario
                    $prenda = $this->crearPrendaInventario($compra, $codigoPrenda, $sucursalId);

                    // 5. Actualizar relación
                    $compra->update(['prenda_id' => $prenda->id]);

                    // 6. Procesar campos dinámicos si existen
                    if (isset($data['campos_dinamicos']) && is_array($data['campos_dinamicos'])) {
                        $this->procesarCamposDinamicos($compra, $data['campos_dinamicos']);
                    }

                    // 7. Registrar movimiento de caja si es necesario.
                    // Una compra en efectivo NO puede quedar registrada sin rebajar caja.
                    if ($compra->genera_egreso_caja) {
                        $movimiento = $this->registrarEgresoCaja($compra, (int) $sucursalId);
                        if (!$movimiento) {
                            throw new \RuntimeException(
                                "No se pudo registrar el egreso en caja para la compra {$compra->codigo_compra}. " .
                                'Verifique que exista una caja abierta hoy en la sucursal e intente de nuevo.'
                            );
                        }
                        $compra->update(['movimiento_caja_id' => $movimiento->id]);
                    }

                    if ($compra->genera_egreso_caja && !$compra->movimiento_caja_id) {
                        throw new \RuntimeException(
                            "La compra {$compra->codigo_compra} no puede guardarse porque no rebajó efectivo en caja."
                        );
                    }

                    // 8. Log de auditoría
                    Log::info("Compra directa registrada", [
                        'codigo_compra' => $compra->codigo_compra,
                        'codigo_prenda' => $prenda->codigo_prenda,
                        'monto' => $compra->monto_pagado,
                        'cliente' => $compra->cliente_nombre,
                        'usuario' => $user->name ?? $user->username,
                        'sucursal_id' => $sucursalId,
                        'metodo_pago' => $compra->metodo_pago,
                        'genera_egreso_caja' => $compra->genera_egreso_caja,
                        'movimiento_caja_id' => $compra->movimiento_caja_id,
                    ]);

                    // 9. Disparar evento (opcional para hooks futuros)
                    Event::dispatch('compra.registrada', [$compra]);

                    return $compra->load(['cliente', 'prenda', 'categoriaProducto', 'sucursal', 'usuario', 'camposDinamicos', 'movimientoCaja']);
                });
            } catch (QueryException $e) {
                $intentos++;
                $isDuplicate = str_contains((string) $e->getMessage(), 'Duplicate entry')
                    && (str_contains((string) $e->getMessage(), 'compras_codigo_compra_unique')
                        || str_contains((string) $e->getMessage(), 'prendas_codigo_prenda_unique'));

                if (!$isDuplicate || $intentos >= $maxIntentos) {
                    throw $e;
                }

                Log::warning('Colisión de código al crear compra, reintentando...', [
                    'intento' => $intentos,
                    'max_intentos' => $maxIntentos,
                ]);
            }
        }
    }

    /**
     * Generar código único para la compra
     */
    private function generarCodigoCompra(int $sucursalId): string
    {
        $prefix = 'CMP-' . str_pad($sucursalId, 3, '0', STR_PAD_LEFT);

        // Obtener el número más alto ya utilizado para este prefijo
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $ultimo = Compra::withTrashed()
                ->where('codigo_compra', 'like', $prefix . '-%')
                ->pluck('codigo_compra')
                ->map(fn($c) => (int) substr(strrchr($c, "-"), 1))
                ->max();
        } else {
            $ultimo = Compra::withTrashed()->where('codigo_compra', 'like', $prefix . '-%')
                ->selectRaw('MAX(CAST(SUBSTRING_INDEX(codigo_compra, "-", -1) AS UNSIGNED)) as max_num')
                ->value('max_num');
        }

        $count = ($ultimo ?? 0) + 1;

        // Verificar que no exista (salvaguarda ante concurrencia)
        do {
            $codigo = $prefix . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);
            $existe = Compra::withTrashed()->where('codigo_compra', $codigo)->exists();
            if ($existe) $count++;
        } while ($existe);

        return $codigo;
    }

    /**
     * Generar código único para la prenda
     */
    private function generarCodigoPrenda($categoriaId): string
    {
        $prefix = 'PRD-CMP-' . str_pad($categoriaId, 2, '0', STR_PAD_LEFT);

        // Obtener el número más alto ya utilizado para este prefijo
        $driver = DB::connection()->getDriverName();
        if ($driver === 'sqlite') {
            $ultimo = Prenda::withTrashed()
                ->where('codigo_prenda', 'like', $prefix . '-%')
                ->pluck('codigo_prenda')
                ->map(fn($c) => (int) substr(strrchr($c, "-"), 1))
                ->max();
        } else {
            $ultimo = Prenda::withTrashed()->where('codigo_prenda', 'like', $prefix . '-%')
                ->selectRaw('MAX(CAST(SUBSTRING_INDEX(codigo_prenda, "-", -1) AS UNSIGNED)) as max_num')
                ->value('max_num');
        }

        $count = ($ultimo ?? 0) + 1;

        // Verificar que no exista (salvaguarda ante concurrencia)
        do {
            $codigo = $prefix . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);
            $existe = Prenda::withTrashed()->where('codigo_prenda', $codigo)->exists();
            if ($existe) $count++;
        } while ($existe);

        return $codigo;
    }


    /**
     * Crear prenda en inventario
     */
    private function crearPrendaInventario(Compra $compra, string $codigo, int $sucursalId): Prenda
    {
        return Prenda::create([
            'credito_prendario_id' => null,
            'categoria_producto_id' => $compra->categoria_producto_id,
            'tasador_id' => $compra->usuario_id,
            'codigo_prenda' => $codigo,
            'descripcion' => $compra->descripcion,
            'marca' => $compra->marca,
            'modelo' => $compra->modelo,
            'serie' => $compra->serie,
            'color' => $compra->color,
            'valor_tasacion' => $compra->valor_tasacion,
            'valor_prestamo' => $compra->monto_pagado,
            'valor_venta' => $compra->precio_venta_sugerido,
            'precio_venta' => $compra->precio_venta_sugerido,
            'estado' => EstadoPrenda::EN_VENTA->value,
            'condicion' => $compra->condicion,
            'fecha_ingreso' => $compra->fecha_compra,
            'fecha_tasacion' => $compra->fecha_compra,
            'tipo_ingreso' => 'compra_directa',
            'sucursal_id' => $sucursalId,
            'observaciones' => "Compra directa #{$compra->codigo_compra}",
            'codigo_cliente_propietario' => $compra->cliente_codigo,
        ]);
    }

    /**
     * Procesar campos dinámicos
     */
    private function procesarCamposDinamicos(Compra $compra, array $camposDinamicos): void
    {
        try {
            // Obtener campos dinámicos de la categoría
            $categoria = CategoriaProducto::find($compra->categoria_producto_id);
            if (!$categoria) return;

            $camposDefinicion = $categoria->campos_dinamicos ?? $categoria->campos_adicionales;
            if (!$camposDefinicion) return;

            foreach ($camposDinamicos as $nombreCampo => $valor) {
                if ($valor === null || $valor === '') continue;

                // Buscar la definición del campo en el array de campos de la categoría
                $campoDef = collect($camposDefinicion)->firstWhere('nombre', $nombreCampo);

                CompraCampoDinamico::create([
                    'compra_id' => $compra->id,
                    'campo_dinamico_id' => 0, // No usamos ID porque están en JSON
                    'valor' => is_bool($valor) ? ($valor ? '1' : '0') : (is_array($valor) ? json_encode($valor) : (string)$valor),
                    'campo_nombre' => $campoDef['nombre'] ?? $nombreCampo,
                    'campo_tipo' => $campoDef['tipo'] ?? 'texto',
                ]);
            }
        } catch (\Exception $e) {
            Log::warning("Hotfix Compra: Error no fatal al guardar campos dinámicos para compra #{$compra->codigo_compra}: " . $e->getMessage());
        }
    }

    /**
     * Registrar egreso en la caja vigente de la sucursal.
     * No usa cajas cerradas, inconsistentes ni de otras sucursales.
     */
    public function registrarEgresoCaja(Compra $compra, int $sucursalId): ?MovimientoCaja
    {
        $caja = CajaService::getCajaAbiertaVigente(
            Auth::id() ?? $compra->usuario_id,
            $sucursalId
        );

        if (!$caja) {
            Log::warning("Compra #{$compra->codigo_compra}: no hay caja operativa hoy en sucursal {$sucursalId}.", [
                'compra_id' => $compra->id,
                'usuario_compra' => $compra->usuario_id,
                'auth_id' => Auth::id(),
            ]);
            return null;
        }

        $userId = Auth::id() ?? $compra->usuario_id ?? $caja->user_id;
        $concepto = mb_substr("Compra directa: {$compra->codigo_compra} - {$compra->descripcion}", 0, 250);

        Log::info("Egreso de compra se aplicará a caja operativa", [
            'codigo_compra' => $compra->codigo_compra,
            'caja_id' => $caja->id,
            'caja_user_id' => $caja->user_id,
            'caja_sucursal_id' => $caja->sucursal_id,
            'fecha_apertura' => $caja->fecha_apertura,
            'monto' => $compra->monto_pagado,
        ]);

        return MovimientoCaja::create([
            'caja_id' => $caja->id,
            'tipo' => 'decremento',
            'monto' => abs((float)$compra->monto_pagado),
            'concepto' => $concepto,
            'detalles_movimiento' => [
                'tipo_operacion' => 'compra_directa',
                'compra_id' => $compra->id,
                'codigo_compra' => $compra->codigo_compra,
                'cliente' => $compra->cliente_nombre,
                'descripcion' => $compra->descripcion,
                'monto' => (float)$compra->monto_pagado,
                'sucursal_id' => $sucursalId,
            ],
            'estado' => 'aplicado',
            'user_id' => $userId,
        ]);
    }

    /**
     * Sincronizar / reparar movimiento de caja para una compra en efectivo que no lo tenga
     */
    public function sincronizarEgresoCaja(Compra $compra): ?MovimientoCaja
    {
        // Si ya tiene movimiento de caja asociado, devolverlo
        if ($compra->movimiento_caja_id) {
            return MovimientoCaja::find($compra->movimiento_caja_id);
        }

        // Si la compra no genera egreso o no es en efectivo o está cancelada, no hacer nada
        if (!$compra->genera_egreso_caja || $compra->estado === 'cancelada') {
            return null;
        }

        $sucursalId = $compra->sucursal_id ?? 1;
        $movimiento = $this->registrarEgresoCaja($compra, $sucursalId);

        if ($movimiento) {
            $compra->update(['movimiento_caja_id' => $movimiento->id]);
            Log::info("Movimiento de caja sincronizado exitosamente para compra #{$compra->codigo_compra}", [
                'compra_id' => $compra->id,
                'movimiento_caja_id' => $movimiento->id,
                'caja_id' => $movimiento->caja_id,
                'monto' => $movimiento->monto,
            ]);
        }

        return $movimiento;
    }

    /**
     * Anular el egreso de caja asociado a una compra (si la caja original sigue abierta),
     * o registrar un ingreso compensatorio si la caja ya cerró.
     */
    public function anularEgresoCaja(Compra $compra, string $motivo = 'Compra cancelada/eliminada'): void
    {
        if (!$compra->movimiento_caja_id) {
            return;
        }

        $movimiento = MovimientoCaja::with('caja')->find($compra->movimiento_caja_id);
        if (!$movimiento || $movimiento->estaAnulado()) {
            return;
        }

        $caja = $movimiento->caja;
        if ($caja && $caja->estado === 'abierta' && !$caja->fecha_cierre) {
            // Caso 1: La caja original sigue abierta, simplemente anulamos el movimiento
            $movimiento->marcarAnulado([
                'observaciones_boveda' => $motivo,
            ]);
            return;
        }

        // Caso 2: La caja original ya estaba cerrada.
        // Se debe registrar un ingreso compensatorio en la caja abierta vigente de hoy.
        $sucursalId = (int) ($compra->sucursal_id ?? $caja?->sucursal_id ?? 1);
        $cajaHoy = CajaService::getCajaAbiertaVigente(Auth::id() ?? $compra->usuario_id, $sucursalId);

        if ($cajaHoy) {
            $ingresoCompensatorio = MovimientoCaja::create([
                'caja_id' => $cajaHoy->id,
                'tipo' => 'incremento',
                'monto' => abs((float) $compra->monto_pagado),
                'concepto' => mb_substr("Reversión egreso por anulación de compra #{$compra->codigo_compra}", 0, 250),
                'detalles_movimiento' => [
                    'tipo_operacion' => 'anulacion_compra_directa',
                    'codigo_compra' => $compra->codigo_compra,
                    'compra_id' => $compra->id,
                    'movimiento_caja_original_id' => $movimiento->id,
                    'caja_original_id' => $caja?->id,
                    'motivo' => $motivo,
                ],
                'estado' => 'aplicado',
                'user_id' => Auth::id() ?? $cajaHoy->user_id,
            ]);

            $movimiento->marcarAnulado([
                'observaciones_boveda' => "Anulado extemporáneamente. Compensado con ingreso #{$ingresoCompensatorio->id} en caja #{$cajaHoy->id}",
            ]);

            Log::info("Ingreso compensatorio registrado por anulación de compra extemporánea #{$compra->codigo_compra}", [
                'caja_hoy_id' => $cajaHoy->id,
                'monto' => $compra->monto_pagado,
                'ingreso_id' => $ingresoCompensatorio->id,
            ]);
        } else {
            Log::warning("No se pudo registrar ingreso compensatorio para compra #{$compra->codigo_compra}: no hay caja abierta hoy.", [
                'compra_id' => $compra->id,
                'monto' => $compra->monto_pagado,
            ]);
        }
    }

    /**
     * Obtener ID de caja abierta
     */
    private function obtenerCajaAbiertaId(int $sucursalId): ?int
    {
        return CajaService::getCajaAbierta(null, $sucursalId)?->id
            ?? \App\Models\CajaAperturaCierre::where('sucursal_id', $sucursalId)
                ->where('estado', 'abierta')
                ->whereNull('fecha_cierre')
                ->orderBy('fecha_apertura', 'desc')
                ->orderBy('id', 'desc')
                ->first()?->id;
    }

    /**
     * Listar compras con filtros
     */
    public function listarCompras(array $filtros = [])
    {
        $query = Compra::with([
            'cliente:id,nombres,apellidos,codigo_cliente',
            'categoriaProducto:id,nombre',
            'sucursal:id,nombre',
            'usuario:id,name,username',
            'prenda:id,codigo_prenda,estado',
            'movimientoCaja',
        ]);

        // Filtros
        if (!empty($filtros['sucursal_id'])) {
            $query->where('sucursal_id', $filtros['sucursal_id']);
        }

        if (!empty($filtros['estado'])) {
            $query->where('estado', $filtros['estado']);
        }

        if (!empty($filtros['cliente_id'])) {
            $query->where('cliente_id', $filtros['cliente_id']);
        }

        if (!empty($filtros['fecha_desde'])) {
            $query->whereDate('fecha_compra', '>=', $filtros['fecha_desde']);
        }

        if (!empty($filtros['fecha_hasta'])) {
            $query->whereDate('fecha_compra', '<=', $filtros['fecha_hasta']);
        }

        if (!empty($filtros['search'])) {
            $search = $filtros['search'];
            $query->where(function ($q) use ($search) {
                $q->where('codigo_compra', 'like', "%{$search}%")
                  ->orWhere('descripcion', 'like', "%{$search}%")
                  ->orWhere('cliente_nombre', 'like', "%{$search}%")
                  ->orWhere('codigo_prenda_generado', 'like', "%{$search}%");
            });
        }

        return $query->orderBy('fecha_compra', 'desc');
    }

    /**
     * Obtener detalle completo de una compra (con auto-reparación si faltaba movimiento de caja)
     */
    public function obtenerDetalle(int $compraId): Compra
    {
        $compra = Compra::with([
            'cliente',
            'prenda',
            'categoriaProducto',
            'sucursal',
            'usuario',
            'movimientoCaja',
            'camposDinamicos',
        ])->findOrFail($compraId);

        return $compra;
    }
}
