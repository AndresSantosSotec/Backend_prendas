<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CompressResponse
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Solo comprimir respuestas JSON mayores a 1KB
        if (
            $response instanceof Response &&
            $response->headers->get('Content-Type') === 'application/json' &&
            strlen($response->getContent()) > 1024 &&
            strpos($request->header('Accept-Encoding', ''), 'gzip') !== false
        ) {
            $compressed = gzencode($response->getContent(), 6); // Nivel de compresión 6

            $response->setContent($compressed);
            $response->headers->set('Content-Encoding', 'gzip');
            $response->headers->set('Content-Length', strlen($compressed));
            $response->headers->set('Vary', 'Accept-Encoding');
        }

        return $response;
    }
}
