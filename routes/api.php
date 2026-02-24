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
use App\Http\Controllers\Api\ReporteComprasController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\ContabilidadController;
use App\Http\Controllers\ParametrizacionCuentasController;
use App\Http\Controllers\NomenclaturaController;
use App\Http\Controllers\DiarioContableController;
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
    /*Rutas de salud API */
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

Route::prefix('v1')->group(function () {
    // 🔒 Rutas públicas de autenticación con rate limiting estricto
    Route::middleware('throttle:5,1')->group(function () {
        Route::post('/auth/login', [AuthController::class, 'login']);
    });


    // Rutas protegidas
    Route::middleware('auth:sanctum')->group(function () {
        // Dashboard
        Route::get('/dashboard', [DashboardController::class, 'index']);
        Route::get('/dashboard/graficas', [DashboardController::class, 'graficas']);
        Route::get('/dashboard/alertas', [DashboardController::class, 'alertas']);

        // Autenticación (sin scope de sucursal)
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        Route::post('/auth/logout-all', [AuthController::class, 'logoutAll']);
        Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
        Route::post('/auth/refresh-token', [AuthController::class, 'refreshToken']);
        Route::post('/auth/cambiar-sucursal', [AuthController::class, 'cambiarSucursal']);

        // Auditoría (solo superadmin - sin scope de sucursal)
        Route::prefix('auditoria')->group(function () {
            Route::get('/', [AuditoriaController::class, 'index']);
            Route::get('/estadisticas', [AuditoriaController::class, 'estadisticas']);
            Route::get('/modulos', [AuditoriaController::class, 'modulos']);
            Route::get('/acciones', [AuditoriaController::class, 'acciones']);
            Route::post('/test', [AuditoriaController::class, 'test']);
            Route::get('/{id}', [AuditoriaController::class, 'show']);
        });

        // Errores de Sistema (solo superadmin)
        Route::prefix('system-errors')->group(function () {
            Route::get('/', [\App\Http\Controllers\SystemErrorController::class, 'index']);
            Route::get('/{id}', [\App\Http\Controllers\SystemErrorController::class, 'show']);
            Route::post('/clear', [\App\Http\Controllers\SystemErrorController::class, 'clear']);
        });

        // Sucursales (sin scope - el superadmin gestiona todas las sucursales)
        Route::get('/sucursales', [SucursalController::class, 'index']);
        Route::get('/sucursales/activas', [SucursalController::class, 'activas']);
        Route::get('/sucursales/{id}', [SucursalController::class, 'show']);
        Route::post('/sucursales', [SucursalController::class, 'store']);
        Route::put('/sucursales/{id}', [SucursalController::class, 'update']);
        Route::delete('/sucursales/{id}', [SucursalController::class, 'destroy']);
        Route::post('/sucursales/{id}/toggle-activa', [SucursalController::class, 'toggleActiva']);

        // Permisos (sin scope de sucursal)
        Route::get('/permisos', [PermissionController::class, 'index']);
        Route::get('/permisos/rol/{rol}', [PermissionController::class, 'getRolePermissions']);
        Route::get('/usuarios/{id}/permisos', [PermissionController::class, 'getUserPermissions']);
        Route::put('/usuarios/{id}/permisos', [PermissionController::class, 'updateUserPermissions']);
        Route::post('/usuarios/{id}/permisos/reset', [PermissionController::class, 'resetToDefault']);

        // 🏢 RUTAS CON SCOPE DE SUCURSAL (todas las operaciones CRUD normales)
        Route::middleware('sucursal.scope')->group(function () {
            // Usuarios
            Route::get('/usuarios', [UserController::class, 'index']);
            Route::get('/usuarios/{id}', [UserController::class, 'show']);
            Route::post('/usuarios', [UserController::class, 'store']);
            Route::put('/usuarios/{id}', [UserController::class, 'update']);
            Route::delete('/usuarios/{id}', [UserController::class, 'destroy']);
            Route::post('/usuarios/{id}/toggle-activo', [UserController::class, 'toggleActivo']);
            Route::post('/usuarios/{id}/cambiar-password', [UserController::class, 'changePassword']);
            Route::post('/usuarios/{id}/foto', [UserController::class, 'uploadPhoto']); // Nueva ruta para foto de perfil


            // Clientes
            Route::get('/clientes/activos', [ClienteController::class, 'activos']);
            Route::get('/clientes', [ClienteController::class, 'index']);
            Route::get('/clientes/{id}', [ClienteController::class, 'show']);
            Route::get('/clientes/{id}/ficha', [ClienteController::class, 'ficha']);
            Route::post('/clientes/{id}/ficha/pdf', [ClienteController::class, 'descargarFichaCliente']);
            Route::get('/clientes/{id}/creditos-prendarios', [ClienteController::class, 'creditosPrendarios']);
            Route::post('/clientes', [ClienteController::class, 'store']);
            Route::put('/clientes/{id}', [ClienteController::class, 'update']);
            Route::delete('/clientes/{id}', [ClienteController::class, 'destroy']);
            Route::post('/clientes/{id}/foto', [ClienteController::class, 'uploadPhoto']);

            // Categorías de Productos
            Route::get('/categorias-productos', [CategoriaProductoController::class, 'index']);
            Route::get('/categorias-productos/activas', [CategoriaProductoController::class, 'getActivas']);
            Route::get('/categorias-productos/{id}', [CategoriaProductoController::class, 'show']);
            Route::post('/categorias-productos', [CategoriaProductoController::class, 'store']);
            Route::put('/categorias-productos/{id}', [CategoriaProductoController::class, 'update']);
            Route::delete('/categorias-productos/{id}', [CategoriaProductoController::class, 'destroy']);
            Route::post('/categorias-productos/{id}/toggle-activa', [CategoriaProductoController::class, 'toggleActiva']);

            // GeoNames (Guatemala) - sin scope porque son datos públicos de referencia
        });

        // GeoNames (sin scope - datos de referencia)
        Route::get('/geonames/departamentos', [GeoNamesController::class, 'obtenerDepartamentos']);
        Route::get('/geonames/municipios/{geonameId}', [GeoNamesController::class, 'obtenerMunicipios']);
        Route::get('/geonames/guatemala-completo', [GeoNamesController::class, 'obtenerGuatemalaCompleto']);

        // CONTINUACIÓN DE RUTAS CON SCOPE
        Route::middleware('sucursal.scope')->group(function () {
            // Códigos Pre-reservados (para wizard de créditos)
            Route::get('/codigos-prereservados/token', [\App\Http\Controllers\CodigoPrereservadoController::class, 'generarToken']);
            Route::post('/codigos-prereservados/reservar', [\App\Http\Controllers\CodigoPrereservadoController::class, 'reservar']);
            Route::get('/codigos-prereservados/obtener', [\App\Http\Controllers\CodigoPrereservadoController::class, 'obtener']);
            Route::post('/codigos-prereservados/liberar', [\App\Http\Controllers\CodigoPrereservadoController::class, 'liberar']);

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

            // ========== GASTOS (Módulo de cargos para créditos) ==========
            // Catálogo de gastos
            Route::get('/gastos', [\App\Http\Controllers\GastoController::class, 'index']);
            Route::post('/gastos', [\App\Http\Controllers\GastoController::class, 'store']);
            Route::get('/gastos/{id}', [\App\Http\Controllers\GastoController::class, 'show']);
            Route::put('/gastos/{id}', [\App\Http\Controllers\GastoController::class, 'update']);
            Route::delete('/gastos/{id}', [\App\Http\Controllers\GastoController::class, 'destroy']);
            Route::post('/gastos/{id}/restaurar', [\App\Http\Controllers\GastoController::class, 'restore']);
            Route::post('/gastos/{id}/calcular', [\App\Http\Controllers\GastoController::class, 'calcular']);

            // Gastos de crédito (asociación)
            Route::get('/creditos-prendarios/{cred_id}/gastos', [\App\Http\Controllers\CreditoGastosController::class, 'index']);
            Route::post('/creditos-prendarios/{cred_id}/gastos', [\App\Http\Controllers\CreditoGastosController::class, 'sync']);
            Route::delete('/creditos-prendarios/{cred_id}/gastos/{gas_id}', [\App\Http\Controllers\CreditoGastosController::class, 'destroy']);
            Route::post('/creditos-prendarios/{cred_id}/gastos/recalcular', [\App\Http\Controllers\CreditoGastosController::class, 'recalcular']);
            Route::post('/creditos/preview-gastos', [\App\Http\Controllers\CreditoGastosController::class, 'preview']);
            // ========== FIN GASTOS ==========

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
            Route::get('/ventas/{id}/plan-pagos-pdf', [\App\Http\Controllers\VentaController::class, 'generarPDFPlanPagos']); // PDF Plan de Pagos
            Route::get('/ventas/{id}/recibo-pos', [\App\Http\Controllers\VentaController::class, 'generarReciboPOS']); // Recibo POS 80mm
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

            // Planes de Pago de Ventas (Ventas a Crédito)
            Route::post('/ventas/{id}/generar-plan', [\App\Http\Controllers\VentaPlanPagoController::class, 'generarPlan']); // Generar plan de pagos
            Route::get('/ventas/apartados', [\App\Http\Controllers\VentaPlanPagoController::class, 'listarApartados']); // Listar apartados
            Route::get('/ventas/planes-pago', [\App\Http\Controllers\VentaPlanPagoController::class, 'listarPlanesPago']); // Listar planes activos
            Route::get('/ventas/{id}/plan-pago', [\App\Http\Controllers\VentaPlanPagoController::class, 'obtenerDetallePlan']); // Detalle del plan
            Route::post('/ventas/cuotas/{cuotaId}/pagar', [\App\Http\Controllers\VentaPlanPagoController::class, 'pagarCuota']); // Pagar cuota
            Route::get('/ventas/planes-pago/resumen', [\App\Http\Controllers\VentaPlanPagoController::class, 'resumenGeneral']); // Estadísticas

            // Compras Directas
            Route::get('/compras', [CompraController::class, 'index']);
            Route::get('/compras/stats/general', [CompraController::class, 'stats']);
            Route::post('/compras', [CompraController::class, 'store']);
            Route::get('/compras/{id}', [CompraController::class, 'show']);
            Route::put('/compras/{id}', [CompraController::class, 'update']);
            Route::delete('/compras/{id}', [CompraController::class, 'destroy']);
            Route::post('/compras/{id}/cancelar', [CompraController::class, 'cancel']);
            Route::get('/compras/{id}/recibo-pdf', [CompraController::class, 'generarReciboPDF']);

            // Reportes de Compras
            Route::get('/reportes/compras/pdf', [ReporteComprasController::class, 'generarPDF']);
            Route::get('/reportes/compras/excel', [ReporteComprasController::class, 'generarExcel']);
            Route::get('/reportes/compras/vista-previa', [ReporteComprasController::class, 'vistaPrevia']);

            // Cotizaciones
            Route::get('/cotizaciones', [\App\Http\Controllers\CotizacionController::class, 'index']);
            Route::post('/cotizaciones', [\App\Http\Controllers\CotizacionController::class, 'store']);
            Route::get('/cotizaciones/{id}', [\App\Http\Controllers\CotizacionController::class, 'show']);
            Route::put('/cotizaciones/{id}', [\App\Http\Controllers\CotizacionController::class, 'update']);
            Route::delete('/cotizaciones/{id}', [\App\Http\Controllers\CotizacionController::class, 'destroy']);
            Route::get('/cotizaciones/{id}/pdf', [\App\Http\Controllers\CotizacionController::class, 'generarPDF']);
            Route::post('/cotizaciones/{id}/convertir', [\App\Http\Controllers\CotizacionController::class, 'convertirAVenta']);

            // Caja
            Route::get('/cajas', [CajaController::class, 'index']);
            Route::get('/cajas/check-estado', [CajaController::class, 'checkEstado']);
            Route::post('/cajas/abrir', [CajaController::class, 'abrir']);
            Route::post('/cajas/{id}/cerrar', [CajaController::class, 'cerrar']);
            // Nuevo endpoint REST para cierre de caja con opción de envío a bóveda
            Route::post('/cash-registers/{id}/close', [CajaController::class, 'closeWithVault']);
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

            // Recibos (actualización y eliminación)
            Route::put('/recibos/{id}', [ReciboController::class, 'update']);
            Route::patch('/recibos/{id}', [ReciboController::class, 'update']);
            Route::delete('/recibos/{id}', [ReciboController::class, 'destroy']);
        }); // FIN DEL GRUPO DE RUTAS CON SCOPE DE SUCURSAL
    });

    // 🔒 Rutas de Descargas con Rate Limiting y Autenticación
    Route::middleware(['auth:sanctum', 'throttle.downloads'])->group(function () {
        // Rutas específicas de exportar ANTES de las rutas con parámetros {id}
        Route::get('/ventas/exportar/excel', [\App\Http\Controllers\VentaController::class, 'exportarExcel']);
        Route::get('/ventas/exportar/pdf', [\App\Http\Controllers\VentaController::class, 'exportarPDF']);
        Route::get('/ventas/{id}/pdf', [\App\Http\Controllers\VentaController::class, 'generarPDF']);
        Route::get('/creditos-prendarios/{id}/plan-pagos/pdf', [CreditoPrendarioController::class, 'descargarPlanPagos']);
        Route::get('/creditos-prendarios/{id}/contrato/pdf', [CreditoPrendarioController::class, 'descargarContrato']);
        Route::get('/creditos-prendarios/{id}/recibo/pdf', [CreditoPrendarioController::class, 'descargarRecibo']);
        Route::get('/creditos-prendarios/{id}/historial-pagos/pdf', [CreditoPrendarioController::class, 'descargarHistorialPagos']);
        Route::get('/recibos/{id}/pdf', [ReciboController::class, 'generarPDF']);
        Route::get('/reportes/caja/excel', [ReporteCajaController::class, 'exportarExcel']);
        Route::get('/bovedas/exportar', [BovedaController::class, 'exportarBovedas']);
        Route::get('/bovedas/consolidacion/exportar', [BovedaController::class, 'exportarConsolidacion']);
        Route::get('/bovedas/consolidacion/pdf', [BovedaController::class, 'exportarConsolidacionPDF']);
        Route::get('/bovedas/{id}/exportar-movimientos', [BovedaController::class, 'exportarMovimientos']);
        Route::get('/bovedas/{id}/exportar-movimientos-pdf', [BovedaController::class, 'exportarMovimientosPDF']);
    });

        // Rutas del Chatbot IA (requiere autenticación con rate limiting)
        Route::middleware('throttle:30,1')->prefix('chatbot')->group(function () {
            Route::post('/consultar', [\App\Http\Controllers\ChatbotController::class, 'consultar']);
            Route::get('/estadisticas', [\App\Http\Controllers\ChatbotController::class, 'estadisticas']);
        });

        // ==================== CONTABILIDAD ====================
        Route::prefix('contabilidad')->group(function () {
            // Dashboard contable
            Route::get('/dashboard', [ContabilidadController::class, 'dashboard']);

            // Plan de cuentas (Nomenclatura)
            Route::get('/nomenclatura', [ContabilidadController::class, 'nomenclatura']);
            Route::get('/nomenclatura/arbol', [ContabilidadController::class, 'nomenclaturaArbol']);
            Route::post('/nomenclatura', [ContabilidadController::class, 'crearCuenta']);
            Route::put('/nomenclatura/{id}', [ContabilidadController::class, 'actualizarCuenta']);

            // Tipos de póliza
            Route::get('/tipos-poliza', [ContabilidadController::class, 'tiposPoliza']);

            // Libro Diario (Asientos)
            Route::get('/diario', [ContabilidadController::class, 'diario']);
            Route::get('/diario/{id}', [ContabilidadController::class, 'verAsiento']);

            // Balance de comprobación
            Route::get('/balance-comprobacion', [ContabilidadController::class, 'balanceComprobacion']);
            Route::get('/balance-comprobacion/pdf', [ContabilidadController::class, 'balanceComprobacionPdf']);

            // Libro Mayor
            Route::get('/libro-mayor', [ContabilidadController::class, 'libroMayor']);
            Route::get('/libro-mayor/pdf', [ContabilidadController::class, 'libroMayorPdf']);

            // Reportes PDF
            Route::get('/libro-diario/pdf', [ContabilidadController::class, 'libroDiarioPdf']);

            // ========== PARAMETRIZACIÓN DE CUENTAS ==========
            Route::prefix('parametrizacion-cuentas')->group(function () {
                Route::get('/', [ParametrizacionCuentasController::class, 'index']);
                Route::get('/operacion/{tipo}', [ParametrizacionCuentasController::class, 'getPorOperacion']);
                Route::get('/tipos-operacion', [ParametrizacionCuentasController::class, 'getTiposOperacion']);
                Route::post('/', [ParametrizacionCuentasController::class, 'store']);
                Route::put('/{id}', [ParametrizacionCuentasController::class, 'update']);
                Route::delete('/{id}', [ParametrizacionCuentasController::class, 'destroy']);
                Route::post('/{id}/toggle', [ParametrizacionCuentasController::class, 'toggle']);
                Route::post('/batch', [ParametrizacionCuentasController::class, 'updateBatch']);
            });

            // ========== ASIENTOS CONTABLES ==========
            Route::prefix('asientos')->group(function () {
                Route::get('/', [DiarioContableController::class, 'index']);
                Route::get('/{id}', [DiarioContableController::class, 'show']);
                Route::post('/{id}/anular', [DiarioContableController::class, 'anular']);
                Route::post('/manual', [DiarioContableController::class, 'registrarManual']);
                Route::get('/estadisticas', [DiarioContableController::class, 'estadisticas']);
            });
        });

        // ========== MIGRACIONES (SOLO SUPERADMIN) ==========
        Route::middleware('auth:sanctum')->prefix('migraciones')->group(function () {
             Route::get('/', [\App\Http\Controllers\MigracionController::class, 'index']);
             Route::get('/plantilla/{modelo}', [\App\Http\Controllers\MigracionController::class, 'downloadTemplate']);
             Route::post('/upload', [\App\Http\Controllers\MigracionController::class, 'upload']);
             Route::post('/execute', [\App\Http\Controllers\MigracionController::class, 'execute']);
        });
});

// 🔒 Rutas E-commerce con Rate Limiting (público pero limitado)
Route::middleware('throttle:60,1')->prefix('ecommerce')->group(function () {

    Route::get('/categorias', [\App\Http\Controllers\CategoriaProductoController::class, 'index']);
    Route::get('/clientes', [\App\Http\Controllers\ClienteController::class, 'index']);
    Route::get('/prendas', [\App\Http\Controllers\PrendaController::class, 'index']);
    Route::get('/prendas/{id}', [\App\Http\Controllers\PrendaController::class, 'show']);

    // 🔒 Operaciones de escritura requieren rate limiting más estricto
    Route::middleware('throttle:10,1')->group(function () {
        Route::post('/prendas', [\App\Http\Controllers\PrendaController::class, 'store']);
        Route::put('/prendas/{id}', [\App\Http\Controllers\PrendaController::class, 'update']);
        Route::patch('/prendas/{id}', [\App\Http\Controllers\PrendaController::class, 'update']);
        Route::delete('/prendas/{id}', [\App\Http\Controllers\PrendaController::class, 'destroy']);
    });

    Route::get('/ping', function () {
        return response()->json([
            'status' => 'success',
            'message' => 'Pong'
        ]);
    });

});


