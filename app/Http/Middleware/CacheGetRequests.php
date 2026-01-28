<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CacheGetRequests
{
    /**
     * Lista de rutas que deben ser cacheadas
     */
    protected $cacheableRoutes = [
        'categorias-producto',
        'denominaciones',
        'monedas',
        'sucursales',
    ];

    /**
     * Tiempo de cache en minutos
     */
    protected $cacheDuration = 60;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Solo cachear requests GET
        if ($request->method() !== 'GET') {
            return $next($request);
        }

        // Verificar si la ruta debe ser cacheada
        $shouldCache = false;
        foreach ($this->cacheableRoutes as $route) {
            if (str_contains($request->path(), $route)) {
                $shouldCache = true;
                break;
            }
        }

        if (!$shouldCache) {
            return $next($request);
        }

        // Generar clave de cache única basada en URL y parámetros
        $cacheKey = 'api_cache_' . md5($request->fullUrl() . $request->user()?->id);

        // Intentar obtener respuesta del cache
        $cachedResponse = Cache::get($cacheKey);

        if ($cachedResponse !== null) {
            return response()->json($cachedResponse)
                ->header('X-Cache', 'HIT')
                ->header('Cache-Control', 'public, max-age=' . ($this->cacheDuration * 60));
        }

        // Procesar request
        $response = $next($request);

        // Cachear solo respuestas exitosas
        if ($response->getStatusCode() === 200 && $response instanceof \Illuminate\Http\JsonResponse) {
            $data = json_decode($response->getContent(), true);
            Cache::put($cacheKey, $data, now()->addMinutes($this->cacheDuration));

            $response->header('X-Cache', 'MISS');
            $response->header('Cache-Control', 'public, max-age=' . ($this->cacheDuration * 60));
        }

        return $response;
    }
}
