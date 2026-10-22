# 🚀 GUÍA DE ACTUALIZACIÓN SEGURA EN PRODUCCIÓN (cPanel Compartido)
## Sistema de Gestión de Empeños (Avanza & Prendamás)

> [!IMPORTANT]
> **ENTORNO CPANEL / EASYAPACHE (PHP 8.3):**
> En servidores cPanel compartido, el comando `php` regular puede apuntar a una versión incompatible. Se debe utilizar **obligatoriamente el prefijo `ea-php83`** para invocar a PHP 8.3 en todos los comandos de Artisan.

---

## 🛡️ Principios de Seguridad en Base de Datos

1. **PROHIBIDO usar `migrate:fresh` o `migrate:reset`**:
   - Estos comandos borran y destruyen todas las tablas. En producción **NUNCA** se deben ejecutar.
2. **Usar siempre migraciones aditivas**:
   - Se ejecutan con `ea-php83 artisan migrate --force`. Solo crea las columnas o tablas nuevas que falten sin alterar datos existentes.
3. **Idempotencia y Cero Pérdida de Datos**:
   - Los scripts `AvanzaOrganizacionSeeder` y `PrendamasOrganizacionSeeder` usan `firstOrCreate` y `updateOrCreate`. Clientes, préstamos, prendas, compras, cajas y contabilidad se conservan intactos.

---

## 🏢 PROCEDIMIENTO 1: Actualizar Producción en AVANZA

### Paso 1: Backend en cPanel (`empenios-api`)
Abre la terminal de cPanel (o accede por SSH) y ejecuta en la carpeta del backend:

```bash
cd /home/usuario/public_html/empenios-api  # Ajusta a tu ruta en cPanel

# 1. Bajar últimos cambios
git pull

# 2. Migraciones seguras sin pérdida de datos
ea-php83 artisan migrate --force

# 3. Aplicar configuración segura de Avanza (sin borrar nada)
ea-php83 artisan db:seed --class=AvanzaOrganizacionSeeder

# 4. Limpieza completa de caché
ea-php83 artisan config:clear
ea-php83 artisan cache:clear
ea-php83 artisan route:clear
ea-php83 artisan view:clear

# 5. Generación de caché optimizado para producción
ea-php83 artisan config:cache
ea-php83 artisan route:cache
ea-php83 artisan view:cache
```

> **Comando rápido de 1 sola línea (copiar y pegar):**
> ```bash
> git pull && ea-php83 artisan migrate --force && ea-php83 artisan db:seed --class=AvanzaOrganizacionSeeder && ea-php83 artisan config:clear && ea-php83 artisan cache:clear && ea-php83 artisan route:clear && ea-php83 artisan view:clear && ea-php83 artisan config:cache && ea-php83 artisan route:cache && ea-php83 artisan view:cache
> ```

---

### Paso 2: Frontend (`sistema-de-gestin-de`)

```bash
cd /home/usuario/public_html/sistema-de-gestin-de
git pull
npm install
npm run build
```

---

## 🏢 PROCEDIMIENTO 2: Actualizar Producción en PRENDAMÁS

### Paso 1: Backend en cPanel (`empenios-api`)

```bash
cd /home/usuario/public_html/empenios-api

# 1. Bajar últimos cambios
git pull

# 2. Migraciones seguras
ea-php83 artisan migrate --force

# 3. Aplicar configuración segura de Prendamás
ea-php83 artisan db:seed --class=PrendamasOrganizacionSeeder

# 4. Limpieza de caché
ea-php83 artisan config:clear
ea-php83 artisan cache:clear
ea-php83 artisan route:clear
ea-php83 artisan view:clear

# 5. Regeneración de caché
ea-php83 artisan config:cache
ea-php83 artisan route:cache
ea-php83 artisan view:cache
```

> **Comando rápido de 1 sola línea para Prendamás:**
> ```bash
> git pull && ea-php83 artisan migrate --force && ea-php83 artisan db:seed --class=PrendamasOrganizacionSeeder && ea-php83 artisan config:clear && ea-php83 artisan cache:clear && ea-php83 artisan route:clear && ea-php83 artisan view:clear && ea-php83 artisan config:cache && ea-php83 artisan route:cache && ea-php83 artisan view:cache
> ```

---

### Paso 2: Frontend (`sistema-de-gestin-de`)

```bash
cd /home/usuario/public_html/sistema-de-gestin-de
git pull
npm install
npm run build
```

---

## 📋 Resumen de Parámetros por Tenant

| Parámetro | Empeños Avanza | Prendamás+ |
| :--- | :--- | :--- |
| **Prefijo PHP cPanel** | `ea-php83 artisan` | `ea-php83 artisan` |
| **Código Organización** | `AVANZA` | `01` |
| **Logo Oficial** | `avanza_logo.png` | `logos/prendamas_logo.png` |
| **Tasa por período directa** | `false` (Prorrateo mensual tradicional) | `true` (Tasa directa de producto) |
| **Descuentos en Créditos** | Habilitado por crédito | Habilitado por crédito |
| **Seeder de Configuración** | `AvanzaOrganizacionSeeder` | `PrendamasOrganizacionSeeder` |

---

## 🔍 Checklist de Verificación en Producción
Tras correr los comandos en cPanel:
1. **Configuración de Intereses**: Pestaña *Interés Diario y Descuentos* muestra la modalidad Mensual/Diario y la política de descuentos sin días de semana.
2. **Nuevo Crédito**: En el Paso 2 aparece la tarjeta *Descuento en Intereses al Crédito* y recalcula en tiempo real el ahorro en la simulación.
3. **Caja y Bóveda**: Permite anular movimientos bajo justificación y permisos administrativos.
