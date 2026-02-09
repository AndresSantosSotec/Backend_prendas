<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuditLog
{
    /**
     * Operaciones críticas que deben ser auditadas
     */
    private const CRITICAL_ROUTES = [
        'auth/login',
        'auth/logout',
        'usuarios/*/cambiar-password',
        'creditos-prendarios',
        'creditos-prendarios/*',
        'cajas/abrir',
        'cajas/*/cerrar',
        'bovedas/*/movimientos',
        'ventas',
        'ventas/*',
        'prendas/*/vender',
        'recibos/*/anular',
    ];

    /**
     * Métodos que representan cambios en datos
     */
    private const MUTATING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Registrar auditoría de operaciones críticas
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Solo auditar si está habilitado en config
        if (!config('app.security.enable_audit_log', true)) {
            return $next($request);
        }

        $startTime = microtime(true);

        // Ejecutar request
        $response = $next($request);

        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2); // milisegundos

        // Determinar si debe auditarse
        if ($this->shouldAudit($request)) {
            $this->logAudit($request, $response, $duration);
        }

        return $response;
    }

    /**
     * Determinar si la request debe ser auditada
     */
    private function shouldAudit(Request $request): bool
    {
        // Solo auditar métodos que cambian datos
        if (!in_array($request->method(), self::MUTATING_METHODS)) {
            return false;
        }

        // Auditar rutas críticas
        $path = $request->path();

        foreach (self::CRITICAL_ROUTES as $route) {
            // Convertir wildcard a regex
            $pattern = str_replace('*', '[^/]+', $route);
            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Registrar log de auditoría
     */
    private function logAudit(Request $request, Response $response, float $duration): void
    {
        $user = Auth::user();

        $logData = [
            // Información del usuario
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'user_rol' => $user?->rol,

            // Información de la request
            'method' => $request->method(),
            'path' => $request->path(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),

            // Datos de entrada (sanitizados)
            'input' => $this->sanitizeInput($request->all()),

            // Respuesta
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $duration,

            // Metadata
            'timestamp' => now()->toIso8601String(),
            'environment' => app()->environment(),
        ];

        // Nivel de log según status code
        if ($response->getStatusCode() >= 500) {
            Log::error('AUDIT: Server Error', $logData);
        } elseif ($response->getStatusCode() >= 400) {
            Log::warning('AUDIT: Client Error', $logData);
        } else {
            Log::info('AUDIT: Success', $logData);
        }

        // Log adicional para operaciones específicas
        $this->logSpecificOperation($request, $response, $logData);
    }

    /**
     * Sanitizar datos de entrada (remover contraseñas, tokens, etc.)
     */
    private function sanitizeInput(array $input): array
    {
        $sensitiveFields = ['password', 'password_confirmation', 'token', 'api_key', 'secret'];

        foreach ($sensitiveFields as $field) {
            if (isset($input[$field])) {
                $input[$field] = '***REDACTED***';
            }
        }

        return $input;
    }

    /**
     * Log específico para operaciones críticas
     */
    private function logSpecificOperation(Request $request, Response $response, array $baseData): void
    {
        $path = $request->path();
        $user = Auth::user();

        // Login exitoso/fallido
        if (str_contains($path, 'auth/login')) {
            if ($response->getStatusCode() === 200) {
                Log::notice('LOGIN_SUCCESS', array_merge($baseData, [
                    'action' => 'login_success'
                ]));
            } else {
                Log::warning('LOGIN_FAILED', array_merge($baseData, [
                    'action' => 'login_failed',
                    'email' => $request->input('email')
                ]));
            }
        }

        // Cambio de contraseña
        if (str_contains($path, 'cambiar-password')) {
            Log::notice('PASSWORD_CHANGED', array_merge($baseData, [
                'action' => 'password_changed',
                'target_user' => $request->route('id')
            ]));
        }

        // Operaciones de caja
        if (str_contains($path, 'cajas/abrir')) {
            Log::notice('CAJA_OPENED', array_merge($baseData, [
                'action' => 'caja_opened',
                'monto_inicial' => $request->input('monto_inicial')
            ]));
        }

        if (str_contains($path, 'cajas') && str_contains($path, 'cerrar')) {
            Log::notice('CAJA_CLOSED', array_merge($baseData, [
                'action' => 'caja_closed',
                'caja_id' => $request->route('id')
            ]));
        }

        // Créditos prendarios
        if ($request->method() === 'POST' && str_contains($path, 'creditos-prendarios')) {
            Log::notice('CREDITO_CREATED', array_merge($baseData, [
                'action' => 'credito_created',
                'cliente_id' => $request->input('cliente_id'),
                'monto' => $request->input('monto_prestamo')
            ]));
        }

        // Ventas
        if ($request->method() === 'POST' && str_contains($path, 'ventas')) {
            Log::notice('VENTA_CREATED', array_merge($baseData, [
                'action' => 'venta_created',
                'total' => $request->input('total')
            ]));
        }

        // Anulaciones
        if (str_contains($path, 'anular')) {
            Log::warning('DOCUMENT_CANCELLED', array_merge($baseData, [
                'action' => 'document_cancelled',
                'document_id' => $request->route('id'),
                'motivo' => $request->input('motivo')
            ]));
        }
    }
}
