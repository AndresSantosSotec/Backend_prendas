<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuthSecurityService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Listar todos los usuarios con paginación
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        // Filtros
        if ($request->has('activo') && $request->activo !== '') {
            $query->where('activo', $request->boolean('activo'));
        }

        if ($request->has('rol') && $request->rol !== '' && $request->rol !== 'todos') {
            $query->where('rol', $request->rol);
        }

        if ($request->has('busqueda') && $request->busqueda !== '') {
            $busqueda = $request->busqueda;
            $query->where(function ($q) use ($busqueda) {
                $q->where('name', 'like', "%{$busqueda}%")
                  ->orWhere('email', 'like', "%{$busqueda}%")
                  ->orWhere('username', 'like', "%{$busqueda}%");
            });
        }

        // Ordenamiento
        $orderBy = $request->get('order_by', 'created_at');
        $orderDir = $request->get('order_dir', 'desc');
        $allowedOrderFields = ['name', 'email', 'rol', 'created_at', 'activo'];
        
        if (in_array($orderBy, $allowedOrderFields)) {
            $query->orderBy($orderBy, $orderDir === 'asc' ? 'asc' : 'desc');
        } else {
            $query->orderBy('created_at', 'desc');
        }

        // Estadísticas (siempre se calculan sobre el total sin paginación)
        $stats = [
            'total' => User::count(),
            'activos' => User::where('activo', true)->count(),
            'inactivos' => User::where('activo', false)->count(),
            'por_rol' => [
                'administrador' => User::where('rol', 'administrador')->count(),
                'cajero' => User::where('rol', 'cajero')->count(),
                'tasador' => User::where('rol', 'tasador')->count(),
                'vendedor' => User::where('rol', 'vendedor')->count(),
                'supervisor' => User::where('rol', 'supervisor')->count(),
            ],
        ];

        // Paginación
        $perPage = min((int) $request->get('per_page', 10), 100); // Máximo 100 por página
        $page = (int) $request->get('page', 1);

        // Clonar query para contar total filtrado
        $totalFiltrado = (clone $query)->count();

        // Aplicar paginación
        $users = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        return response()->json([
            'success' => true,
            'data' => $users->map(function ($user) {
                return $this->formatUser($user);
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
     * Obtener un usuario por ID
     */
    public function show(string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $this->formatUser($user)
        ]);
    }

    /**
     * Crear un nuevo usuario
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username|regex:/^[a-zA-Z0-9_]+$/',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => AuthSecurityService::getPasswordValidationRules(),
            'rol' => 'required|in:administrador,cajero,tasador,supervisor,vendedor',
            'activo' => 'nullable|boolean',
        ], array_merge(
            AuthSecurityService::getPasswordValidationMessages(),
            ['username.regex' => 'El nombre de usuario solo puede contener letras, números y guiones bajos']
        ));

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $data['password'] = Hash::make($data['password']);
        $data['activo'] = $data['activo'] ?? true;

        $user = User::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Usuario creado exitosamente',
            'data' => $this->formatUser($user)
        ], 201);
    }

    /**
     * Actualizar un usuario
     */
    public function update(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'username' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'rol' => 'sometimes|required|in:administrador,cajero,tasador,supervisor,vendedor',
            'activo' => 'nullable|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();

        // Solo actualizar password si se proporciona
        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente',
            'data' => $this->formatUser($user)
        ]);
    }

    /**
     * Eliminar un usuario
     */
    public function destroy(string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        // No permitir eliminar el último administrador
        if ($user->rol === 'administrador') {
            $adminCount = User::where('rol', 'administrador')->count();
            if ($adminCount <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede eliminar el último administrador del sistema'
                ], 422);
            }
        }

        $user->delete();

        return response()->json([
            'success' => true,
            'message' => 'Usuario eliminado exitosamente'
        ]);
    }

    /**
     * Cambiar estado activo/inactivo
     */
    public function toggleActivo(string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        // No permitir desactivar el último administrador activo
        if ($user->rol === 'administrador' && $user->activo) {
            $adminCount = User::where('rol', 'administrador')
                ->where('activo', true)
                ->count();
            if ($adminCount <= 1) {
                return response()->json([
                    'success' => false,
                    'message' => 'No se puede desactivar el último administrador activo'
                ], 422);
            }
        }

        $user->activo = !$user->activo;
        $user->save();

        return response()->json([
            'success' => true,
            'message' => $user->activo ? 'Usuario activado' : 'Usuario desactivado',
            'data' => $this->formatUser($user)
        ]);
    }

    /**
     * Cambiar contraseña
     */
    public function changePassword(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'password' => AuthSecurityService::getPasswordValidationRules(),
            'password_confirmation' => 'required|string|same:password',
        ], AuthSecurityService::getPasswordValidationMessages());

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Error de validación',
                'errors' => $validator->errors()
            ], 422);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Contraseña actualizada exitosamente'
        ]);
    }

    /**
     * Formatear usuario para respuesta
     */
    private function formatUser(User $user): array
    {
        return [
            'id' => (string) $user->id,
            'nombre' => $user->name,
            'username' => $user->username,
            'email' => $user->email,
            'rol' => $user->rol,
            'activo' => $user->activo,
            'creadoEn' => $user->created_at->toISOString(),
            'actualizadoEn' => $user->updated_at->toISOString(),
        ];
    }
}

