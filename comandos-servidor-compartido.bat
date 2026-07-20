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
echo 📌 IMPORTANTE: Este proyecto usa ea-php83 (EasyApache PHP 8.3)
echo    Todos los comandos artisan deben usar el prefijo: ea-php83
echo.
echo OPCION 1: RESET COMPLETO (Elimina todos los datos)
echo ══════════════════════════════════════════════════════════
echo.
echo cd /home/tu_usuario/public_html/api
echo git pull origin main
echo ea-php83 artisan migrate:fresh --force
echo ea-php83 artisan db:seed --class=DatabaseProdSeeder --force
echo ea-php83 artisan optimize:clear
echo ea-php83 artisan config:cache
echo ea-php83 artisan route:cache
echo.
echo ══════════════════════════════════════════════════════════
echo.
echo OPCION 2: SOLO AGREGAR USUARIOS (Sin borrar datos)
echo ══════════════════════════════════════════════════════════
echo.
echo cd /home/tu_usuario/public_html/api
echo git pull origin main
echo ea-php83 artisan migrate --force
echo ea-php83 artisan db:seed --class=SucursalProdSeeder
echo ea-php83 artisan db:seed --class=UserProdSeeder
echo ea-php83 artisan permissions:assign-missing
echo ea-php83 artisan optimize:clear
echo.
echo ══════════════════════════════════════════════════════════
echo.
echo OPCION 3: SOLO ASIGNAR PERMISOS (Sin agregar usuarios)
echo ══════════════════════════════════════════════════════════
echo.
echo cd /home/tu_usuario/public_html/api
echo ea-php83 artisan permissions:assign-missing
echo ea-php83 artisan optimize:clear
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
