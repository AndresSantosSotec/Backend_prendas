<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

        // Lista de orígenes permitidos (desarrollo + producción)
        $allowedOrigins = [
            'http://localhost:5000',
            'http://localhost:5173',
            'http://localhost:3000',
            'http://localhost:5174',
            'http://127.0.0.1:5000',
            'http://127.0.0.1:5173',
            'http://127.0.0.1:3000',
            'https://digiprenda.inniserver.net',
            'https://avanzadigiprenda.stockgenius-sotecpro.com',
            'https://crediprendasdigi.stockgenius-sotecpro.com',
            'https://prendamas.stockgenius-sotecpro.com',
        ];

        // Permitir localhost, subdominios de la plataforma o PWA standalone ('null')
        $isLocalhost = $origin && (
            preg_match('/^http:\/\/localhost:\d+$/', $origin) ||
            preg_match('/^http:\/\/127\.0\.0\.1:\d+$/', $origin) ||
            preg_match('/^https:\/\/.*\.stockgenius-sotecpro\.com$/', $origin) ||
            preg_match('/^https:\/\/.*\.inniserver\.net$/', $origin) ||
            $origin === 'null'
        );

        $isAllowed = ($origin && (in_array($origin, $allowedOrigins) || $isLocalhost)) || (!$origin && $request->hasHeader('X-Client-ID'));
        $allowOriginHeader = $origin ?: '*';

        $corsHeaders = 'Content-Type, Authorization, X-Requested-With, Accept, Origin, X-Client-ID, X-Sucursal-Activa';

        if ($isAllowed && $request->isMethod('OPTIONS')) {
            return response('', 204)
                ->header('Access-Control-Allow-Origin', $allowOriginHeader)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH')
                ->header('Access-Control-Allow-Headers', $corsHeaders)
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '86400');
        }

        $response = $next($request);

        if ($isAllowed) {
            // Manejar StreamedResponse y BinaryFileResponse de manera diferente
            if ($response instanceof StreamedResponse || $response instanceof BinaryFileResponse) {
                $response->headers->set('Access-Control-Allow-Origin', $allowOriginHeader);
                $response->headers->set('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH');
                $response->headers->set('Access-Control-Allow-Headers', $corsHeaders);
                $response->headers->set('Access-Control-Allow-Credentials', 'true');
                $response->headers->set('Access-Control-Max-Age', '86400');
                return $response;
            }

            return $response
                ->header('Access-Control-Allow-Origin', $allowOriginHeader)
                ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH')
                ->header('Access-Control-Allow-Headers', $corsHeaders)
                ->header('Access-Control-Allow-Credentials', 'true')
                ->header('Access-Control-Max-Age', '86400');
        }

        return $response;
    }
}

