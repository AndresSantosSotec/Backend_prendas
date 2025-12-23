<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleCors
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $origin = $request->headers->get('Origin');
        
        // Lista de orígenes permitidos
        $allowedOrigins = [
            'http://localhost:5000',
            'http://localhost:5173',
            'http://localhost:3000',
            'http://localhost:5174',
            'http://127.0.0.1:5000',
            'http://127.0.0.1:5173',
            'http://127.0.0.1:3000',
        ];

        // Permitir cualquier localhost o 127.0.0.1 con cualquier puerto
        $isLocalhost = $origin && (
            preg_match('/^http:\/\/localhost:\d+$/', $origin) ||
            preg_match('/^http:\/\/127\.0\.0\.1:\d+$/', $origin)
        );

        if ($origin && (in_array($origin, $allowedOrigins) || $isLocalhost)) {
            $response = $next($request);
            
            return $response
                ->header('Access-Control-Allow-Origin', $origin)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin')
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '86400');
        }

        // Para peticiones OPTIONS (preflight)
        if ($request->getMethod() === 'OPTIONS') {
            return response('', 200)
                ->header('Access-Control-Allow-Origin', $origin ?: '*')
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH')
                ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, Accept, Origin')
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '86400');
        }

        return $next($request);
    }
}

