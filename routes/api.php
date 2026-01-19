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
use App\Http\Controllers\PrendaController;

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

Route::prefix('v1')->group(function () {
    // Rutas públicas de autenticación
    Route::post('/auth/login', [AuthController::class, 'login']);

    // Rutas protegidas
    Route::middleware('auth:sanctum')->group(function () {
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

        // Créditos Prendarios
        Route::get('/creditos-prendarios', [CreditoPrendarioController::class, 'index']);
        Route::get('/creditos-prendarios/estadisticas', [CreditoPrendarioController::class, 'getEstadisticas']);
        Route::get('/creditos-prendarios/{id}', [CreditoPrendarioController::class, 'show']);
        Route::get('/creditos-prendarios/{id}/plan-pagos', [CreditoPrendarioController::class, 'getPlanPagos']);
        Route::get('/creditos-prendarios/{id}/movimientos', [CreditoPrendarioController::class, 'getMovimientos']);
        Route::get('/creditos-prendarios/{id}/saldo', [CreditoPrendarioController::class, 'getSaldo']);
        Route::post('/creditos-prendarios', [CreditoPrendarioController::class, 'store']);
        Route::post('/creditos-prendarios/{id}/desembolsar', [CreditoPrendarioController::class, 'desembolsar']);
        Route::post('/creditos-prendarios/{id}/pagar', [CreditoPrendarioController::class, 'pagar']);
        Route::post('/creditos-prendarios/{id}/aprobar', [CreditoPrendarioController::class, 'aprobar']);
        Route::post('/creditos-prendarios/{id}/rechazar', [CreditoPrendarioController::class, 'rechazar']);
        Route::get('/creditos-prendarios/{id}/transiciones', [CreditoPrendarioController::class, 'getTransiciones']);
        Route::get('/creditos-prendarios/{id}/auditoria', [CreditoPrendarioController::class, 'getAuditoria']);
        Route::get('/creditos-prendarios/{id}/plan-pagos/pdf', [CreditoPrendarioController::class, 'descargarPlanPagos']);
        Route::get('/creditos-prendarios/{id}/contrato/pdf', [CreditoPrendarioController::class, 'descargarContrato']);
        Route::post('/creditos-prendarios/{id}/movimientos/{movimiento_id}/anular', [CreditoPrendarioController::class, 'anularMovimiento']);
        Route::delete('/creditos-prendarios/{id}', [CreditoPrendarioController::class, 'destroy']);
        
        // Pagos y Ledger
        Route::get('/creditos-prendarios/{id}/calculo-pago', [\App\Http\Controllers\PagoController::class, 'calcularPago']);
        Route::post('/creditos-prendarios/{id}/pagos', [\App\Http\Controllers\PagoController::class, 'ejecutarPago']);
        Route::post('/creditos-prendarios/{id}/reactivar', [CreditoPrendarioController::class, 'reactivar']);

        // Prendas
        Route::get('/prendas', [PrendaController::class, 'index']);
        Route::get('/prendas/estadisticas', [PrendaController::class, 'getEstadisticas']);
        Route::get('/prendas/en-venta', [PrendaController::class, 'getEnVenta']);
        Route::get('/prendas/{id}', [PrendaController::class, 'show']);
        Route::post('/prendas', [PrendaController::class, 'store']);
        Route::put('/prendas/{id}', [PrendaController::class, 'update']);
        Route::delete('/prendas/{id}', [PrendaController::class, 'destroy']);
        Route::post('/prendas/{id}/foto', [PrendaController::class, 'uploadPhoto']);
        Route::post('/prendas/{id}/recuperar', [PrendaController::class, 'marcarRecuperada']);
        Route::post('/prendas/{id}/poner-venta', [PrendaController::class, 'marcarEnVenta']);
        Route::post('/prendas/{id}/vender', [PrendaController::class, 'marcarVendida']);
    });
});

