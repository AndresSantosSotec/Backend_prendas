<?php

namespace App\Http\Controllers;

use App\Models\Permission;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PermissionController extends Controller
{
    /**
     * Obtener todos los permisos disponibles
     */
    public function index(): JsonResponse
    {
        $permisos = [];
        
        foreach (Permission::$permisosPorModulo as $modulo => $acciones) {
            $permisos[] = [
                'modulo' => $modulo,
                'acciones' => $acciones,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $permisos,
        ]);
    }

    /**
     * Obtener permisos de un usuario específico
     */
    public function getUserPermissions(string $userId): JsonResponse
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $user->getFormattedPermissions(),
        ]);
    }

    /**
     * Actualizar permisos de un usuario
     */
    public function updateUserPermissions(Request $request, string $userId): JsonResponse
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        // Validar que el usuario actual tenga permiso para asignar permisos
        $currentUser = $request->user();
        if (!$currentUser->hasPermission('usuarios', 'asignar_permisos')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para asignar permisos',
            ], 403);
        }

        $permisos = $request->input('permisos', []);
        $user->syncPermissions($permisos);

        return response()->json([
            'success' => true,
            'message' => 'Permisos actualizados correctamente',
            'data' => $user->getFormattedPermissions(),
        ]);
    }

    /**
     * Restablecer permisos por defecto según el rol
     */
    public function resetToDefault(Request $request, string $userId): JsonResponse
    {
        $user = User::find($userId);

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Usuario no encontrado',
            ], 404);
        }

        // Validar permisos
        $currentUser = $request->user();
        if (!$currentUser->hasPermission('usuarios', 'asignar_permisos')) {
            return response()->json([
                'success' => false,
                'message' => 'No tienes permiso para modificar permisos',
            ], 403);
        }

        $user->assignDefaultPermissions();

        return response()->json([
            'success' => true,
            'message' => 'Permisos restablecidos a los valores por defecto',
            'data' => $user->getFormattedPermissions(),
        ]);
    }

    /**
     * Obtener permisos por defecto de un rol
     */
    public function getRolePermissions(string $rol): JsonResponse
    {
        $permisosRol = Permission::$permisosPorRol[$rol] ?? null;

        if ($permisosRol === null) {
            return response()->json([
                'success' => false,
                'message' => 'Rol no encontrado',
            ], 404);
        }

        // Si es administrador, devolver todos los permisos
        if ($permisosRol === '*') {
            $permisos = [];
            foreach (Permission::$permisosPorModulo as $modulo => $acciones) {
                $permisos[] = [
                    'modulo' => $modulo,
                    'acciones' => $acciones,
                ];
            }
            return response()->json([
                'success' => true,
                'data' => $permisos,
            ]);
        }

        $permisos = [];
        foreach ($permisosRol as $modulo => $acciones) {
            $permisos[] = [
                'modulo' => $modulo,
                'acciones' => $acciones,
            ];
        }

        return response()->json([
            'success' => true,
            'data' => $permisos,
        ]);
    }
}

