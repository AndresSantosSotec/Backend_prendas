<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClienteController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\SucursalController;
use App\Http\Controllers\GeoNamesController;

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
        
        // GeoNames (Guatemala)
        Route::get('/geonames/departamentos', [GeoNamesController::class, 'obtenerDepartamentos']);
        Route::get('/geonames/municipios/{geonameId}', [GeoNamesController::class, 'obtenerMunicipios']);
        Route::get('/geonames/guatemala-completo', [GeoNamesController::class, 'obtenerGuatemalaCompleto']);
    });
});

