<?php

namespace App\Http\Controllers;

use App\Models\Contabilidad\CtbParametrizacionCuenta;
use App\Models\Contabilidad\CtbNomenclatura;
use App\Models\Contabilidad\CtbTipoPoliza;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ParametrizacionCuentasController extends Controller
{
    /**
     * Listar todas las parametrizaciones
     */
    public function index(Request $request): JsonResponse
    {
        $query = CtbParametrizacionCuenta::with([
            'cuentaContable',
            'tipoPoliza',
            'sucursal'
        ]);

        // Filtros
        if ($request->has('tipo_operacion')) {
            $query->where('tipo_operacion', $request->tipo_operacion);
        }

        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->has('sucursal_id')) {
            $query->paraSucursal($request->sucursal_id);
        }

        $parametrizaciones = $query->orderBy('tipo_operacion')
            ->orderBy('orden')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $parametrizaciones,
        ]);
    }

    /**
     * Obtener parametrización por tipo de operación
     */
    public function getPorOperacion(string $tipoOperacion): JsonResponse
    {
        $parametrizaciones = CtbParametrizacionCuenta::with([
            'cuentaContable',
            'tipoPoliza'
        ])
            ->where('tipo_operacion', $tipoOperacion)
            ->where('activo', true)
            ->orderBy('orden')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $parametrizaciones,
        ]);
    }

    /**
     * Crear nueva parametrización
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tipo_operacion' => 'required|string',
            'tipo_movimiento' => 'required|in:debe,haber',
            'cuenta_contable_id' => 'required|exists:ctb_nomenclatura,id',
            'tipo_poliza_id' => 'nullable|exists:ctb_tipo_poliza,id',
            'descripcion' => 'nullable|string|max:255',
            'activo' => 'boolean',
            'orden' => 'integer|min:1',
            'sucursal_id' => 'nullable|exists:sucursales,id',
        ]);

        $parametrizacion = CtbParametrizacionCuenta::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Parametrización creada exitosamente',
            'data' => $parametrizacion->load(['cuentaContable', 'tipoPoliza']),
        ], 201);
    }

    /**
     * Mostrar una parametrización específica
     */
    public function show(int $id): JsonResponse
    {
        $parametrizacion = CtbParametrizacionCuenta::with([
            'cuentaContable',
            'tipoPoliza',
            'sucursal'
        ])->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $parametrizacion,
        ]);
    }

    /**
     * Actualizar parametrización
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $parametrizacion = CtbParametrizacionCuenta::findOrFail($id);

        $validated = $request->validate([
            'tipo_operacion' => 'sometimes|string',
            'tipo_movimiento' => 'sometimes|in:debe,haber',
            'cuenta_contable_id' => 'sometimes|exists:ctb_nomenclatura,id',
            'tipo_poliza_id' => 'nullable|exists:ctb_tipo_poliza,id',
            'descripcion' => 'nullable|string|max:255',
            'activo' => 'boolean',
            'orden' => 'integer|min:1',
            'sucursal_id' => 'nullable|exists:sucursales,id',
        ]);

        $parametrizacion->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Parametrización actualizada exitosamente',
            'data' => $parametrizacion->fresh()->load(['cuentaContable', 'tipoPoliza']),
        ]);
    }

    /**
     * Eliminar parametrización
     */
    public function destroy(int $id): JsonResponse
    {
        $parametrizacion = CtbParametrizacionCuenta::findOrFail($id);
        $parametrizacion->delete();

        return response()->json([
            'success' => true,
            'message' => 'Parametrización eliminada exitosamente',
        ]);
    }

    /**
     * Activar/Desactivar parametrización
     */
    public function toggle(int $id): JsonResponse
    {
        $parametrizacion = CtbParametrizacionCuenta::findOrFail($id);
        $parametrizacion->update(['activo' => !$parametrizacion->activo]);

        return response()->json([
            'success' => true,
            'message' => 'Estado actualizado exitosamente',
            'data' => $parametrizacion,
        ]);
    }

    /**
     * Obtener tipos de operación disponibles
     */
    public function getTiposOperacion(): JsonResponse
    {
        $tiposOperacion = [
            ['value' => 'credito_desembolso', 'label' => 'Crédito - Desembolso'],
            ['value' => 'credito_pago_capital', 'label' => 'Crédito - Pago de Capital'],
            ['value' => 'credito_pago_interes', 'label' => 'Crédito - Pago de Intereses'],
            ['value' => 'credito_pago_mora', 'label' => 'Crédito - Pago de Mora'],
            ['value' => 'credito_gastos', 'label' => 'Crédito - Gastos'],
            ['value' => 'credito_cancelacion', 'label' => 'Crédito - Cancelación'],
            ['value' => 'venta_contado', 'label' => 'Venta - Contado'],
            ['value' => 'venta_credito', 'label' => 'Venta - Crédito'],
            ['value' => 'venta_apartado', 'label' => 'Venta - Apartado'],
            ['value' => 'venta_enganche', 'label' => 'Venta - Enganche'],
            ['value' => 'venta_abono', 'label' => 'Venta - Abono'],
            ['value' => 'compra_directa', 'label' => 'Compra Directa'],
            ['value' => 'caja_apertura', 'label' => 'Caja - Apertura'],
            ['value' => 'caja_cierre', 'label' => 'Caja - Cierre'],
            ['value' => 'caja_ingreso', 'label' => 'Caja - Ingreso'],
            ['value' => 'caja_egreso', 'label' => 'Caja - Egreso'],
            ['value' => 'boveda_deposito', 'label' => 'Bóveda - Depósito'],
            ['value' => 'boveda_retiro', 'label' => 'Bóveda - Retiro'],
            ['value' => 'ajuste_inventario', 'label' => 'Ajuste de Inventario'],
            ['value' => 'prenda_recuperada', 'label' => 'Prenda Recuperada'],
            ['value' => 'prenda_extraviada', 'label' => 'Prenda Extraviada'],
        ];

        return response()->json([
            'success' => true,
            'data' => $tiposOperacion,
        ]);
    }

    /**
     * Actualización masiva de parametrizaciones
     */
    public function updateBatch(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'parametrizaciones' => 'required|array',
            'parametrizaciones.*.id' => 'required|exists:ctb_parametrizacion_cuentas,id',
            'parametrizaciones.*.cuenta_contable_id' => 'required|exists:ctb_nomenclatura,id',
            'parametrizaciones.*.tipo_poliza_id' => 'nullable|exists:ctb_tipo_poliza,id',
            'parametrizaciones.*.orden' => 'required|integer|min:1',
            'parametrizaciones.*.activo' => 'required|boolean',
        ]);

        DB::beginTransaction();
        try {
            foreach ($validated['parametrizaciones'] as $item) {
                CtbParametrizacionCuenta::where('id', $item['id'])->update([
                    'cuenta_contable_id' => $item['cuenta_contable_id'],
                    'tipo_poliza_id' => $item['tipo_poliza_id'] ?? null,
                    'orden' => $item['orden'],
                    'activo' => $item['activo'],
                ]);
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Parametrizaciones actualizadas exitosamente',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Error al actualizar parametrizaciones: ' . $e->getMessage(),
            ], 500);
        }
    }
}
