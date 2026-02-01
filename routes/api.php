<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\GeoNamesController;
use App\Http\Controllers\CategoriaProductoController;
use App\Http\Controllers\CreditoPrendarioController;
use App\Http\Controllers\BovedaController;
use App\Http\Controllers\PrendaController;
use App\Http\Controllers\CajaController;
use App\Http\Controllers\DenominacionController;
use App\Http\Controllers\ReciboController;
use App\Http\Controllers\ReporteCajaController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\CompraController;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

    Route::get('/ping', function () {
        return response()->json([
            'status' => 'success',
            'message' => 'Pong'
        ]);
    });

Route::prefix('v1')->group(function () {
    // Rutas públicas de autenticación
    Route::post('/auth/login', [AuthController::class, 'login']);

    Route::get('/ping', function () {
        return response()->json([
            'status' => 'success',
            'message' => 'Pong'
        ]);
    });

    Route::get('/version', function () {
        return response()->json([
            'status' => 'success',
            'version' => '1.0.0'
        ]);
    });

    Route::get('/health', function () {
        return response()->json([
            'status' => 'success',
            'message' => 'API is healthy'
        ]);
    });

    Route::get('/BD', function () {
        try {
            DB::connection()->getPdo();
            return response()->json([
                'status' => 'success',
                'message' => 'Base de datos conectada'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Base de datos no conectada'
            ]);
        }
    });

    // Rutas protegidas
    Route::middleware('auth:sanctum')->group(function () {
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/graficas', [DashboardController::class, 'graficas']);
        Route::get('/dashboard/alertas', [DashboardController::class, 'alertas']);

        // Autenticación
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
        Route::post('/auth/refresh-token', [AuthController::class, 'refreshToken']);

        // Permisos
        Route::get('/permisos', [PermissionController::class, 'index']);
        Route::get('/permisos/rol/{rol}', [PermissionController::class, 'getRolePermissions']);
        Route::get('/usuarios/{id}/permisos', [PermissionController::class, 'getUserPermissions']);
        Route::put('/usuarios/{id}/permisos', [PermissionController::class, 'updateUserPermissions']);
        Route::post('/usuarios/{id}/permisos/reset', [PermissionController::class, 'resetToDefault']);

        // Usuarios
        Route::get('/usuarios', [UserController::class, 'index']);
        Route::get('/usuarios/{id}', [UserController::class, 'show']);
        Route::post('/usuarios', [UserController::class, 'store']);
        Route::put('/usuarios/{id}', [UserController::class, 'update']);
        Route::delete('/usuarios/{id}', [UserController::class, 'destroy']);
        Route::post('/usuarios/{id}/toggle-activo', [UserController::class, 'toggleActivo']);
        Route::post('/usuarios/{id}/cambiar-password', [UserController::class, 'changePassword']);

        // Clientes
        Route::get('/clientes/activos', [ClienteController::class, 'activos']);
        Route::get('/clientes', [ClienteController::class, 'index']);
        Route::get('/clientes/{id}', [ClienteController::class, 'show']);
        Route::get('/clientes/{id}/ficha', [ClienteController::class, 'ficha']);
        Route::get('/clientes/{id}/creditos-prendarios', [ClienteController::class, 'creditosPrendarios']);
        Route::post('/clientes', [ClienteController::class, 'store']);
        Route::put('/clientes/{id}', [ClienteController::class, 'update']);
        Route::delete('/clientes/{id}', [ClienteController::class, 'destroy']);
        Route::post('/clientes/{id}/foto', [ClienteController::class, 'uploadPhoto']);

        // Sucursales
        Route::get('/sucursales', [SucursalController::class, 'index']);
        Route::get('/sucursales/activas', [SucursalController::class, 'activas']);
        Route::get('/sucursales/{id}', [SucursalController::class, 'show']);
        Route::post('/sucursales', [SucursalController::class, 'store']);
        Route::put('/sucursales/{id}', [SucursalController::class, 'update']);
        Route::delete('/sucursales/{id}', [SucursalController::class, 'destroy']);
        Route::post('/sucursales/{id}/toggle-activa', [SucursalController::class, 'toggleActiva']);

        // Categorías de Productos
        Route::get('/categorias-productos', [CategoriaProductoController::class, 'index']);
        Route::get('/categorias-productos/activas', [CategoriaProductoController::class, 'getActivas']);
        Route::get('/categorias-productos/{id}', [CategoriaProductoController::class, 'show']);
        Route::post('/categorias-productos', [CategoriaProductoController::class, 'store']);
        Route::put('/categorias-productos/{id}', [CategoriaProductoController::class, 'update']);
        Route::delete('/categorias-productos/{id}', [CategoriaProductoController::class, 'destroy']);
        Route::post('/categorias-productos/{id}/toggle-activa', [CategoriaProductoController::class, 'toggleActiva']);

        // GeoNames (Guatemala)
        Route::get('/geonames/departamentos', [GeoNamesController::class, 'obtenerDepartamentos']);
        Route::get('/geonames/municipios/{geonameId}', [GeoNamesController::class, 'obtenerMunicipios']);
        Route::get('/geonames/guatemala-completo', [GeoNamesController::class, 'obtenerGuatemalaCompleto']);

        // Códigos Pre-reservados (para wizard de créditos)
        Route::get('/codigos-prereservados/token', [\App\Http\Controllers\CodigoPrereservadoController::class, 'generarToken']);
        Route::post('/codigos-prereservados/reservar', [\App\Http\Controllers\CodigoPrereservadoController::class, 'reservar']);
        Route::get('/codigos-prereservados/obtener', [\App\Http\Controllers\CodigoPrereservadoController::class, 'obtener']);

        // Bóvedas
        Route::get('/bovedas/consolidacion', [BovedaController::class, 'consolidacion']); // Antes de {id}
        Route::get('/bovedas/exportar', [BovedaController::class, 'exportarBovedas']);
        Route::get('/bovedas/consolidacion/exportar', [BovedaController::class, 'exportarConsolidacion']);
        Route::get('/bovedas/consolidacion/pdf', [BovedaController::class, 'exportarConsolidacionPDF']);
        Route::get('/bovedas', [BovedaController::class, 'index']);
        Route::post('/bovedas', [BovedaController::class, 'store']);
        Route::get('/bovedas/{id}', [BovedaController::class, 'show']);
        Route::put('/bovedas/{id}', [BovedaController::class, 'update']);
        Route::delete('/bovedas/{id}', [BovedaController::class, 'destroy']);
        Route::post('/bovedas/{id}/movimientos', [BovedaController::class, 'registrarMovimiento']);
        Route::get('/bovedas/{id}/historial', [BovedaController::class, 'historialMovimientos']);
        Route::get('/bovedas/{id}/exportar-movimientos', [BovedaController::class, 'exportarMovimientos']);
        Route::get('/bovedas/{id}/exportar-movimientos-pdf', [BovedaController::class, 'exportarMovimientosPDF']);

        // Movimientos de bóveda
        Route::get('/bovedas-movimientos/pendientes', [BovedaController::class, 'movimientosPendientes']);
        Route::post('/bovedas-movimientos/{id}/aprobar', [BovedaController::class, 'aprobarMovimiento']);
        Route::post('/bovedas-movimientos/{id}/rechazar', [BovedaController::class, 'rechazarMovimiento']);
        Route::post('/codigos-prereservados/liberar', [\App\Http\Controllers\CodigoPrereservadoController::class, 'liberar']);

        // Recibos - rutas sin parámetro ID primero
        Route::get('/recibos', [ReciboController::class, 'index']);
        Route::post('/recibos', [ReciboController::class, 'store']);
        Route::get('/recibos/siguiente-numero', [ReciboController::class, 'siguienteNumero']);
        Route::get('/recibos/reporte', [ReciboController::class, 'reporte']);
        Route::get('/recibos/buscar-cliente', [ReciboController::class, 'buscarCliente']);
        // Recibos - rutas con parámetro ID después
        Route::get('/recibos/{id}', [ReciboController::class, 'show']);
        Route::post('/recibos/{id}/anular', [ReciboController::class, 'anular']);
        Route::get('/recibos/{id}/pdf', [ReciboController::class, 'generarPDF']);

        // Créditos Prendarios
        Route::get('/creditos-prendarios', [CreditoPrendarioController::class, 'index']);
        Route::get('/creditos-prendarios/estadisticas', [CreditoPrendarioController::class, 'getEstadisticas']);
        Route::get('/creditos-prendarios/{id}', [CreditoPrendarioController::class, 'show']);
        Route::get('/creditos-prendarios/{id}/plan-pagos', [CreditoPrendarioController::class, 'getPlanPagos']);
        Route::get('/creditos-prendarios/{id}/movimientos', [CreditoPrendarioController::class, 'getMovimientos']);
        Route::get('/creditos-prendarios/{id}/saldo', [CreditoPrendarioController::class, 'getSaldo']);
        Route::post('/creditos-prendarios', [CreditoPrendarioController::class, 'store']);
        Route::put('/creditos-prendarios/{id}', [CreditoPrendarioController::class, 'update']);
        Route::patch('/creditos-prendarios/{id}', [CreditoPrendarioController::class, 'update']);
        Route::post('/credigos-prendarios/{id}/desembolsar', [CreditoPrendarioController::class, 'desembolsar']);
        Route::post('/creditos-prendarios/{id}/pagar', [CreditoPrendarioController::class, 'pagar']);
        Route::post('/creditos-prendarios/{id}/aprobar', [CreditoPrendarioController::class, 'aprobar']);
        Route::post('/creditos-prendarios/{id}/rechazar', [CreditoPrendarioController::class, 'rechazar']);
        Route::get('/creditos-prendarios/{id}/transiciones', [CreditoPrendarioController::class, 'getTransiciones']);
        Route::get('/creditos-prendarios/{id}/auditoria', [CreditoPrendarioController::class, 'getAuditoria']);
        Route::get('/creditos-prendarios/{id}/plan-pagos/pdf', [CreditoPrendarioController::class, 'descargarPlanPagos']);
        Route::get('/creditos-prendarios/{id}/contrato/pdf', [CreditoPrendarioController::class, 'descargarContrato']);
        Route::get('/creditos-prendarios/{id}/recibo/pdf', [CreditoPrendarioController::class, 'descargarRecibo']);
        Route::get('/creditos-prendarios/{id}/historial-pagos/pdf', [CreditoPrendarioController::class, 'descargarHistorialPagos']);
        Route::post('/creditos-prendarios/preliminar/recibo', [CreditoPrendarioController::class, 'generarReciboPreliminar']);
        Route::post('/creditos-prendarios/preliminar/contrato', [CreditoPrendarioController::class, 'generarContratoPreliminar']);
        Route::post('/creditos-prendarios/preliminar/plan-pagos', [CreditoPrendarioController::class, 'generarPlanPagosPreliminar']);
        Route::post('/creditos-prendarios/simular-plan', [CreditoPrendarioController::class, 'simularPlan']);
        Route::post('/creditos-prendarios/{id}/movimientos/{movimiento_id}/anular', [CreditoPrendarioController::class, 'anularMovimiento']);
        Route::delete('/creditos-prendarios/{id}', [CreditoPrendarioController::class, 'destroy']);

        // Pagos y Ledger
        Route::get('/creditos-prendarios/{id}/calculo-pago', [\App\Http\Controllers\PagoController::class, 'calcularPago']);
        Route::post('/creditos-prendarios/{id}/pagos', [\App\Http\Controllers\PagoController::class, 'ejecutarPago']);
        Route::post('/creditos-prendarios/{id}/reactivar', [CreditoPrendarioController::class, 'reactivar']);

        // Prendas
        Route::get('/prendas/reporte', [PrendaController::class, 'reporte']);
        Route::get('/prendas', [PrendaController::class, 'index']);
        Route::get('/prendas/estadisticas', [PrendaController::class, 'getEstadisticas']);
        Route::get('/prendas/en-venta', [PrendaController::class, 'getEnVenta']);
        Route::get('/prendas/{id}', [PrendaController::class, 'show']);
        Route::post('/prendas', [PrendaController::class, 'store']);
        Route::put('/prendas/{id}', [PrendaController::class, 'update']);
        Route::patch('/prendas/{id}', [PrendaController::class, 'update']); // Permitir PATCH también
        Route::delete('/prendas/{id}', [PrendaController::class, 'destroy']);
        Route::post('/prendas/{id}/foto', [PrendaController::class, 'uploadPhoto']);
        Route::post('/prendas/{id}/recuperar', [PrendaController::class, 'marcarRecuperada']);
        Route::post('/prendas/{id}/poner-venta', [PrendaController::class, 'marcarEnVenta']);
        Route::post('/prendas/{id}/vender', [PrendaController::class, 'marcarVendida']);
        Route::post('/prendas/{id}/reservar-temporal', [PrendaController::class, 'reservarTemporal']); // NUEVO

        // Imágenes de Prendas (CRUD normalizado)
        Route::get('/prendas/{prendaId}/imagenes', [\App\Http\Controllers\PrendaImagenController::class, 'index']);
        Route::post('/prendas/{prendaId}/imagenes', [\App\Http\Controllers\PrendaImagenController::class, 'store']);
        Route::get('/prendas/{prendaId}/imagenes/{imagenId}', [\App\Http\Controllers\PrendaImagenController::class, 'show']);
        Route::put('/prendas/{prendaId}/imagenes/{imagenId}', [\App\Http\Controllers\PrendaImagenController::class, 'update']);
        Route::delete('/prendas/{prendaId}/imagenes/{imagenId}', [\App\Http\Controllers\PrendaImagenController::class, 'destroy']);
        Route::post('/prendas/{prendaId}/imagenes/reordenar', [\App\Http\Controllers\PrendaImagenController::class, 'reordenar']);
        Route::post('/prendas/{prendaId}/imagenes/{imagenId}/principal', [\App\Http\Controllers\PrendaImagenController::class, 'establecerPrincipal']);
        Route::get('/imagenes/duplicados', [\App\Http\Controllers\PrendaImagenController::class, 'buscarDuplicados']);

        // Ventas
        Route::get('/ventas/debug', [\App\Http\Controllers\VentaController::class, 'debug']); // DEBUG TEMPORAL
        Route::get('/ventas/pendientes-pago', [\App\Http\Controllers\VentaController::class, 'ventasPendientesPago']); // Ventas con saldo pendiente
        Route::get('/ventas', [\App\Http\Controllers\VentaController::class, 'index']);
        Route::get('/ventas/prendas-disponibles', [\App\Http\Controllers\VentaController::class, 'prendasEnVenta']);
        Route::get('/ventas/estadisticas', [\App\Http\Controllers\VentaController::class, 'estadisticas']);
        Route::get('/ventas/{id}', [\App\Http\Controllers\VentaController::class, 'show']);
        Route::get('/ventas/{id}/resumen-pagos', [\App\Http\Controllers\VentaController::class, 'resumenPagos']); // Resumen de pagos
        Route::post('/ventas', [\App\Http\Controllers\VentaController::class, 'store']); // NUEVO: crear venta multi-prenda
        Route::put('/ventas/{id}', [\App\Http\Controllers\VentaController::class, 'update']);
        Route::patch('/ventas/{id}', [\App\Http\Controllers\VentaController::class, 'update']);
        Route::post('/ventas/prendas/{id}/marcar-venta', [\App\Http\Controllers\VentaController::class, 'marcarParaVenta']);
        Route::post('/ventas/prendas/{id}/procesar', [\App\Http\Controllers\VentaController::class, 'procesarVenta']); // DEPRECADO
        Route::post('/ventas/{id}/abonos', [\App\Http\Controllers\VentaController::class, 'registrarAbono']); // Registrar abono/pago
        Route::post('/ventas/{id}/cancelar', [\App\Http\Controllers\VentaController::class, 'cancelar']);
        Route::post('/ventas/{id}/pagos', [\App\Http\Controllers\VentaController::class, 'registrarPago']); // NUEVO: pagos adicionales
        Route::post('/ventas/{id}/certificar', [\App\Http\Controllers\VentaController::class, 'certificar']);
        Route::delete('/ventas/{id}', [\App\Http\Controllers\VentaController::class, 'destroy']);

        // Compras
        Route::post('/compras', [CompraController::class, 'store']);

        // Caja
        Route::get('/cajas', [CajaController::class, 'index']);
        Route::get('/cajas/check-estado', [CajaController::class, 'checkEstado']);
        Route::post('/cajas/abrir', [CajaController::class, 'abrir']);
        Route::post('/cajas/{id}/cerrar', [CajaController::class, 'cerrar']);
        Route::get('/cajas/{id}/movimientos', [CajaController::class, 'getMovimientos']);
        Route::post('/cajas/movimientos', [CajaController::class, 'registrarMovimiento']);
        Route::put('/cajas/{id}', [CajaController::class, 'update']);
        Route::patch('/cajas/{id}', [CajaController::class, 'update']);

        // Reportes de Caja
        Route::get('/reportes/caja/movimientos', [ReporteCajaController::class, 'reporteMovimientos']);
        Route::get('/reportes/caja/consolidado', [ReporteCajaController::class, 'consolidado']);
        Route::post('/reportes/caja/pdf', [ReporteCajaController::class, 'reportePDF']);
        Route::post('/reportes/caja/consolidado-pdf', [ReporteCajaController::class, 'consolidadoPDF']);
        Route::get('/reportes/caja/excel', [ReporteCajaController::class, 'exportarExcel']);

        // Denominaciones y Monedas
        Route::get('/denominaciones', [DenominacionController::class, 'index']);
        Route::get('/denominaciones/moneda-base', [DenominacionController::class, 'getMonedaBase']);
        Route::get('/denominaciones/moneda/{monedaId}', [DenominacionController::class, 'getDenominacionesByMoneda']);
        Route::post('/denominaciones/monedas', [DenominacionController::class, 'createMoneda']);
        Route::post('/denominaciones', [DenominacionController::class, 'createDenominacion']);
        Route::put('/denominaciones/{id}', [DenominacionController::class, 'update']);
        Route::patch('/denominaciones/{id}', [DenominacionController::class, 'update']);
        Route::delete('/denominaciones/{id}', [DenominacionController::class, 'destroy']);
        Route::post('/denominaciones/{id}/toggle', [DenominacionController::class, 'toggleDenominacion']);

        // Recibos
        Route::put('/recibos/{id}', [ReciboController::class, 'update']);
        Route::patch('/recibos/{id}', [ReciboController::class, 'update']);
        Route::delete('/recibos/{id}', [ReciboController::class, 'destroy']);
    });

    // Rutas de Reportes de Ventas (Fuera de Sanctum para descargas directas)
    Route::get('/ventas/{id}/pdf', [\App\Http\Controllers\VentaController::class, 'generarPDF']);
    Route::get('/ventas/exportar/excel', [\App\Http\Controllers\VentaController::class, 'exportarExcel']);

        // Rutas del Chatbot IA (requiere autenticación)
        Route::prefix('chatbot')->group(function () {
            Route::post('/consultar', [\App\Http\Controllers\ChatbotController::class, 'consultar']);
            Route::get('/estadisticas', [\App\Http\Controllers\ChatbotController::class, 'estadisticas']);
        });
});

Route::prefix('ecommerce')->group(function () {

    Route::get('/categorias', [\App\Http\Controllers\CategoriaProductoController::class, 'index']);
    Route::get('/clientes', [\App\Http\Controllers\ClienteController::class, 'index']);
    Route::get('/prendas', [\App\Http\Controllers\PrendaController::class, 'index']);
    Route::get('/prendas/{id}', [\App\Http\Controllers\PrendaController::class, 'show']);
    Route::post('/prendas', [\App\Http\Controllers\PrendaController::class, 'store']);
    Route::put('/prendas/{id}', [\App\Http\Controllers\PrendaController::class, 'update']);
    Route::patch('/prendas/{id}', [\App\Http\Controllers\PrendaController::class, 'update']);
    Route::delete('/prendas/{id}', [\App\Http\Controllers\PrendaController::class, 'destroy']);

    Route::get('/ping', function () {
        return response()->json([
            'status' => 'success',
            'message' => 'Pong'
        ]);
    });

});


