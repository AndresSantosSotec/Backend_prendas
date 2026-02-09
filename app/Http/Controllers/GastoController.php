<?php

namespace App\Http\Controllers;

use App\Models\Gasto;
use App\Services\GastosService;
use App\Http\Requests\StoreGastoRequest;
use App\Http\Requests\UpdateGastoRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Controlador para el catálogo de gastos
 *
 * CRUD completo para administrar tipos de gastos que pueden
 * asociarse a créditos prendarios.
 */
class GastoController extends Controller
{
    protected GastosService $gastosService;

    public function __construct(GastosService $gastosService)
    {
        $this->gastosService = $gastosService;
    }

    /**
     * Listar gastos (paginado con filtros)
     *
     * GET /api/gastos
     * Query params:
     *   - tipo: FIJO|VARIABLE (filtrar por tipo)
     *   - buscar: string (buscar por nombre)
     *   - activos: bool (solo activos, default true)
     *   - per_page: int (paginación)
     *   - all: bool (sin paginar, obtener todos)
     */
    public function index(Request $request): JsonResponse
    {
        $query = Gasto::query();

        // Filtrar por tipo
        if ($request->filled('tipo')) {
            $query->tipo($request->tipo);
        }

        // Búsqueda por nombre
        if ($request->filled('buscar')) {
            $query->buscar($request->buscar);
        }

        // Por defecto solo activos, a menos que se pida explícitamente
        if ($request->boolean('activos', true)) {
            $query->activos();
        }

        // Incluir eliminados si se solicita
        if ($request->boolean('con_eliminados')) {
            $query->withTrashed();
        }

        // Ordenamiento dinámico
        $ordenCampo = $request->get('orden_campo', 'nombre');
        $ordenDireccion = $request->get('orden_direccion', 'asc');

        // Validar campos permitidos para ordenar
        $camposPermitidos = ['nombre', 'tipo', 'monto', 'porcentaje', 'activo', 'created_at'];
        if (!in_array($ordenCampo, $camposPermitidos)) {
            $ordenCampo = 'nombre';
        }

        // Validar dirección
        $ordenDireccion = in_array($ordenDireccion, ['asc', 'desc']) ? $ordenDireccion : 'asc';

        $query->orderBy($ordenCampo, $ordenDireccion);

        // Si se pide todos sin paginar
        if ($request->boolean('all')) {
            $gastos = $query->get();
            return response()->json([
                'success' => true,
                'data' => $gastos,
                'total' => $gastos->count(),
            ]);
        }

        // Paginado con validación de rango (mínimo 10, máximo 100)
        $perPage = $request->integer('per_page', 10);
        $perPage = max(10, min(100, $perPage)); // Asegurar rango 10-100
        $gastos = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $gastos->items(),
            'total' => $gastos->total(),
            'meta' => [
                'current_page' => $gastos->currentPage(),
                'last_page' => $gastos->lastPage(),
                'per_page' => $gastos->perPage(),
                'total' => $gastos->total(),
            ],
        ]);
    }

    /**
     * Crear un nuevo gasto
     *
     * POST /api/gastos
     */
    public function store(StoreGastoRequest $request): JsonResponse
    {
        $gasto = Gasto::create($request->validated());

        return response()->json([
            'success' => true,
            'message' => 'Gasto creado exitosamente',
            'data' => $gasto,
        ], 201);
    }

    /**
     * Obtener un gasto específico
     *
     * GET /api/gastos/{id}
     */
    public function show(int $id): JsonResponse
    {
        $gasto = Gasto::withTrashed()->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => $gasto,
        ]);
    }

    /**
     * Actualizar un gasto
     *
     * PUT /api/gastos/{id}
     */
    public function update(UpdateGastoRequest $request, int $id): JsonResponse
    {
        $gasto = Gasto::findOrFail($id);

        $data = $request->validated();

        // Limpiar campos según el tipo
        if (isset($data['tipo'])) {
            if ($data['tipo'] === Gasto::TIPO_FIJO) {
                $data['porcentaje'] = null;
            } elseif ($data['tipo'] === Gasto::TIPO_VARIABLE) {
                $data['monto'] = null;
            }
        }

        $gasto->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Gasto actualizado exitosamente',
            'data' => $gasto->fresh(),
        ]);
    }

    /**
     * Eliminar un gasto
     *
     * DELETE /api/gastos/{id}
     *
     * Estrategia:
     * - Si tiene créditos asociados: soft delete
     * - Si no tiene créditos: hard delete
     */
    public function destroy(int $id): JsonResponse
    {
        $gasto = Gasto::findOrFail($id);

        $resultado = $this->gastosService->eliminarGasto($gasto);

        return response()->json([
            'success' => $resultado['deleted'],
            'message' => $resultado['message'],
            'soft_delete' => $resultado['soft_delete'],
        ]);
    }

    /**
     * Restaurar un gasto eliminado
     *
     * POST /api/gastos/{id}/restaurar
     */
    public function restore(int $id): JsonResponse
    {
        $gasto = Gasto::withTrashed()->findOrFail($id);

        if (!$gasto->trashed()) {
            return response()->json([
                'success' => false,
                'message' => 'El gasto no está eliminado',
            ], 400);
        }

        $gasto->restore();

        return response()->json([
            'success' => true,
            'message' => 'Gasto restaurado exitosamente',
            'data' => $gasto,
        ]);
    }

    /**
     * Calcular valor de un gasto para un monto dado
     *
     * POST /api/gastos/{id}/calcular
     * Body: { "monto_otorgado": 5000 }
     */
    public function calcular(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'monto_otorgado' => 'required|numeric|min:0',
        ]);

        $gasto = Gasto::findOrFail($id);
        $valor = $gasto->calcularValor($request->monto_otorgado);

        return response()->json([
            'success' => true,
            'data' => [
                'gasto' => $gasto,
                'monto_otorgado' => $request->monto_otorgado,
                'valor_calculado' => $valor,
            ],
        ]);
    }
}
