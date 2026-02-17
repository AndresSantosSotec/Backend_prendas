<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureSuperAdmin
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Verificar que el usuario esté autenticado
        if (!Auth::check()) {
            return response()->json([
                'message' => 'No autenticado'
            ], 401);
        }

        // Verificar que el usuario tenga rol superadmin
        $user = Auth::user();

        if (!$user || $user->role !== 'superadmin') {
            return response()->json([
                'message' => 'Acceso denegado. Se requiere rol de Super Administrador.'
            ], 403);
        }

        return $next($request);
    }
}
