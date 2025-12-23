<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class ClienteController extends Controller
{
    /**
     * Listar todos los clientes con paginación
     */
    public function index(Request $request): JsonResponse
    {
        $query = Cliente::query();

        // Filtros
        if ($request->has('estado') && $request->estado !== '' && $request->estado !== 'todos') {
            $query->where('estado', $request->estado);
        }

        if ($request->has('tipo_cliente') && $request->tipo_cliente !== '' && $request->tipo_cliente !== 'todos') {
            $query->where('tipo_cliente', $request->tipo_cliente);
        }

        if ($request->has('genero') && $request->genero !== '' && $request->genero !== 'todos') {
            $query->where('genero', $request->genero);
        }

        if ($request->has('sucursal') && $request->sucursal !== '' && $request->sucursal !== 'todos') {
            $query->where('sucursal', $request->sucursal);
        }

        if ($request->has('busqueda') && $request->busqueda !== '') {
            $busqueda = $request->busqueda;
            $query->where(function ($q) use ($busqueda) {
                $q->where('nombres', 'like', "%{$busqueda}%")
                  ->orWhere('apellidos', 'like', "%{$busqueda}%")
                  ->orWhere('dpi', 'like', "%{$busqueda}%")
                  ->orWhere('nit', 'like', "%{$busqueda}%")
                  ->orWhere('telefono', 'like', "%{$busqueda}%")
                  ->orWhere('email', 'like', "%{$busqueda}%");
            });
        }

        // Excluir eliminados
        $query->where('eliminado', false);

        // Ordenamiento
        $orderBy = $request->get('order_by', 'created_at');
        $orderDir = $request->get('order_dir', 'desc');
        $allowedOrderFields = ['nombres', 'apellidos', 'dpi', 'created_at', 'estado', 'tipo_cliente'];
        
        if (in_array($orderBy, $allowedOrderFields)) {
            $query->orderBy($orderBy, $orderDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Estadísticas
        $stats = [
            'total' => Cliente::where('eliminado', false)->count(),
            'activos' => Cliente::where('eliminado', false)->where('estado', 'activo')->count(),
            'inactivos' => Cliente::where('eliminado', false)->where('estado', 'inactivo')->count(),
            'vip' => Cliente::where('eliminado', false)->where('tipo_cliente', 'vip')->count(),
            'por_genero' => [
                'masculino' => Cliente::where('eliminado', false)->where('genero', 'masculino')->count(),
                'femenino' => Cliente::where('eliminado', false)->where('genero', 'femenino')->count(),
                'otro' => Cliente::where('eliminado', false)->where('genero', 'otro')->count(),
            ],
        ];

        // Sucursales únicas
        $sucursales = Cliente::where('eliminado', false)
            ->whereNotNull('sucursal')
            ->where('sucursal', '!=', '')
            ->distinct()
            ->pluck('sucursal')
            ->toArray();

        // Paginación
        $perPage = min((int) $request->get('per_page', 10), 100);
        $page = (int) $request->get('page', 1);

        $totalFiltrado = (clone $query)->count();
        $clientes = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response()->json([
            'success' => true,
            'data' => $clientes->map(function ($cliente) {
                return $this->formatCliente($cliente);
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
            'sucursales' => $sucursales,
        ]);
    }

    /**
     * Obtener un cliente por ID
     */
    public function show(string $id): JsonResponse
    {
        $cliente = Cliente::where('id', $id)
            ->where('eliminado', false)
            ->first();

        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatCliente($cliente)
        ]);
    }

    /**
     * Crear un nuevo cliente
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nombres' => 'required|string|max:255',
            'apellidos' => 'required|string|max:255',
            'dpi' => 'required|string|max:20|unique:clientes,dpi',
            'nit' => 'nullable|string|max:20',
            'fecha_nacimiento' => 'required|date',
            'genero' => 'required|in:masculino,femenino,otro',
            'telefono' => 'required|string|max:20',
            'telefono_secundario' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'direccion' => 'required|string',
            'municipio' => 'nullable|string|max:255',
            'departamento_geoname_id' => 'nullable|integer',
            'municipio_geoname_id' => 'nullable|integer',
            'fotografia' => 'nullable|string',
            'estado' => 'nullable|in:activo,inactivo',
            'sucursal' => 'nullable|string|max:255',
            'tipo_cliente' => 'nullable|in:regular,vip',
            'notas' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $data['estado'] = $data['estado'] ?? 'activo';
        $data['tipo_cliente'] = $data['tipo_cliente'] ?? 'regular';
        $data['eliminado'] = false;

        // Procesar fotografía si es base64
        if (!empty($data['fotografia']) && str_starts_with($data['fotografia'], 'data:image')) {
            $data['fotografia'] = $this->savePhotoFromBase64($data['fotografia']);
        }

        $cliente = Cliente::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Cliente creado exitosamente',
            'data' => $this->formatCliente($cliente)
        ], 201);
    }

    /**
     * Actualizar un cliente
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $cliente = Cliente::where('id', $id)
            ->where('eliminado', false)
            ->first();

        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente no encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'nombres' => 'sometimes|required|string|max:255',
            'apellidos' => 'sometimes|required|string|max:255',
            'dpi' => ['sometimes', 'required', 'string', 'max:20', Rule::unique('clientes')->ignore($cliente->id)],
            'nit' => 'nullable|string|max:20',
            'fecha_nacimiento' => 'sometimes|required|date',
            'genero' => 'sometimes|required|in:masculino,femenino,otro',
            'telefono' => 'sometimes|required|string|max:20',
            'telefono_secundario' => 'nullable|string|max:20',
            'email' => 'nullable|email|max:255',
            'direccion' => 'sometimes|required|string',
            'municipio' => 'nullable|string|max:255',
            'departamento_geoname_id' => 'nullable|integer',
            'municipio_geoname_id' => 'nullable|integer',
            'fotografia' => 'nullable|string',
            'estado' => 'nullable|in:activo,inactivo',
            'sucursal' => 'nullable|string|max:255',
            'tipo_cliente' => 'nullable|in:regular,vip',
            'notas' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Procesar fotografía si es base64
        if (isset($data['fotografia']) && str_starts_with($data['fotografia'], 'data:image')) {
            // Eliminar foto anterior si existe
            if ($cliente->fotografia && !str_starts_with($cliente->fotografia, 'data:image')) {
                Storage::disk('public')->delete($cliente->fotografia);
            }
            $data['fotografia'] = $this->savePhotoFromBase64($data['fotografia']);
        }

        $cliente->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Cliente actualizado exitosamente',
            'data' => $this->formatCliente($cliente)
        ]);
    }

    /**
     * Eliminar un cliente (soft delete)
     */
    public function destroy(string $id): JsonResponse
    {
        $cliente = Cliente::where('id', $id)
            ->where('eliminado', false)
            ->first();

        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente no encontrado'
            ], 404);
        }

        $cliente->update([
            'eliminado' => true,
            'eliminado_en' => now()
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cliente eliminado exitosamente'
        ]);
    }

    /**
     * Subir fotografía del cliente
     */
    public function uploadPhoto(Request $request, string $id): JsonResponse
    {
        $cliente = Cliente::where('id', $id)
            ->where('eliminado', false)
            ->first();

        if (!$cliente) {
            return response()->json([
                'success' => false,
                'message' => 'Cliente no encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'fotografia' => 'required|string', // Base64
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $base64 = $request->fotografia;

        if (!str_starts_with($base64, 'data:image')) {
            return response()->json([
                'success' => false,
                'message' => 'Formato de imagen inválido'
            ], 422);
        }

        // Eliminar foto anterior si existe
        if ($cliente->fotografia && !str_starts_with($cliente->fotografia, 'data:image')) {
            Storage::disk('public')->delete($cliente->fotografia);
        }

        $path = $this->savePhotoFromBase64($base64);
        $cliente->update(['fotografia' => $path]);

        return response()->json([
            'success' => true,
            'message' => 'Fotografía subida exitosamente',
            'data' => [
                'fotografia' => $cliente->fotografia_url
            ]
        ]);
    }

    /**
     * Guardar foto desde base64
     */
    private function savePhotoFromBase64(string $base64): string
    {
        // Extraer datos de la imagen
        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $matches)) {
            $extension = $matches[1];
            $imageData = base64_decode(substr($base64, strpos($base64, ',') + 1));
            
            // Generar nombre único
            $filename = 'clientes/' . uniqid() . '_' . time() . '.' . $extension;
            
            // Guardar en storage
            Storage::disk('public')->put($filename, $imageData);
            
            return $filename;
        }

        // Si no es base64 válido, retornar como está
        return $base64;
    }

    /**
     * Formatear cliente para respuesta
     */
    private function formatCliente(Cliente $cliente): array
    {
        return [
            'id' => (string) $cliente->id,
            'nombres' => $cliente->nombres,
            'apellidos' => $cliente->apellidos,
            'dpi' => $cliente->dpi,
            'nit' => $cliente->nit,
            'fechaNacimiento' => $cliente->fecha_nacimiento->format('Y-m-d'),
            'genero' => $cliente->genero,
            'telefono' => $cliente->telefono,
            'telefonoSecundario' => $cliente->telefono_secundario,
            'email' => $cliente->email,
            'direccion' => $cliente->direccion,
            'municipio' => $cliente->municipio,
            'departamentoGeonameId' => $cliente->departamento_geoname_id,
            'municipioGeonameId' => $cliente->municipio_geoname_id,
            'fotografia' => $cliente->fotografia_url,
            'estado' => $cliente->estado,
            'sucursal' => $cliente->sucursal,
            'tipoCliente' => $cliente->tipo_cliente,
            'notas' => $cliente->notas,
            'eliminado' => $cliente->eliminado,
            'creadoEn' => $cliente->created_at->toISOString(),
            'actualizadoEn' => $cliente->updated_at->toISOString(),
        ];
    }
}

