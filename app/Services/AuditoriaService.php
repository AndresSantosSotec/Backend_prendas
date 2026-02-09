<?php

namespace App\Services;

use App\Models\AuditoriaLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Log;
class AuditoriaService
{
    /**
     * Registrar una acción en la auditoría
     */
    public static function log(
        string $modulo,
        string $accion,
        ?string $tabla = null,
        ?string $registroId = null,
        ?array $datosAnteriores = null,
        ?array $datosNuevos = null,
        ?string $descripcion = null
    ): void {
        try {
            $user = Auth::user();

            AuditoriaLog::create([
                'user_id' => $user?->id,
                'sucursal_id' => $user?->sucursal_id ?? request()->get('_sucursal_scope'),
                'modulo' => $modulo,
                'accion' => $accion,
                'tabla' => $tabla,
                'registro_id' => $registroId,
                'datos_anteriores' => $datosAnteriores ? self::limpiarDatos($datosAnteriores) : null,
                'datos_nuevos' => $datosNuevos ? self::limpiarDatos($datosNuevos) : null,
                'ip_address' => Request::ip(),
                'user_agent' => Request::userAgent(),
                'metodo_http' => Request::method(),
                'url' => Request::fullUrl(),
                'descripcion' => $descripcion,
            ]);
        } catch (\Exception $e) {
            // Log el error pero no interrumpir la operación principal
            Log::error('Error registrando auditoría', [
                'error' => $e->getMessage(),
                'modulo' => $modulo,
                'accion' => $accion
            ]);
        }
    }

    /**
     * Limpiar datos sensibles antes de guardar
     */
    private static function limpiarDatos(array $datos): array
    {
        $camposSensibles = ['password', 'password_confirmation', 'token', 'api_token', 'remember_token'];

        foreach ($camposSensibles as $campo) {
            if (isset($datos[$campo])) {
                $datos[$campo] = '***OCULTO***';
            }
        }

        return $datos;
    }

    /**
     * Registrar login exitoso
     */
    public static function logLogin($user): void
    {
        self::log(
            modulo: 'autenticacion',
            accion: 'login',
            tabla: 'users',
            registroId: $user->id,
            descripcion: "Usuario {$user->username} inició sesión"
        );
    }

    /**
     * Registrar logout
     */
    public static function logLogout($user): void
    {
        self::log(
            modulo: 'autenticacion',
            accion: 'logout',
            tabla: 'users',
            registroId: $user->id,
            descripcion: "Usuario {$user->username} cerró sesión"
        );
    }

    /**
     * Registrar cambio de contraseña
     */
    public static function logCambioContrasena($user): void
    {
        self::log(
            modulo: 'autenticacion',
            accion: 'cambio_contrasena',
            tabla: 'users',
            registroId: $user->id,
            descripcion: "Usuario {$user->username} cambió su contraseña"
        );
    }

    /**
     * Registrar creación de registro
     */
    public static function logCrear(string $modulo, string $tabla, $registro, ?string $descripcion = null): void
    {
        self::log(
            modulo: $modulo,
            accion: 'crear',
            tabla: $tabla,
            registroId: $registro->id ?? null,
            datosNuevos: $registro->toArray(),
            descripcion: $descripcion ?? "Registro creado en {$tabla}"
        );
    }

    /**
     * Registrar actualización de registro
     */
    public static function logActualizar(string $modulo, string $tabla, $registroAnterior, $registroNuevo, ?string $descripcion = null): void
    {
        self::log(
            modulo: $modulo,
            accion: 'actualizar',
            tabla: $tabla,
            registroId: $registroNuevo->id ?? null,
            datosAnteriores: $registroAnterior->toArray(),
            datosNuevos: $registroNuevo->toArray(),
            descripcion: $descripcion ?? "Registro actualizado en {$tabla}"
        );
    }

    /**
     * Registrar eliminación de registro
     */
    public static function logEliminar(string $modulo, string $tabla, $registro, ?string $descripcion = null): void
    {
        self::log(
            modulo: $modulo,
            accion: 'eliminar',
            tabla: $tabla,
            registroId: $registro->id ?? null,
            datosAnteriores: $registro->toArray(),
            descripcion: $descripcion ?? "Registro eliminado en {$tabla}"
        );
    }

    /**
     * Registrar exportación de datos
     */
    public static function logExportar(string $modulo, string $tipo, int $cantidad, ?string $descripcion = null): void
    {
        self::log(
            modulo: $modulo,
            accion: 'exportar',
            datosNuevos: ['tipo' => $tipo, 'cantidad' => $cantidad],
            descripcion: $descripcion ?? "Exportación de {$cantidad} registros en formato {$tipo}"
        );
    }

    /**
     * Registrar cambio de sucursal (solo superadmin)
     */
    public static function logCambioSucursal($user, $sucursalAnterior, $sucursalNueva): void
    {
        self::log(
            modulo: 'sistema',
            accion: 'cambio_sucursal',
            tabla: 'users',
            registroId: $user->id,
            datosAnteriores: ['sucursal_id' => $sucursalAnterior],
            datosNuevos: ['sucursal_id' => $sucursalNueva],
            descripcion: "SuperAdmin cambió de sucursal {$sucursalAnterior} a {$sucursalNueva}"
        );
    }
}
