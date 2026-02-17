<?php

namespace App\Http\Controllers;

use App\Models\Contabilidad\CtbNomenclatura;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class NomenclaturaController extends Controller
{
    /**
     * Listar cuentas contables
     */
    public function index(Request $request): JsonResponse
    {
        $query = CtbNomenclatura::query();

        // Filtros
        if ($request->has('tipo')) {
            $query->where('tipo', $request->tipo);
        }

        if ($request->has('estado')) {
            $query->where('estado', $request->boolean('estado'));
        }

        if ($request->has('nivel')) {
            $query->where('nivel', $request->nivel);
        }

        if ($request->has('acepta_movimientos')) {
            $query->where('acepta_movimientos', $request->boolean('acepta_movimientos'));
        }

        if ($request->has('buscar')) {
            $buscar = $request->buscar;
            $query->where(function ($q) use ($buscar) {
                $q->where('codigo_cuenta', 'like', "%{$buscar}%")
                    ->orWhere('nombre_cuenta', 'like', "%{$buscar}%");
            });
        }

        // Solo cuentas de detalle (que aceptan movimientos)
        if ($request->boolean('solo_detalle')) {
            $query->where('acepta_movimientos', true);
        }

        $cuentas = $query->with('cuentaPadre')
            ->orderBy('codigo_cuenta')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $cuentas,
        ]);
    }

    /**
     * Obtener cuenta por ID
     */
    public function show(int $id): JsonResponse
    {
        $cuenta = CtbNomenclatura::with(['cuentaPadre', 'cuentasHijas'])
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $cuenta,
        ]);
    }

    /**
     * Crear nueva cuenta
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'codigo_cuenta' => 'required|string|max:20|unique:ctb_nomenclatura,codigo_cuenta',
            'nombre_cuenta' => 'required|string|max:255',
            'tipo' => 'required|in:activo,pasivo,patrimonio,ingreso,gasto,costos,cuentas_orden',
            'naturaleza' => 'required|in:deudora,acreedora',
            'nivel' => 'required|integer|min:1',
            'cuenta_padre_id' => 'nullable|exists:ctb_nomenclatura,id',
            'acepta_movimientos' => 'boolean',
            'requiere_auxiliar' => 'boolean',
            'categoria_flujo' => 'in:operacion,inversion,financiamiento,ninguno',
            'estado' => 'boolean',
        ]);

        $cuenta = CtbNomenclatura::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cuenta contable creada exitosamente',
            'data' => $cuenta,
        ], 201);
    }

    /**
     * Actualizar cuenta
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $cuenta = CtbNomenclatura::findOrFail($id);

        $validated = $request->validate([
            'codigo_cuenta' => 'sometimes|string|max:20|unique:ctb_nomenclatura,codigo_cuenta,' . $id,
            'nombre_cuenta' => 'sometimes|string|max:255',
            'tipo' => 'sometimes|in:activo,pasivo,patrimonio,ingreso,gasto,costos,cuentas_orden',
            'naturaleza' => 'sometimes|in:deudora,acreedora',
            'nivel' => 'sometimes|integer|min:1',
            'cuenta_padre_id' => 'nullable|exists:ctb_nomenclatura,id',
            'acepta_movimientos' => 'boolean',
            'requiere_auxiliar' => 'boolean',
            'categoria_flujo' => 'in:operacion,inversion,financiamiento,ninguno',
            'estado' => 'boolean',
        ]);

        $cuenta->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Cuenta contable actualizada exitosamente',
            'data' => $cuenta->fresh(),
        ]);
    }

    /**
     * Eliminar cuenta
     */
    public function destroy(int $id): JsonResponse
    {
        $cuenta = CtbNomenclatura::findOrFail($id);

        // Verificar si tiene cuentas hijas
        if ($cuenta->cuentasHijas()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar una cuenta que tiene subcuentas',
            ], 422);
        }

        // Verificar si tiene movimientos
        if ($cuenta->movimientos()->count() > 0) {
            return response()->json([
                'success' => false,
                'message' => 'No se puede eliminar una cuenta que tiene movimientos contables',
            ], 422);
        }

        $cuenta->delete();

        return response()->json([
            'success' => true,
            'message' => 'Cuenta contable eliminada exitosamente',
        ]);
    }

    /**
     * Obtener árbol jerárquico de cuentas
     */
    public function getArbol(): JsonResponse
    {
        $cuentas = CtbNomenclatura::where('estado', true)
            ->whereNull('cuenta_padre_id')
            ->with(['cuentasHijas' => function ($query) {
                $query->where('estado', true)->with('cuentasHijas');
            }])
            ->orderBy('codigo_cuenta')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $cuentas,
        ]);
    }

    /**
     * Calcular saldo de una cuenta
     */
    public function calcularSaldo(int $id, Request $request): JsonResponse
    {
        $cuenta = CtbNomenclatura::findOrFail($id);

        $fechaDesde = $request->fecha_desde ?? null;
        $fechaHasta = $request->fecha_hasta ?? null;

        $saldo = $cuenta->calcularSaldo($fechaDesde, $fechaHasta);

        return response()->json([
            'success' => true,
            'data' => [
                'cuenta' => $cuenta,
                'saldo' => $saldo,
                'fecha_desde' => $fechaDesde,
                'fecha_hasta' => $fechaHasta,
            ],
        ]);
    }
}
