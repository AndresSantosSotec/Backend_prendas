@echo off
echo ========================================
echo   ASIGNAR PERMISOS A USUARIOS
echo   Sistema DigiPrenda - Produccion
echo ========================================
echo.

echo [1/5] Verificando migraciones...
php artisan migrate:status
echo.

echo [2/5] Ejecutando migraciones pendientes (si las hay)...
php artisan migrate --force
echo.

echo [3/5] Asignando permisos a usuarios sin permisos...
php artisan permissions:assign-missing
echo.

echo [4/5] Limpiando cache...
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan optimize
echo.

echo [5/5] Verificacion completada
echo.
echo ========================================
echo   PROCESO COMPLETADO
echo ========================================
echo.
echo Verifica que los usuarios puedan acceder al sistema
echo y que sus permisos sean los correctos.
echo.

pause
