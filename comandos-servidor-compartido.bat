@echo off
REM =================================================================
REM   SCRIPT ALTERNATIVO PARA SERVIDORES COMPARTIDOS
REM   Sistema DigiPrenda
REM =================================================================
REM
REM Este script es para cuando NO puedes ejecutar scripts .sh
REM o cuando necesitas hacerlo comando por comando.
REM
REM Ejecuta cada paso manualmente copiando y pegando en la terminal.
REM =================================================================

echo.
echo ╔═══════════════════════════════════════════════════════╗
echo ║    COMANDOS PARA SERVIDOR COMPARTIDO                 ║
echo ║              Sistema DigiPrenda                      ║
echo ╚═══════════════════════════════════════════════════════╝
echo.
echo.
echo PASO 1: Copiar estos comandos y ejecutarlos UNO POR UNO
echo ══════════════════════════════════════════════════════════
echo.
echo php artisan migrate:fresh --force
echo php artisan db:seed --class=DatabaseProdSeeder --force
echo php artisan config:clear
echo php artisan cache:clear
echo php artisan route:clear
echo php artisan optimize
echo.
echo ══════════════════════════════════════════════════════════
echo.
echo ALTERNATIVA: Si solo necesitas agregar usuarios (sin borrar datos)
echo ══════════════════════════════════════════════════════════
echo.
echo php artisan db:seed --class=SucursalProdSeeder --force
echo php artisan db:seed --class=UserProdSeeder --force
echo php artisan permissions:assign-missing
echo php artisan optimize:clear
echo.
echo ══════════════════════════════════════════════════════════
echo.
echo 📝 Credenciales de acceso al finalizar:
echo.
echo    SuperAdmin: andres@empenios.com / 2905Andres@
echo    Admin Esquipulas: cvinicio1983@gmail.com
echo.
echo ══════════════════════════════════════════════════════════
echo.

pause
