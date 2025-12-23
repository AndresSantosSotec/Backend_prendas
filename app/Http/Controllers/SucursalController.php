<?php

namespace App\Http\Controllers;

use App\Models\Sucursal;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class SucursalController extends Controller
{
    /**
     * Listar todas las sucursales con paginación
     */
    public function index(Request $request): JsonResponse
    {
        $query = Sucursal::query();

        // Filtros
        if ($request->has('activa') && $request->activa !== '') {
            $query->where('activa', $request->boolean('activa'));
        }

        if ($request->has('busqueda') && $request->busqueda !== '') {
            $busqueda = $request->busqueda;
            $query->where(function ($q) use ($busqueda) {
                $q->where('codigo', 'like', "%{$busqueda}%")
                  ->orWhere('nombre', 'like', "%{$busqueda}%")
                  ->orWhere('ciudad', 'like', "%{$busqueda}%")
                  ->orWhere('departamento', 'like', "%{$busqueda}%");
            });
        }

        // Ordenamiento
        $orderBy = $request->get('order_by', 'created_at');
        $orderDir = $request->get('order_dir', 'desc');
        $allowedOrderFields = ['codigo', 'nombre', 'ciudad', 'activa', 'created_at'];
        
        if (in_array($orderBy, $allowedOrderFields)) {
            $query->orderBy($orderBy, $orderDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Estadísticas
        $stats = [
            'total' => Sucursal::count(),
            'activas' => Sucursal::where('activa', true)->count(),
            'inactivas' => Sucursal::where('activa', false)->count(),
        ];

        // Paginación
        $perPage = min((int) $request->get('per_page', 10), 100);
        $page = (int) $request->get('page', 1);

        $totalFiltrado = (clone $query)->count();
        $sucursales = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response()->json([
            'success' => true,
            'data' => $sucursales->map(function ($sucursal) {
                return $this->formatSucursal($sucursal);
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
     * Obtener todas las sucursales activas (para selects)
     */
    public function activas(): JsonResponse
    {
        $sucursales = Sucursal::where('activa', true)
            ->orderBy('nombre')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sucursales->map(function ($sucursal) {
                return [
                    'id' => (string) $sucursal->id,
                    'codigo' => $sucursal->codigo,
                    'nombre' => $sucursal->nombre,
                ];
            }),
        ]);
    }

    /**
     * Obtener una sucursal por ID
     */
    public function show(string $id): JsonResponse
    {
        $sucursal = Sucursal::find($id);

        if (!$sucursal) {
            return response()->json([
                'success' => false,
                'message' => 'Sucursal no encontrada'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatSucursal($sucursal)
        ]);
    }

    /**
     * Crear una nueva sucursal
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'codigo' => 'nullable|string|max:20|unique:sucursales,codigo',
            'nombre' => 'required|string|max:255',
            'direccion' => 'nullable|string',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'ciudad' => 'nullable|string|max:100',
            'departamento' => 'nullable|string|max:100',
            'municipio' => 'nullable|string|max:255',
            'departamento_geoname_id' => 'required|integer',
            'municipio_geoname_id' => 'nullable|integer',
            'pais' => 'nullable|string|max:100',
            'pais_geoname_id' => 'nullable|integer',
            'descripcion' => 'nullable|string',
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
        
        // Auto-generar código si no se proporciona
        if (empty($data['codigo'])) {
            $ultimaSucursal = Sucursal::withTrashed()->orderBy('id', 'desc')->first();
            $numero = $ultimaSucursal ? ((int) substr($ultimaSucursal->codigo, 4)) + 1 : 1;
            $data['codigo'] = 'SUC-' . str_pad($numero, 3, '0', STR_PAD_LEFT);
        }
        
        // Limpiar nombre del departamento (remover "Departamento de ")
        if (!empty($data['departamento'])) {
            $data['departamento'] = preg_replace('/^Departamento de\s+/i', '', $data['departamento']);
        }
        
        // Establecer Guatemala como país por defecto
        if (empty($data['pais'])) {
            $data['pais'] = 'Guatemala';
        }
        if (empty($data['pais_geoname_id'])) {
            $data['pais_geoname_id'] = 3595528; // ID de Guatemala en GeoNames
        }

        $sucursal = Sucursal::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Sucursal creada exitosamente',
            'data' => $this->formatSucursal($sucursal)
        ], 201);
    }

    /**
     * Actualizar una sucursal
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $sucursal = Sucursal::find($id);

        if (!$sucursal) {
            return response()->json([
                'success' => false,
                'message' => 'Sucursal no encontrada'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'codigo' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('sucursales')->ignore($sucursal->id)],
            'nombre' => 'sometimes|required|string|max:255',
            'direccion' => 'nullable|string',
            'telefono' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'ciudad' => 'nullable|string|max:100',
            'departamento' => 'nullable|string|max:100',
            'municipio' => 'nullable|string|max:255',
            'departamento_geoname_id' => 'sometimes|required|integer',
            'municipio_geoname_id' => 'nullable|integer',
            'pais' => 'nullable|string|max:100',
            'pais_geoname_id' => 'nullable|integer',
            'descripcion' => 'nullable|string',
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
        
        // Limpiar nombre del departamento (remover "Departamento de ")
        if (!empty($data['departamento'])) {
            $data['departamento'] = preg_replace('/^Departamento de\s+/i', '', $data['departamento']);
        }
        
        // Asegurar que el país sea Guatemala si no se especifica
        if (empty($data['pais'])) {
            $data['pais'] = 'Guatemala';
        }
        if (empty($data['pais_geoname_id'])) {
            $data['pais_geoname_id'] = 3595528;
        }

        $sucursal->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Sucursal actualizada exitosamente',
            'data' => $this->formatSucursal($sucursal->fresh())
        ]);
    }

    /**
     * Eliminar una sucursal (soft delete)
     */
    public function destroy(string $id): JsonResponse
    {
        $sucursal = Sucursal::find($id);

        if (!$sucursal) {
            return response()->json([
                'success' => false,
                'message' => 'Sucursal no encontrada'
            ], 404);
        }

        // Verificar si tiene clientes asociados
        $clientesCount = $sucursal->clientes()->where('eliminado', false)->count();
        if ($clientesCount > 0) {
            return response()->json([
                'success' => false,
                'message' => "No se puede eliminar la sucursal porque tiene {$clientesCount} cliente(s) asociado(s). Desactívela en su lugar."
            ], 422);
        }

        $sucursal->delete();

        return response()->json([
            'success' => true,
            'message' => 'Sucursal eliminada exitosamente'
        ]);
    }

    /**
     * Cambiar estado activa/inactiva
     */
    public function toggleActiva(string $id): JsonResponse
    {
        $sucursal = Sucursal::find($id);

        if (!$sucursal) {
            return response()->json([
                'success' => false,
                'message' => 'Sucursal no encontrada'
            ], 404);
        }

        $sucursal->activa = !$sucursal->activa;
        $sucursal->save();

        return response()->json([
            'success' => true,
            'message' => $sucursal->activa ? 'Sucursal activada' : 'Sucursal desactivada',
            'data' => $this->formatSucursal($sucursal)
        ]);
    }

    /**
     * Formatear sucursal para respuesta
     */
    private function formatSucursal(Sucursal $sucursal): array
    {
        return [
            'id' => (string) $sucursal->id,
            'codigo' => $sucursal->codigo,
            'nombre' => $sucursal->nombre,
            'direccion' => $sucursal->direccion,
            'telefono' => $sucursal->telefono,
            'email' => $sucursal->email,
            'ciudad' => $sucursal->ciudad,
            'departamento' => $sucursal->departamento,
            'municipio' => $sucursal->municipio,
            'departamentoGeonameId' => $sucursal->departamento_geoname_id,
            'municipioGeonameId' => $sucursal->municipio_geoname_id,
            'pais' => $sucursal->pais ?? 'Guatemala',
            'paisGeonameId' => $sucursal->pais_geoname_id ?? 3595528,
            'descripcion' => $sucursal->descripcion,
            'activa' => $sucursal->activa,
            'creadoEn' => $sucursal->created_at->toISOString(),
            'actualizadoEn' => $sucursal->updated_at->toISOString(),
        ];
    }
}
