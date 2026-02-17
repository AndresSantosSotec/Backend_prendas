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
            \App\Http\Middleware\AuditLog::class, // 🔒 Auditoría de operaciones
        ]);

        // 🔒 Alias de middlewares personalizados
        $middleware->alias([
            'throttle.downloads' => \App\Http\Middleware\ThrottleDownloads::class,
            'sucursal.scope' => \App\Http\Middleware\SucursalScope::class,
            'superadmin' => \App\Http\Middleware\EnsureSuperAdmin::class,
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
        $exceptions->report(function (\Throwable $e) {
            try {
                \App\Models\SystemErrorLog::create([
                    'user_id' => auth()->id(),
                    'message' => $e->getMessage(),
                    'exception' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => collect($e->getTrace())->slice(0, 10)->toArray(),
                    'url' => request()->fullUrl(),
                    'method' => request()->method(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                    'input_data' => request()->except(['password', 'password_confirmation']),
                    'status_code' => method_exists($e, 'getStatusCode') ? $e->getStatusCode() : 500,
                ]);
            } catch (\Exception $ex) {
                // Si falla el log en BD, al menos que se guarde en archivo
                \Illuminate\Support\Facades\Log::error('Error registrando excepción en BD: ' . $ex->getMessage());
            }
        });
    })->create();
