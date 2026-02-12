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

    // =====================================
    // MÉTODOS PARA CRÉDITOS PRENDARIOS
    // =====================================

    /**
     * Registrar apertura de empeño/crédito
     */
    public static function logAperturaCredito($credito, ?string $descripcionAdicional = null): void
    {
        $clienteNombre = $credito->cliente ? "{$credito->cliente->nombres} {$credito->cliente->apellidos}" : 'N/A';
        $descripcion = "Apertura de crédito prendario #{$credito->id} - Cliente: {$clienteNombre} - Monto: Q{$credito->monto_prestamo}";
        if ($descripcionAdicional) {
            $descripcion .= " - {$descripcionAdicional}";
        }

        self::log(
            modulo: 'creditos',
            accion: 'apertura_credito',
            tabla: 'creditos_prendarios',
            registroId: $credito->id,
            datosNuevos: $credito->toArray(),
            descripcion: $descripcion
        );
    }

    /**
     * Registrar pago de crédito
     */
    public static function logPago($pago, $credito): void
    {
        self::log(
            modulo: 'pagos',
            accion: 'registrar_pago',
            tabla: 'pagos',
            registroId: $pago->id,
            datosNuevos: [
                'pago' => $pago->toArray(),
                'credito_id' => $credito->id,
            ],
            descripcion: "Pago de Q{$pago->monto} registrado para crédito #{$credito->id} - Tipo: {$pago->tipo_pago}"
        );
    }

    /**
     * Registrar renovación de crédito
     */
    public static function logRenovacion($creditoAnterior, $creditoNuevo): void
    {
        self::log(
            modulo: 'creditos',
            accion: 'renovacion',
            tabla: 'creditos_prendarios',
            registroId: $creditoNuevo->id,
            datosAnteriores: ['credito_anterior_id' => $creditoAnterior->id],
            datosNuevos: $creditoNuevo->toArray(),
            descripcion: "Renovación de crédito #{$creditoAnterior->id} → #{$creditoNuevo->id}"
        );
    }

    /**
     * Registrar remate de prenda
     */
    public static function logRemate($prenda, $credito): void
    {
        self::log(
            modulo: 'prendas',
            accion: 'remate',
            tabla: 'prendas',
            registroId: $prenda->id,
            datosNuevos: [
                'prenda_id' => $prenda->id,
                'credito_id' => $credito->id,
                'estado' => 'rematado',
            ],
            descripcion: "Prenda #{$prenda->id} rematada del crédito #{$credito->id}"
        );
    }

    // =====================================
    // MÉTODOS PARA CAJA Y BÓVEDA
    // =====================================

    /**
     * Registrar apertura de caja
     */
    public static function logAperturaCaja($caja, $montoInicial): void
    {
        self::log(
            modulo: 'caja',
            accion: 'apertura_caja',
            tabla: 'cajas',
            registroId: $caja->id,
            datosNuevos: [
                'caja_id' => $caja->id,
                'monto_inicial' => $montoInicial,
                'fecha_apertura' => now()->toDateTimeString(),
            ],
            descripcion: "Apertura de caja con monto inicial Q{$montoInicial}"
        );
    }

    /**
     * Registrar cierre de caja
     */
    public static function logCierreCaja($caja, $montoFinal, $diferencia): void
    {
        self::log(
            modulo: 'caja',
            accion: 'cierre_caja',
            tabla: 'cajas',
            registroId: $caja->id,
            datosNuevos: [
                'caja_id' => $caja->id,
                'monto_final' => $montoFinal,
                'diferencia' => $diferencia,
                'fecha_cierre' => now()->toDateTimeString(),
            ],
            descripcion: "Cierre de caja - Monto final: Q{$montoFinal}" . ($diferencia != 0 ? " - Diferencia: Q{$diferencia}" : "")
        );
    }

    /**
     * Registrar movimiento de caja
     */
    public static function logMovimientoCaja($movimiento, $tipo): void
    {
        self::log(
            modulo: 'caja',
            accion: "movimiento_{$tipo}",
            tabla: 'movimientos_caja',
            registroId: $movimiento->id ?? null,
            datosNuevos: is_array($movimiento) ? $movimiento : $movimiento->toArray(),
            descripcion: "Movimiento de caja: {$tipo} - Monto: Q" . ($movimiento['monto'] ?? $movimiento->monto ?? 0)
        );
    }

    /**
     * Registrar transferencia a bóveda
     */
    public static function logTransferenciaBoveda($origen, $destino, $monto, $tipo): void
    {
        self::log(
            modulo: 'boveda',
            accion: 'transferencia',
            tabla: 'movimientos_boveda',
            datosNuevos: [
                'origen' => $origen,
                'destino' => $destino,
                'monto' => $monto,
                'tipo' => $tipo,
            ],
            descripcion: "Transferencia {$tipo}: {$origen} → {$destino} por Q{$monto}"
        );
    }

    // =====================================
    // MÉTODOS PARA VENTAS
    // =====================================

    /**
     * Registrar venta
     */
    public static function logVenta($venta, $prendas = []): void
    {
        $ventaId = is_array($venta) ? ($venta['id'] ?? 'N/A') : ($venta->id ?? 'N/A');
        $total = is_array($venta) ? ($venta['total'] ?? 0) : ($venta->total ?? 0);
        
        self::log(
            modulo: 'ventas',
            accion: 'registrar_venta',
            tabla: 'ventas',
            registroId: is_array($venta) ? ($venta['id'] ?? null) : $venta->id,
            datosNuevos: [
                'venta' => is_array($venta) ? $venta : $venta->toArray(),
                'prendas_vendidas' => count($prendas),
            ],
            descripcion: "Venta #{$ventaId} registrada - Total: Q{$total}"
        );
    }

    /**
     * Registrar apartado
     */
    public static function logApartado($apartado): void
    {
        self::log(
            modulo: 'ventas',
            accion: 'registrar_apartado',
            tabla: 'apartados',
            registroId: $apartado->id ?? null,
            datosNuevos: is_array($apartado) ? $apartado : $apartado->toArray(),
            descripcion: "Apartado registrado - Monto: Q" . ($apartado['monto'] ?? $apartado->monto ?? 0)
        );
    }

    /**
     * Registrar cancelación de apartado
     */
    public static function logCancelacionApartado($apartado): void
    {
        self::log(
            modulo: 'ventas',
            accion: 'cancelar_apartado',
            tabla: 'apartados',
            registroId: $apartado->id ?? null,
            datosAnteriores: is_array($apartado) ? $apartado : $apartado->toArray(),
            descripcion: "Apartado cancelado - ID: " . ($apartado['id'] ?? $apartado->id ?? 'N/A')
        );
    }

    // =====================================
    // MÉTODOS PARA COTIZACIONES
    // =====================================

    /**
     * Registrar cotización
     */
    public static function logCotizacion($cotizacion, $accion = 'crear'): void
    {
        self::log(
            modulo: 'cotizaciones',
            accion: "cotizacion_{$accion}",
            tabla: 'cotizaciones',
            registroId: $cotizacion->id ?? null,
            datosNuevos: is_array($cotizacion) ? $cotizacion : $cotizacion->toArray(),
            descripcion: "Cotización " . ($accion === 'crear' ? 'creada' : $accion) . " - Valor estimado: Q" . ($cotizacion['valor_estimado'] ?? $cotizacion->valor_estimado ?? 0)
        );
    }

    // =====================================
    // MÉTODOS PARA COMPRAS
    // =====================================

    /**
     * Registrar compra directa
     */
    public static function logCompra($compra): void
    {
        self::log(
            modulo: 'compras',
            accion: 'registrar_compra',
            tabla: 'compras',
            registroId: $compra->id ?? null,
            datosNuevos: is_array($compra) ? $compra : $compra->toArray(),
            descripcion: "Compra directa registrada - Monto: Q" . ($compra['monto'] ?? $compra->monto ?? 0)
        );
    }

    // =====================================
    // MÉTODOS PARA USUARIOS Y PERMISOS
    // =====================================

    /**
     * Registrar cambio de permisos
     */
    public static function logCambioPermisos($usuario, $permisosAnteriores, $permisosNuevos): void
    {
        self::log(
            modulo: 'usuarios',
            accion: 'cambio_permisos',
            tabla: 'users',
            registroId: $usuario->id,
            datosAnteriores: ['permisos' => $permisosAnteriores],
            datosNuevos: ['permisos' => $permisosNuevos],
            descripcion: "Permisos actualizados para usuario {$usuario->username}"
        );
    }

    /**
     * Registrar cambio de estado de usuario
     */
    public static function logCambioEstadoUsuario($usuario, $estadoAnterior, $estadoNuevo): void
    {
        self::log(
            modulo: 'usuarios',
            accion: $estadoNuevo ? 'activar_usuario' : 'desactivar_usuario',
            tabla: 'users',
            registroId: $usuario->id,
            datosAnteriores: ['activo' => $estadoAnterior],
            datosNuevos: ['activo' => $estadoNuevo],
            descripcion: "Usuario {$usuario->username} " . ($estadoNuevo ? 'activado' : 'desactivado')
        );
    }

    // =====================================
    // MÉTODOS PARA REPORTES
    // =====================================

    /**
     * Registrar generación de reporte
     */
    public static function logGenerarReporte($tipoReporte, $filtros = [], $formato = 'pdf'): void
    {
        self::log(
            modulo: 'reportes',
            accion: 'generar_reporte',
            datosNuevos: [
                'tipo_reporte' => $tipoReporte,
                'filtros' => $filtros,
                'formato' => $formato,
            ],
            descripcion: "Reporte '{$tipoReporte}' generado en formato {$formato}"
        );
    }

    // =====================================
    // MÉTODOS PARA PRENDAS
    // =====================================

    /**
     * Registrar cambio de estado de prenda
     */
    public static function logCambioEstadoPrenda($prenda, $estadoAnterior, $estadoNuevo): void
    {
        self::log(
            modulo: 'prendas',
            accion: 'cambio_estado',
            tabla: 'prendas',
            registroId: $prenda->id,
            datosAnteriores: ['estado' => $estadoAnterior],
            datosNuevos: ['estado' => $estadoNuevo],
            descripcion: "Prenda #{$prenda->id} cambió de '{$estadoAnterior}' a '{$estadoNuevo}'"
        );
    }

    /**
     * Registrar tasación
     */
    public static function logTasacion($prenda, $valorAnterior, $valorNuevo): void
    {
        self::log(
            modulo: 'tasaciones',
            accion: 'tasacion',
            tabla: 'prendas',
            registroId: $prenda->id,
            datosAnteriores: ['valor_tasacion' => $valorAnterior],
            datosNuevos: ['valor_tasacion' => $valorNuevo],
            descripcion: "Prenda #{$prenda->id} tasada: Q{$valorAnterior} → Q{$valorNuevo}"
        );
    }

    // =====================================
    // MÉTODOS PARA GASTOS
    // =====================================

    /**
     * Registrar gasto
     */
    public static function logGasto($gasto): void
    {
        self::log(
            modulo: 'gastos',
            accion: 'registrar_gasto',
            tabla: 'gastos',
            registroId: $gasto->id ?? null,
            datosNuevos: is_array($gasto) ? $gasto : $gasto->toArray(),
            descripcion: "Gasto registrado: " . ($gasto['concepto'] ?? $gasto->concepto ?? 'N/A') . " - Q" . ($gasto['monto'] ?? $gasto->monto ?? 0)
        );
    }

    // =====================================
    // MÉTODOS PARA SUCURSALES
    // =====================================

    /**
     * Registrar operación de sucursal
     */
    public static function logOperacionSucursal($sucursal, $operacion): void
    {
        self::log(
            modulo: 'sucursales',
            accion: $operacion,
            tabla: 'sucursales',
            registroId: $sucursal->id ?? null,
            datosNuevos: is_array($sucursal) ? $sucursal : $sucursal->toArray(),
            descripcion: "Sucursal {$operacion}: " . ($sucursal['nombre'] ?? $sucursal->nombre ?? 'N/A')
        );
    }

    // =====================================
    // MÉTODO GENÉRICO PARA ACCIONES PERSONALIZADAS
    // =====================================

    /**
     * Registrar acción personalizada
     */
    public static function logAccion(
        string $modulo,
        string $accion,
        string $descripcion,
        ?string $tabla = null,
        $registro = null,
        ?array $datosAdicionales = null
    ): void {
        self::log(
            modulo: $modulo,
            accion: $accion,
            tabla: $tabla,
            registroId: $registro?->id ?? ($registro['id'] ?? null),
            datosNuevos: $datosAdicionales,
            descripcion: $descripcion
        );
    }
}
