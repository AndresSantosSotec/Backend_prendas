
// ========================================
// RUTAS DE COMPRAS DIRECTAS
// ========================================

// Agregar estas rutas al archivo routes/api.php dentro del grupo de autenticación

Route::middleware(['auth:sanctum'])->prefix('compras')->group(function () {

    // Listar compras con filtros
    Route::get('/', [CompraController::class, 'index']);

    // Obtener detalle de una compra
    Route::get('/{id}', [CompraController::class, 'show']);

    // Registrar nueva compra
    Route::post('/', [CompraController::class, 'store']);

    // Cancelar compra
    Route::post('/{id}/cancelar', [CompraController::class, 'cancel']);

    // Estadísticas
    Route::get('/stats/general', [CompraController::class, 'stats']);
});
