<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use App\Models\AuditoriaLog;

class AuditLog
{
    /**
     * Rutas que NO deben auditarse (para evitar ruido)
     */
    private const EXCLUDED_ROUTES = [
        'api/v1/ping',
        'api/v1/health',
        'api/v1/version',
        'api/v1/BD',
        'api/ping',
        'sanctum/csrf-cookie',
        'api/v1/auditoria', // No auditar consultas de auditoría
    ];

    /**
     * Métodos que representan cambios en datos (siempre auditar)
     */
    private const MUTATING_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Rutas GET que también se auditan (descargas, exportaciones, reportes)
     */
    private const AUDITABLE_GET_PATTERNS = [
        '/exportar',
        '/pdf',
        '/excel',
        '/reporte',
        '/descargar',
        '/ficha',
    ];

    /**
     * Registrar auditoría de todas las operaciones relevantes
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
        $duration = round(($endTime - $startTime) * 1000, 2);

        // Determinar si debe auditarse
        if ($this->shouldAudit($request)) {
            $this->guardarAuditoria($request, $response, $duration);
        }

        return $response;
    }

    /**
     * Determinar si la request debe ser auditada
     */
    private function shouldAudit(Request $request): bool
    {
        $path = $request->path();
        $method = $request->method();

        // Excluir rutas específicas
        foreach (self::EXCLUDED_ROUTES as $route) {
            if (str_starts_with($path, $route) || str_contains($path, $route)) {
                return false;
            }
        }

        // Siempre auditar métodos que cambian datos
        if (in_array($method, self::MUTATING_METHODS)) {
            return true;
        }

        // Para GET, auditar descargas y exportaciones
        if ($method === 'GET') {
            foreach (self::AUDITABLE_GET_PATTERNS as $pattern) {
                if (str_contains(strtolower($path), strtolower($pattern))) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Guardar auditoría en base de datos
     */
    private function guardarAuditoria(Request $request, Response $response, float $duration): void
    {
        try {
            $user = Auth::user();
            $path = $request->path();
            $method = $request->method();
            $statusCode = $response->getStatusCode();

            // Extraer módulo y acción
            [$modulo, $accion] = $this->extraerModuloAccion($path, $method);

            // Sanitizar datos de entrada
            $datosRequest = $this->sanitizeInput($request->except([
                'password', 'password_confirmation', 'current_password',
                'new_password', 'token', 'api_token', '_token',
            ]));

            // Generar descripción
            $descripcion = $this->generarDescripcion($method, $path, $statusCode, $duration, $request);

            // Guardar en BD
            AuditoriaLog::create([
                'user_id' => $user?->id,
                'sucursal_id' => $user?->sucursal_id ?? session('sucursal_activa'),
                'modulo' => $modulo,
                'accion' => $accion,
                'descripcion' => $descripcion,
                'tabla' => $this->extraerTabla($path),
                'registro_id' => $this->extraerRegistroId($path),
                'datos_anteriores' => null,
                'datos_nuevos' => (!empty($datosRequest) && in_array($method, ['POST', 'PUT', 'PATCH']))
                    ? $datosRequest
                    : null,
                'ip_address' => $request->ip(),
                'user_agent' => substr($request->userAgent() ?? '', 0, 500),
                'metodo_http' => $method,
                'url' => substr($request->fullUrl(), 0, 2000),
                'codigo_respuesta' => $statusCode,
                'tiempo_respuesta_ms' => $duration,
            ]);

            // También log de archivo para respaldo
            $this->logToFile($request, $response, $duration, $user, $modulo, $accion);

        } catch (\Exception $e) {
            Log::error('Error guardando auditoría: ' . $e->getMessage(), [
                'path' => $request->path(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Extraer módulo y acción desde la ruta
     */
    private function extraerModuloAccion(string $path, string $method): array
    {
        // Remover prefijo api/v1/
        $cleanPath = preg_replace('/^api\/v1\//', '', $path);
        $segmentos = explode('/', $cleanPath);
        $recurso = $segmentos[0] ?? 'sistema';

        // Mapeo de módulos
        $modulosMap = [
            'auth' => 'autenticacion',
            'usuarios' => 'usuarios',
            'clientes' => 'clientes',
            'creditos-prendarios' => 'creditos',
            'prendas' => 'prendas',
            'ventas' => 'ventas',
            'compras' => 'compras',
            'bovedas' => 'boveda',
            'bovedas-movimientos' => 'boveda',
            'caja' => 'caja',
            'recibos' => 'recibos',
            'categorias-productos' => 'categorias',
            'sucursales' => 'sucursales',
            'gastos' => 'gastos',
            'reportes' => 'reportes',
            'dashboard' => 'dashboard',
            'permisos' => 'permisos',
            'codigos-prereservados' => 'codigos',
            'geonames' => 'geonames',
            'chatbot' => 'chatbot',
        ];

        $modulo = $modulosMap[$recurso] ?? $recurso;

        // Determinar acción según método y path
        $accion = match ($method) {
            'POST' => $this->determinarAccionPost($cleanPath),
            'PUT', 'PATCH' => 'actualizar',
            'DELETE' => 'eliminar',
            'GET' => $this->determinarAccionGet($cleanPath),
            default => strtolower($method),
        };

        return [$modulo, $accion];
    }

    /**
     * Determinar acción específica para POST
     */
    private function determinarAccionPost(string $path): string
    {
        $pathLower = strtolower($path);

        $acciones = [
            'login' => 'login',
            'logout' => 'logout',
            'pagar' => 'pago',
            'pagos' => 'pago',
            'aprobar' => 'aprobar',
            'rechazar' => 'rechazar',
            'anular' => 'anular',
            'toggle' => 'cambiar_estado',
            'movimientos' => 'movimiento',
            'cambiar-sucursal' => 'cambio_sucursal',
            'cambiar-password' => 'cambio_password',
            'foto' => 'subir_foto',
            'simular' => 'simular',
            'preliminar' => 'generar_documento',
            'reactivar' => 'reactivar',
            'reservar' => 'reservar',
            'liberar' => 'liberar',
            'refresh' => 'refresh_token',
            'desembolsar' => 'desembolsar',
            'recalcular' => 'recalcular',
            'sync' => 'sincronizar',
            'calcular' => 'calcular',
            'restaurar' => 'restaurar',
            'abrir' => 'apertura',
            'cerrar' => 'cierre',
        ];

        foreach ($acciones as $patern => $accion) {
            if (str_contains($pathLower, $patern)) {
                return $accion;
            }
        }

        return 'crear';
    }

    /**
     * Determinar acción específica para GET
     */
    private function determinarAccionGet(string $path): string
    {
        $pathLower = strtolower($path);

        if (str_contains($pathLower, 'pdf')) return 'exportar_pdf';
        if (str_contains($pathLower, 'excel')) return 'exportar_excel';
        if (str_contains($pathLower, 'exportar')) return 'exportar';
        if (str_contains($pathLower, 'reporte')) return 'generar_reporte';
        if (str_contains($pathLower, 'descargar')) return 'descargar';
        if (str_contains($pathLower, 'ficha')) return 'consultar_ficha';

        return 'consultar';
    }

    /**
     * Extraer nombre de tabla desde la ruta
     */
    private function extraerTabla(string $path): ?string
    {
        $cleanPath = preg_replace('/^api\/v1\//', '', $path);
        $segmentos = explode('/', $cleanPath);
        $recurso = $segmentos[0] ?? null;

        $tablasMap = [
            'usuarios' => 'users',
            'clientes' => 'clientes',
            'creditos-prendarios' => 'creditos_prendarios',
            'prendas' => 'prendas',
            'ventas' => 'ventas',
            'compras' => 'compras',
            'bovedas' => 'bovedas',
            'bovedas-movimientos' => 'boveda_movimientos',
            'caja' => 'caja_apertura_cierres',
            'recibos' => 'recibos',
            'categorias-productos' => 'categoria_productos',
            'sucursales' => 'sucursales',
            'gastos' => 'gastos',
        ];

        return $tablasMap[$recurso] ?? null;
    }

    /**
     * Extraer ID del registro desde la URL
     */
    private function extraerRegistroId(string $path): ?string
    {
        if (preg_match('/\/(\d+)(?:\/|$)/', $path, $matches)) {
            return $matches[1];
        }
        return null;
    }

    /**
     * Generar descripción legible
     */
    private function generarDescripcion(string $method, string $path, int $statusCode, float $tiempo, Request $request): string
    {
        $accionTexto = match ($method) {
            'GET' => 'Consulta/Descarga',
            'POST' => 'Creación/Acción',
            'PUT', 'PATCH' => 'Actualización',
            'DELETE' => 'Eliminación',
            default => $method,
        };

        $estado = ($statusCode >= 200 && $statusCode < 300) ? 'exitosa' : "fallida (HTTP {$statusCode})";

        // Simplificar ruta
        $rutaSimple = preg_replace('/^api\/v1\//', '', $path);
        $rutaSimple = preg_replace('/\/\d+/', '/{id}', $rutaSimple);

        // Agregar contexto específico
        $contexto = '';
        if ($method === 'POST' && str_contains($path, 'login')) {
            $contexto = ' - Usuario: ' . ($request->input('email') ?? $request->input('username') ?? 'N/A');
        } elseif (str_contains($path, 'pago') || str_contains($path, 'pagar')) {
            $monto = $request->input('monto') ?? $request->input('monto_pago');
            if ($monto) $contexto = " - Monto: Q{$monto}";
        }

        return "{$accionTexto} {$estado} en /{$rutaSimple}{$contexto} ({$tiempo}ms)";
    }

    /**
     * Sanitizar datos de entrada
     */
    private function sanitizeInput(array $input): array
    {
        $sensitiveFields = [
            'password', 'password_confirmation', 'token', 'api_key',
            'secret', 'credential', 'current_password', 'new_password'
        ];

        $result = [];
        foreach ($input as $key => $value) {
            if (in_array(strtolower($key), $sensitiveFields)) {
                continue; // No incluir campos sensibles
            }

            if (is_string($value) && strlen($value) > 500) {
                $result[$key] = substr($value, 0, 500) . '...[truncado]';
            } elseif (is_array($value)) {
                $result[$key] = $this->sanitizeInput($value);
            } else {
                $result[$key] = $value;
            }
        }

        return $result;
    }

    /**
     * Log de respaldo en archivo
     */
    private function logToFile(Request $request, Response $response, float $duration, $user, string $modulo, string $accion): void
    {
        $logData = [
            'user_id' => $user?->id,
            'user_email' => $user?->email,
            'modulo' => $modulo,
            'accion' => $accion,
            'method' => $request->method(),
            'path' => $request->path(),
            'ip' => $request->ip(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => $duration,
            'timestamp' => now()->toIso8601String(),
        ];

        $statusCode = $response->getStatusCode();
        if ($statusCode >= 500) {
            Log::error('AUDIT: Server Error', $logData);
        } elseif ($statusCode >= 400) {
            Log::warning('AUDIT: Client Error', $logData);
        } else {
            Log::info('AUDIT: Success', $logData);
        }
    }
}
