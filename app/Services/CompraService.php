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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Event;

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
        return DB::transaction(function () use ($data) {
            $user = Auth::user();
            $sucursalId = $data['sucursal_id'] ?? $user->sucursal_id;

            if (!$sucursalId) {
                $sucursalId = Sucursal::first()?->id;
                if (!$sucursalId) {
                    throw new \Exception('No se pudo determinar la sucursal');
                }
            }

            // 1. Obtener datos del cliente (snapshot)
            $cliente = Cliente::findOrFail($data['cliente_id']);
            $categoria = CategoriaProducto::findOrFail($data['categoria_producto_id']);

            // 2. Generar códigos únicos
            $codigoCompra = $this->generarCodigoCompra($sucursalId);
            $codigoPrenda = $this->generarCodigoPrenda($data['categoria_producto_id']);

            // 3. Crear registro de compra (tabla independiente)
            $compra = Compra::create([
                'cliente_id' => $cliente->id,
                'categoria_producto_id' => $categoria->id,
                'sucursal_id' => $sucursalId,
                'usuario_id' => $user->id,
                'codigo_compra' => $codigoCompra,

                // Snapshot del cliente
                'cliente_nombre' => trim($cliente->nombres . ' ' . $cliente->apellidos),
                'cliente_documento' => $cliente->numero_documento ?? $cliente->documento ?? $cliente->dpi,
                'cliente_telefono' => $cliente->telefono,
                'cliente_codigo' => $cliente->codigo_cliente,

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

            // 7. Registrar movimiento de caja si es necesario
            if ($compra->genera_egreso_caja) {
                $movimiento = $this->registrarEgresoCaja($compra, $sucursalId);
                if ($movimiento) {
                    $compra->update(['movimiento_caja_id' => $movimiento->id]);
                }
            }

            // 8. Log de auditoría
            Log::info("Compra directa registrada", [
                'codigo_compra' => $compra->codigo_compra,
                'codigo_prenda' => $prenda->codigo_prenda,
                'monto' => $compra->monto_pagado,
                'cliente' => $compra->cliente_nombre,
                'usuario' => $user->name ?? $user->username,
            ]);

            // 9. Disparar evento (opcional para hooks futuros)
            Event::dispatch('compra.registrada', [$compra]);

            return $compra->load(['cliente', 'prenda', 'categoriaProducto', 'sucursal', 'usuario', 'camposDinamicos']);
        });
    }

    /**
     * Generar código único para la compra
     */
    private function generarCodigoCompra(int $sucursalId): string
    {
        $prefix = 'CMP-' . str_pad($sucursalId, 3, '0', STR_PAD_LEFT);
        $count = Compra::where('codigo_compra', 'like', $prefix . '%')->count() + 1;

        do {
            $codigo = $prefix . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);
            $existe = Compra::where('codigo_compra', $codigo)->exists();
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
        $count = Prenda::where('codigo_prenda', 'like', $prefix . '%')->count() + 1;

        do {
            $codigo = $prefix . '-' . str_pad($count, 6, '0', STR_PAD_LEFT);
            $existe = Prenda::where('codigo_prenda', $codigo)->exists();
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
        // Obtener campos dinámicos de la categoría
        $categoria = CategoriaProducto::find($compra->categoria_producto_id);
        if (!$categoria || !$categoria->campos_dinamicos) return;

        $camposDefinicion = $categoria->campos_dinamicos;

        foreach ($camposDinamicos as $nombreCampo => $valor) {
            if (empty($valor)) continue;

            // Buscar la definición del campo en el array de campos de la categoría
            $campoDef = collect($camposDefinicion)->firstWhere('nombre', $nombreCampo);
            if (!$campoDef) continue;

            CompraCampoDinamico::create([
                'compra_id' => $compra->id,
                'campo_dinamico_id' => 0, // No usamos ID porque están en JSON
                'valor' => is_array($valor) ? json_encode($valor) : $valor,
                'campo_nombre' => $campoDef['nombre'] ?? $nombreCampo,
                'campo_tipo' => $campoDef['tipo'] ?? 'texto',
            ]);
        }
    }

    /**
     * Registrar egreso en caja
     */
    private function registrarEgresoCaja(Compra $compra, int $sucursalId): ?MovimientoCaja
    {
        $cajaAbiertaId = session('caja_abierta_id') ?? $this->obtenerCajaAbiertaId($sucursalId);

        if (!$cajaAbiertaId) {
            Log::warning("No se pudo registrar egreso en caja para compra {$compra->codigo_compra}: No hay caja abierta");
            return null;
        }

        return MovimientoCaja::create([
            'caja_id' => $cajaAbiertaId,
            'tipo' => 'decremento',
            'monto' => $compra->monto_pagado,
            'concepto' => "Compra directa: {$compra->codigo_compra} - {$compra->descripcion}",
            'detalles_movimiento' => json_encode([
                'tipo_operacion' => 'compra_directa',
                'compra_id' => $compra->id,
                'codigo_compra' => $compra->codigo_compra,
                'cliente' => $compra->cliente_nombre,
                'descripcion' => $compra->descripcion,
            ]),
            'estado' => 'aplicado',
            'user_id' => $compra->usuario_id,
        ]);
    }

    /**
     * Obtener ID de caja abierta
     */
    private function obtenerCajaAbiertaId(int $sucursalId): ?int
    {
        return \App\Models\CajaAperturaCierre::where('sucursal_id', $sucursalId)
            ->whereNull('fecha_cierre')
            ->orderBy('created_at', 'desc')
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
     * Obtener detalle completo de una compra
     */
    public function obtenerDetalle(int $compraId): Compra
    {
        return Compra::with([
            'cliente',
            'prenda',
            'categoriaProducto',
            'sucursal',
            'usuario',
            'movimientoCaja',
            'camposDinamicos',
        ])->findOrFail($compraId);
    }
}
