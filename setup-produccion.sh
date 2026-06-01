#!/bin/bash

# =================================================================
#   SCRIPT DE CONFIGURACIÓN INICIAL DE PRODUCCIÓN
#   Sistema DigiPrenda - Sucursal Esquipulas
# =================================================================
#
# Este script hace un RESET COMPLETO de la base de datos y
# configura el sistema para producción con:
#   - SuperAdmin (andres@empenios.com)
#   - Sucursal Esquipulas
#   - 6 usuarios de producción
#   - Todas las configuraciones del sistema
#
# ⚠️  ADVERTENCIA: Este comando ELIMINARÁ TODOS LOS DATOS
#    Solo ejecutar en configuración inicial o reset completo.
# =================================================================

echo ""
echo "╔═══════════════════════════════════════════════════════╗"
echo "║    CONFIGURACION INICIAL DE PRODUCCION               ║"
echo "║              Sistema DigiPrenda                      ║"
echo "╚═══════════════════════════════════════════════════════╝"
echo ""

# Confirmación de seguridad
echo "⚠️  ADVERTENCIA: Este proceso ELIMINARA TODOS LOS DATOS existentes."
echo ""
read -p "¿Estas seguro de continuar? (escriba SI en mayusculas): " confirmacion

if [ "$confirmacion" != "SI" ]; then
    echo ""
    echo "❌ Operacion cancelada."
    echo ""
    exit 0
fi

echo ""
echo "══════════════════════════════════════════════════════════"
echo "[1/3] Eliminando tablas existentes y ejecutando migraciones..."
echo "══════════════════════════════════════════════════════════"
echo ""

php artisan migrate:fresh --force

if [ $? -ne 0 ]; then
    echo ""
    echo "❌ Error al ejecutar migraciones."
    echo ""
    exit 1
fi

echo ""
echo "══════════════════════════════════════════════════════════"
echo "[2/3] Configurando sistema de produccion..."
echo "══════════════════════════════════════════════════════════"
echo ""

php artisan db:seed --class=DatabaseProdSeeder --force

if [ $? -ne 0 ]; then
    echo ""
    echo "❌ Error al ejecutar seeder de produccion."
    echo ""
    exit 1
fi

echo ""
echo "══════════════════════════════════════════════════════════"
echo "[3/3] Limpiando cache del sistema..."
echo "══════════════════════════════════════════════════════════"
echo ""

php artisan config:clear
php artisan cache:clear
php artisan route:clear
php artisan optimize

echo ""
echo "╔═══════════════════════════════════════════════════════╗"
echo "║           ✅ CONFIGURACION COMPLETADA                ║"
echo "╚═══════════════════════════════════════════════════════╝"
echo ""
echo "📝 CREDENCIALES DE ACCESO:"
echo ""
echo "   SuperAdmin:"
echo "   • Email: andres@empenios.com"
echo "   • Password: 2905Andres@"
echo ""
echo "   Administrador Esquipulas:"
echo "   • Email: cvinicio1983@gmail.com"
echo "   • Username: cvinicio1983"
echo ""
echo "══════════════════════════════════════════════════════════"
echo ""
