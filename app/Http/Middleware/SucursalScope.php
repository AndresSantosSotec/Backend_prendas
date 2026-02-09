<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware para filtrar automáticamente por sucursal
 * - El superadmin puede cambiar de sucursal y ver todas
 * - Usuarios con sucursal_id = null pueden ver todo (sin restricción)
 * - Usuarios normales solo ven datos de su sucursal asignada
 */
class SucursalScope
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user) {
            return $next($request);
        }

        // Superadmin puede ver todo y cambiar de sucursal
        if ($user->rol === 'superadmin') {
            // Si el superadmin tiene una sucursal activa en la sesión, la usa
            $sucursalActiva = $request->header('X-Sucursal-Activa') ?? $user->sucursal_id;

            if ($sucursalActiva) {
                // Aplicar scope global para la sucursal seleccionada
                $this->applySucursalScope($sucursalActiva, $user);
            }
            // Si no hay sucursal activa, el superadmin ve todo (no se aplica scope)
        } else {
            // Usuarios normales: solo si tienen sucursal_id se aplica el scope
            // Si sucursal_id es null, pueden ver todas las sucursales
            if ($user->sucursal_id) {
                $this->applySucursalScope($user->sucursal_id, $user);
            }
            // Si no tienen sucursal_id asignada, ven todo (no se aplica scope)
        }

        return $next($request);
    }

    /**
     * Aplicar scope de sucursal a los modelos
     */
    protected function applySucursalScope($sucursalId, $user): void
    {
        // Inyectar sucursal_id en el request para usarlo en los controladores
        request()->merge(['_sucursal_scope' => $sucursalId]);

        // Guardar en el usuario actual para acceso rápido
        $user->_sucursal_activa = $sucursalId;

        Log::info('Scope de sucursal aplicado', [
            'user_id' => $user->id,
            'user_rol' => $user->rol,
            'sucursal_id' => $sucursalId
        ]);
    }
}
