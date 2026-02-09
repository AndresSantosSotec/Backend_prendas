<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class ThrottleDownloads
{
    /**
     * Limitar descargas por usuario/IP
     *
     * Límites:
     * - 20 descargas por hora por usuario autenticado
     * - 5 descargas por hora por IP no autenticada
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $ip = $request->ip();

        // Identificador único: usuario o IP
        $identifier = $user ? "user_{$user->id}" : "ip_{$ip}";

        // Límites diferentes para usuarios autenticados vs anónimos
        $maxAttempts = $user ? 20 : 5;
        $decayMinutes = 60;

        // Key para cache
        $key = "download_throttle_{$identifier}";

        // Obtener intentos actuales
        $attempts = Cache::get($key, 0);

        // Verificar límite
        if ($attempts >= $maxAttempts) {
            Log::warning('DOWNLOAD_THROTTLE_EXCEEDED', [
                'identifier' => $identifier,
                'attempts' => $attempts,
                'max_attempts' => $maxAttempts,
                'ip' => $ip,
                'user_id' => $user?->id,
                'path' => $request->path(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Demasiadas descargas. Intenta nuevamente en ' . $decayMinutes . ' minutos.',
                'retry_after' => $decayMinutes * 60,
            ], 429);
        }

        // Incrementar contador
        Cache::put($key, $attempts + 1, now()->addMinutes($decayMinutes));

        $response = $next($request);

        // Agregar headers de rate limiting
        $response->headers->set('X-RateLimit-Limit', $maxAttempts);
        $response->headers->set('X-RateLimit-Remaining', max(0, $maxAttempts - $attempts - 1));

        return $response;
    }
}
