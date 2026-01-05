<?php

namespace App\Http\Controllers;

use App\Models\CategoriaProducto;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CategoriaProductoController extends Controller
{
    /**
     * Listar todas las categorías con paginación
     */
    public function index(Request $request): JsonResponse
    {
        $query = CategoriaProducto::query();

        // Filtros
        if ($request->has('activa') && $request->activa !== '') {
            $query->where('activa', $request->boolean('activa'));
        }

        if ($request->has('busqueda') && $request->busqueda !== '') {
            $busqueda = $request->busqueda;
            $query->where(function ($q) use ($busqueda) {
                $q->where('codigo', 'like', "%{$busqueda}%")
                  ->orWhere('nombre', 'like', "%{$busqueda}%")
                  ->orWhere('descripcion', 'like', "%{$busqueda}%");
            });
        }

        // Ordenamiento
        $orderBy = $request->get('order_by', 'orden');
        $orderDir = $request->get('order_dir', 'asc');
        $allowedOrderFields = ['codigo', 'nombre', 'orden', 'activa', 'created_at'];
        
        if (in_array($orderBy, $allowedOrderFields)) {
            $query->orderBy($orderBy, $orderDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('orden', 'asc')->orderBy('nombre', 'asc');
        }

        // Estadísticas
        $stats = [
            'total' => CategoriaProducto::count(),
            'activas' => CategoriaProducto::where('activa', true)->count(),
            'inactivas' => CategoriaProducto::where('activa', false)->count(),
        ];

        // Paginación
        $perPage = min((int) $request->get('per_page', 10), 100);
        $page = (int) $request->get('page', 1);

        $totalFiltrado = (clone $query)->count();
        $categorias = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response()->json([
            'success' => true,
            'data' => $categorias->map(function ($categoria) {
                return $this->formatCategoria($categoria);
            }),
            'pagination' => [
                'total' => $totalFiltrado,
                'per_page' => $perPage,
                'current_page' => $page,
                'last_page' => ceil($totalFiltrado / $perPage),
                'from' => (($page - 1) * $perPage) + 1,
                'to' => min($page * $perPage, $totalFiltrado),
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * Obtener todas las categorías activas (para selects en frontend)
     */
    public function getActivas(): JsonResponse
    {
        $categorias = CategoriaProducto::where('activa', true)
            ->orderBy('orden', 'asc')
            ->orderBy('nombre', 'asc')
            ->get(['id', 'codigo', 'nombre', 'color', 'icono']);

        return response()->json([
            'success' => true,
            'data' => $categorias
        ]);
    }

    /**
     * Obtener una categoría por ID
     */
    public function show(string $id): JsonResponse
    {
        $categoria = CategoriaProducto::find($id);

        if (!$categoria) {
            return response()->json([
                'success' => false,
                'message' => 'Categoría no encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatCategoria($categoria)
        ]);
    }

    /**
     * Crear una nueva categoría
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'codigo' => 'nullable|string|max:20|unique:categoria_productos,codigo',
            'nombre' => 'required|string|max:255',
            'descripcion' => 'nullable|string',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'icono' => 'nullable|string|max:50',
            'orden' => 'nullable|integer|min:0',
            'activa' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $data['activa'] = $data['activa'] ?? true;
        $data['orden'] = $data['orden'] ?? 0;
        
        // Auto-generar código si no se proporciona
        if (empty($data['codigo'])) {
            $ultimaCategoria = CategoriaProducto::withTrashed()->orderBy('id', 'desc')->first();
            $numero = $ultimaCategoria ? ((int) substr($ultimaCategoria->codigo ?? 'CAT-000', 4)) + 1 : 1;
            $data['codigo'] = 'CAT-' . str_pad($numero, 3, '0', STR_PAD_LEFT);
        }

        $categoria = CategoriaProducto::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Categoría creada exitosamente',
            'data' => $this->formatCategoria($categoria)
        ], 201);
    }

    /**
     * Actualizar una categoría
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $categoria = CategoriaProducto::find($id);

        if (!$categoria) {
            return response()->json([
                'success' => false,
                'message' => 'Categoría no encontrada'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'codigo' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('categoria_productos')->ignore($categoria->id)],
            'nombre' => 'sometimes|required|string|max:255',
            'descripcion' => 'nullable|string',
            'color' => 'nullable|string|max:7|regex:/^#[0-9A-Fa-f]{6}$/',
            'icono' => 'nullable|string|max:50',
            'orden' => 'nullable|integer|min:0',
            'activa' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $categoria->update($validator->validated());

        return response()->json([
            'success' => true,
            'message' => 'Categoría actualizada exitosamente',
            'data' => $this->formatCategoria($categoria->fresh())
        ]);
    }

    /**
     * Eliminar una categoría (soft delete)
     */
    public function destroy(string $id): JsonResponse
    {
        $categoria = CategoriaProducto::find($id);

        if (!$categoria) {
            return response()->json([
                'success' => false,
                'message' => 'Categoría no encontrada'
            ], 404);
        }

        // TODO: Verificar si tiene productos asociados antes de eliminar
        // if ($categoria->productos()->exists()) {
        //     return response()->json([
        //         'success' => false,
        //         'message' => 'No se puede eliminar la categoría porque tiene productos asociados.'
        //     ], 409);
        // }

        $categoria->delete();

        return response()->json([
            'success' => true,
            'message' => 'Categoría eliminada exitosamente'
        ]);
    }

    /**
     * Cambiar estado activa/inactiva
     */
    public function toggleActiva(string $id): JsonResponse
    {
        $categoria = CategoriaProducto::find($id);

        if (!$categoria) {
            return response()->json([
                'success' => false,
                'message' => 'Categoría no encontrada'
            ], 404);
        }

        $categoria->activa = !$categoria->activa;
        $categoria->save();

        return response()->json([
            'success' => true,
            'message' => $categoria->activa ? 'Categoría activada' : 'Categoría desactivada',
            'data' => $this->formatCategoria($categoria)
        ]);
    }

    /**
     * Formatear categoría para respuesta
     */
    private function formatCategoria(CategoriaProducto $categoria): array
    {
        return [
            'id' => (string) $categoria->id,
            'codigo' => $categoria->codigo,
            'nombre' => $categoria->nombre,
            'descripcion' => $categoria->descripcion,
            'color' => $categoria->color,
            'icono' => $categoria->icono,
            'orden' => $categoria->orden,
            'activa' => $categoria->activa,
            'creadoEn' => $categoria->created_at->toISOString(),
            'actualizadoEn' => $categoria->updated_at->toISOString(),
        ];
    }
}
