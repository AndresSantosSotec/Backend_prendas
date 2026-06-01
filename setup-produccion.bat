@echo off
REM =================================================================
REM   SCRIPT DE CONFIGURACIÓN INICIAL DE PRODUCCIÓN
REM   Sistema DigiPrenda - Sucursal Esquipulas
REM =================================================================
REM
REM Este script hace un RESET COMPLETO de la base de datos y
REM configura el sistema para producción con:
REM   - SuperAdmin (andres@empenios.com)
REM   - Sucursal Esquipulas
REM   - 6 usuarios de producción
REM   - Todas las configuraciones del sistema
REM
REM ⚠️  ADVERTENCIA: Este comando ELIMINARÁ TODOS LOS DATOS
REM    Solo ejecutar en configuración inicial o reset completo.
REM =================================================================

echo.
echo ╔═══════════════════════════════════════════════════════╗
echo ║    CONFIGURACION INICIAL DE PRODUCCION               ║
echo ║              Sistema DigiPrenda                      ║
echo ╚═══════════════════════════════════════════════════════╝
echo.

REM Confirmación de seguridad
echo ⚠️  ADVERTENCIA: Este proceso ELIMINARA TODOS LOS DATOS existentes.
echo.
set /p confirmacion="¿Estas seguro de continuar? (escriba SI en mayusculas): "

if not "%confirmacion%"=="SI" (
    echo.
    echo ❌ Operacion cancelada.
    echo.
    pause
    exit /b
)

echo.
echo ══════════════════════════════════════════════════════════
echo [1/3] Eliminando tablas existentes y ejecutando migraciones...
echo ══════════════════════════════════════════════════════════
echo.

php artisan migrate:fresh --force

if %errorlevel% neq 0 (
    echo.
    echo ❌ Error al ejecutar migraciones.
    echo.
    pause
    exit /b 1
)

echo.
echo ══════════════════════════════════════════════════════════
echo [2/3] Configurando sistema de produccion...
echo ══════════════════════════════════════════════════════════
echo.

php artisan db:seed --class=DatabaseProdSeeder --force

if %errorlevel% neq 0 (
    echo.
    echo ❌ Error al ejecutar seeder de produccion.
    echo.
    pause
    exit /b 1
)

echo.
echo ══════════════════════════════════════════════════════════
echo [3/3] Limpiando cache del sistema...
echo ══════════════════════════════════════════════════════════
echo.

php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan optimize

echo.
echo ╔═══════════════════════════════════════════════════════╗
echo ║           ✅ CONFIGURACION COMPLETADA                ║
echo ╚═══════════════════════════════════════════════════════╝
echo.
echo 📝 CREDENCIALES DE ACCESO:
echo.
echo    SuperAdmin:
echo    • Email: andres@empenios.com
echo    • Password: 2905Andres@
echo.
echo    Administrador Esquipulas:
echo    • Email: cvinicio1983@gmail.com
echo    • Username: cvinicio1983
echo.
echo ══════════════════════════════════════════════════════════
echo.

pause
