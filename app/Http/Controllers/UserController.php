<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AuthSecurityService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    /**
     * Listar todos los usuarios con paginación
     */
    public function index(Request $request): JsonResponse
    {
        $authUser = $request->user();
        $query = User::with('sucursal');

        // 🔒 PROTECCIÓN DE VISTA: Ocultar superadmins a usuarios no-superadmin
        if ($authUser->rol !== 'superadmin') {
            $query->where('rol', '!=', 'superadmin');
        }

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

        // Paginación con validación (mínimo 10, máximo 100)
        $perPage = (int) $request->get('per_page', 10);
        $perPage = max(10, min(100, $perPage)); // Asegurar rango 10-100
        $page = (int) $request->get('page', 1);

        // Clonar query para contar total filtrado
        $totalFiltrado = (clone $query)->count();

        // Aplicar paginación
        $users = $query->skip(($page - 1) * $perPage)->take($perPage)->get();

        // Estadísticas optimizadas (solo si se solicitan o es la primera carga)
        // Se pueden omitir con ?skip_stats=1 para mejorar rendimiento en navegación de páginas
        $stats = null;
        if (!$request->boolean('skip_stats')) {
            $statsQuery = User::query();

            // 🔒 PROTECCIÓN DE VISTA EN ESTADÍSTICAS
            if ($authUser->rol !== 'superadmin') {
                $statsQuery->where('rol', '!=', 'superadmin');
            }

            $statsRaw = $statsQuery->selectRaw('
                COUNT(*) as total,
                SUM(CASE WHEN activo = 1 THEN 1 ELSE 0 END) as activos,
                SUM(CASE WHEN activo = 0 THEN 1 ELSE 0 END) as inactivos,
                SUM(CASE WHEN rol = "administrador" THEN 1 ELSE 0 END) as administrador,
                SUM(CASE WHEN rol = "cajero" THEN 1 ELSE 0 END) as cajero,
                SUM(CASE WHEN rol = "tasador" THEN 1 ELSE 0 END) as tasador,
                SUM(CASE WHEN rol = "vendedor" THEN 1 ELSE 0 END) as vendedor,
                SUM(CASE WHEN rol = "supervisor" THEN 1 ELSE 0 END) as supervisor
            ')->first();

            $stats = [
                'total' => (int) $statsRaw->total,
                'activos' => (int) $statsRaw->activos,
                'inactivos' => (int) $statsRaw->inactivos,
                'por_rol' => [
                    'administrador' => (int) $statsRaw->administrador,
                    'cajero' => (int) $statsRaw->cajero,
                    'tasador' => (int) $statsRaw->tasador,
                    'vendedor' => (int) $statsRaw->vendedor,
                    'supervisor' => (int) $statsRaw->supervisor,
                ],
            ];
        }

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
        $user = User::with('sucursal')->find($id);

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
        $authUser = $request->user();

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username|regex:/^[a-zA-Z0-9_]+$/',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => AuthSecurityService::getPasswordValidationRules(),
            'rol' => 'required|in:superadmin,administrador,cajero,tasador,supervisor,vendedor',
            'activo' => 'nullable|boolean',
            'sucursal_id' => 'nullable|exists:sucursales,id',
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
        
        // 🔒 PROTECCIÓN DE ROL: Solo superadmin puede crear otro superadmin
        if ($data['rol'] === 'superadmin' && $authUser->rol !== 'superadmin') {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para crear un Super Administrador'
            ], 403);
        }

        $data['password'] = Hash::make($data['password']);
        $data['activo'] = $data['activo'] ?? true;

        // 🏢 ASIGNACIÓN DE SUCURSAL
        // SuperAdmin y Administrador pueden elegir la sucursal
        // Otros roles heredan la sucursal del usuario que está creando
        if (in_array($authUser->rol, ['superadmin', 'administrador']) && isset($data['sucursal_id'])) {
            // SuperAdmin y Administrador pueden elegir la sucursal
            $data['sucursal_id'] = $data['sucursal_id'];
        } elseif (!in_array($authUser->rol, ['superadmin', 'administrador'])) {
            // Otros roles: heredar su propia sucursal
            $data['sucursal_id'] = $authUser->sucursal_id;
        }
        // Si es superadmin o administrador y no envió sucursal_id, se mantiene como null (puede ver todas)

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

        $authUser = $request->user();

        // 🔒 PROTECCIÓN: No modificar superadmins si no eres superadmin
        if ($user->rol === 'superadmin' && $authUser->rol !== 'superadmin') {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para modificar a un Super Administrador'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'username' => ['sometimes', 'required', 'string', 'max:255', Rule::unique('users')->ignore($user->id)],
            'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => 'nullable|string|min:6',
            'rol' => 'sometimes|required|in:superadmin,administrador,cajero,tasador,supervisor,vendedor',
            'activo' => 'nullable|boolean',
            'sucursal_id' => 'nullable|exists:sucursales,id',
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

        // 🏢 VALIDACIÓN DE CAMBIO DE SUCURSAL
        // SuperAdmin y Administrador pueden cambiar la sucursal de un usuario
        if (isset($data['sucursal_id'])) {
            if (!in_array($authUser->rol, ['superadmin', 'administrador'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes permisos para cambiar la sucursal de un usuario'
                ], 403);
            }
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

        // 🔒 PROTECCIÓN: No eliminar superadmins si no eres superadmin
        $authUser = request()->user();
        if ($user->rol === 'superadmin' && $authUser->rol !== 'superadmin') {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para eliminar a un Super Administrador'
            ], 403);
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

        // 🔒 PROTECCIÓN: No desactivar superadmins si no eres superadmin
        $authUser = request()->user();
        if ($user->rol === 'superadmin' && $authUser->rol !== 'superadmin') {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para desactivar a un Super Administrador'
            ], 403);
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

        // 🔒 PROTECCIÓN: No cambiar password de superadmins si no eres superadmin
        $authUser = $request->user();
        if ($user->rol === 'superadmin' && $authUser->rol !== 'superadmin') {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permisos para cambiar la contraseña de un Super Administrador'
            ], 403);
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
     * Subir foto de perfil
     */
    public function uploadPhoto(Request $request, string $id): JsonResponse
    {
        $user = User::find($id);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado'
            ], 404);
        }

        $authUser = $request->user();

        // 🔒 PROTECCIÓN: Superadmin
        if ($user->rol === 'superadmin' && $authUser->rol !== 'superadmin') {
            return response()->json([
                'success' => false,
                'message' => 'No autorizado para modificar foto de Super Administrador'
            ], 403);
        }

        // Validar permisos: Mismo usuario O Admin (para otros)
        if ($authUser->id != $user->id && !in_array($authUser->rol, ['superadmin', 'administrador'])) {
             return response()->json([
                'success' => false,
                'message' => 'No autorizado'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'foto' => 'required|image|mimes:jpeg,png,jpg,webp|max:5120|dimensions:min_width=200,min_height=200',
        ], [
            'foto.dimensions' => 'La imagen debe tener al menos 200x200 píxeles.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Imagen inválida',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->hasFile('foto')) {
            // Eliminar anterior si existe
            if ($user->foto_url) {
                $oldPath = str_replace('/storage/', '', $user->foto_url);
                if (Storage::disk('public')->exists($oldPath)) {
                    Storage::disk('public')->delete($oldPath);
                }
            }

            $path = $request->file('foto')->store('usuarios/fotos', 'public');
            $user->foto_url = Storage::url($path);
            $user->save();

            return response()->json([
                'success' => true,
                'message' => 'Foto de perfil actualizada exitosamente',
                'data' => [
                    'foto_url' => $user->foto_url
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'No se recibió ninguna imagen'
        ], 400);
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
            'foto_url' => $user->foto_url,
            'rol' => $user->rol,
            'activo' => $user->activo,
            'sucursal_id' => $user->sucursal_id,
            'sucursal' => $user->sucursal ? [
                'id' => (string) $user->sucursal->id,
                'codigo' => $user->sucursal->codigo,
                'nombre' => $user->sucursal->nombre,
            ] : null,
            'creadoEn' => $user->created_at->toISOString(),
            'actualizadoEn' => $user->updated_at->toISOString(),
        ];
    }
}

