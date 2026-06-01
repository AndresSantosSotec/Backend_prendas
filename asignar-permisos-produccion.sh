#!/bin/bash

# Script para asignar permisos a usuarios en producción
# Sistema DigiPrenda

echo "========================================"
echo "  ASIGNAR PERMISOS A USUARIOS"
echo "  Sistema DigiPrenda - Producción"
echo "========================================"
echo ""

echo "[1/5] Verificando migraciones..."
php artisan migrate:status
echo ""

echo "[2/5] Ejecutando migraciones pendientes (si las hay)..."
php artisan migrate --force
echo ""

echo "[3/5] Asignando permisos a usuarios sin permisos..."
php artisan permissions:assign-missing
echo ""

echo "[4/5] Limpiando caché..."
php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan optimize
echo ""

echo "[5/5] Verificación completada"
echo ""
echo "========================================"
echo "  PROCESO COMPLETADO"
echo "========================================"
echo ""
echo "Verifica que los usuarios puedan acceder al sistema"
echo "y que sus permisos sean los correctos."
echo ""
