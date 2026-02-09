#!/usr/bin/env pwsh
# Script para configurar las sucursales en el sistema

Write-Host "================================================" -ForegroundColor Cyan
Write-Host "  CONFIGURACIÓN DE SUCURSALES - Empeños API" -ForegroundColor Cyan
Write-Host "================================================" -ForegroundColor Cyan
Write-Host ""

# Verificar que estamos en el directorio correcto
$currentPath = Get-Location
if (-not (Test-Path ".\artisan")) {
    Write-Host "❌ Error: Este script debe ejecutarse desde la carpeta empenios-api" -ForegroundColor Red
    Write-Host "Navegando a empenios-api..." -ForegroundColor Yellow
    Set-Location ".\empenios-api"
    if (-not (Test-Path ".\artisan")) {
        Write-Host "❌ No se pudo encontrar la carpeta empenios-api" -ForegroundColor Red
        exit 1
    }
}

Write-Host "✅ Directorio correcto detectado" -ForegroundColor Green
Write-Host ""

# Ejecutar el seeder de sucursales
Write-Host "📊 Ejecutando SucursalSeeder..." -ForegroundColor Yellow
php artisan db:seed --class=SucursalSeeder

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ SucursalSeeder ejecutado exitosamente" -ForegroundColor Green
} else {
    Write-Host "⚠️ Hubo un problema al ejecutar el seeder" -ForegroundColor Yellow
}

Write-Host ""

# Verificar las sucursales creadas
Write-Host "🔍 Verificando sucursales activas..." -ForegroundColor Yellow
Write-Host ""

# Crear archivo temporal PHP para consultar
$phpScript = @'
<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$sucursales = App\Models\Sucursal::where('activa', true)->get(['id', 'codigo', 'nombre']);

echo "┌────┬──────────┬─────────────────────────────┐\n";
echo "│ ID │ CÓDIGO   │ NOMBRE                      │\n";
echo "├────┼──────────┼─────────────────────────────┤\n";

foreach ($sucursales as $suc) {
    printf("│ %-2s │ %-8s │ %-27s │\n", $suc->id, $suc->codigo, $suc->nombre);
}

echo "└────┴──────────┴─────────────────────────────┘\n";
echo "\nTotal: " . $sucursales->count() . " sucursales activas\n";
'@

$phpScript | Out-File -FilePath ".\temp_check_sucursales.php" -Encoding UTF8
php .\temp_check_sucursales.php
Remove-Item ".\temp_check_sucursales.php"

Write-Host ""
Write-Host "================================================" -ForegroundColor Cyan
Write-Host "  VERIFICACIÓN DE SUPERADMIN" -ForegroundColor Cyan
Write-Host "================================================" -ForegroundColor Cyan
Write-Host ""

# Verificar SuperAdmin
$phpScriptAdmin = @'
<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$admin = App\Models\User::where('rol', 'superadmin')->first();

if ($admin) {
    echo "✅ Usuario SuperAdmin encontrado:\n";
    echo "   Email: {$admin->email}\n";
    echo "   Nombre: {$admin->name}\n";
    echo "   Rol: {$admin->rol}\n";
    echo "   Activo: " . ($admin->activo ? 'Sí' : 'No') . "\n";
    echo "   Sucursal: " . ($admin->sucursal_id ? "ID {$admin->sucursal_id}" : "Sin asignar (ve todas)") . "\n";
} else {
    echo "⚠️ No se encontró usuario SuperAdmin\n";
    echo "Ejecuta: php artisan db:seed --class=CreateSuperAdminSeeder\n";
}
'@

$phpScriptAdmin | Out-File -FilePath ".\temp_check_admin.php" -Encoding UTF8
php .\temp_check_admin.php
Remove-Item ".\temp_check_admin.php"

Write-Host ""
Write-Host "================================================" -ForegroundColor Cyan
Write-Host "  CONFIGURACIÓN COMPLETADA" -ForegroundColor Cyan
Write-Host "================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "📝 Próximos pasos:" -ForegroundColor Yellow
Write-Host "   1. Abre el frontend (sistema-de-gestin-de)" -ForegroundColor White
Write-Host "   2. Haz login con: superadmin@empenios.com" -ForegroundColor White
Write-Host "   3. Contraseña: SuperAdmin2024!" -ForegroundColor White
Write-Host "   4. Ve a Usuarios y Roles > Nuevo Usuario" -ForegroundColor White
Write-Host "   5. Deberías ver el selector de Sucursal" -ForegroundColor White
Write-Host ""
Write-Host "🐛 Si no ves el selector:" -ForegroundColor Yellow
Write-Host "   - Abre la consola del navegador (F12)" -ForegroundColor White
Write-Host "   - Busca los logs con 🔍 DEBUG" -ForegroundColor White
Write-Host "   - Verifica que isSuperAdmin = true" -ForegroundColor White
Write-Host ""
Write-Host "✨ ¡Listo!" -ForegroundColor Green
Write-Host ""
