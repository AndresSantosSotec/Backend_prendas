<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->api(prepend: [
            \App\Http\Middleware\HandleCors::class,
            \App\Http\Middleware\SecurityHeaders::class,
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
            \App\Http\Middleware\CompressResponse::class,
            \App\Http\Middleware\CacheGetRequests::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);

        // Configurar el comportamiento de autenticación para APIs
        $middleware->redirectGuestsTo(function (Request $request) {
            // Si es una petición a la API, devolver 401 en lugar de redirigir
            if ($request->is('api/*')) {
                abort(401, 'No autenticado.');
            }
            // Para rutas web, redirigir al login (si existiera)
            return route('login');
        });

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
