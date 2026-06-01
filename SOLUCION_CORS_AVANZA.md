# 🔧 SOLUCIÓN ERROR CORS - AVANZA

## ❌ Error Actual

```
Access to fetch at 'https://backendigiprenda.stockgenius-sotecpro.com/api/v1/auth/login' 
from origin 'https://avanzadigiprenda.stockgenius-sotecpro.com' has been blocked by CORS policy: 
Response to preflight request doesn't pass access control check: 
No 'Access-Control-Allow-Origin' header is present on the requested resource.
```

## 🎯 Causa del Problema

1. El servidor de producción NO ha actualizado el código
2. El archivo `.htaccess` no tenía configurados los headers CORS
3. El cache de Laravel no se ha limpiado

---

## ✅ SOLUCIÓN COMPLETA

### Paso 1: Conectarse al Servidor

Accede a tu servidor mediante:
- **SSH** (recomendado)
- **Terminal de cPanel**

### Paso 2: Actualizar Código y Configuración

**Ejecuta este comando completo (copia y pega todo):**

```bash
cd ~/public_html/api && git pull origin main && ea-php83 artisan config:clear && ea-php83 artisan cache:clear && ea-php83 artisan view:clear && ea-php83 artisan route:clear && ea-php83 artisan optimize:clear && ea-php83 artisan config:cache && ea-php83 artisan route:cache && ea-php83 artisan optimize && echo "✅ CORS actualizado correctamente"
```

**O paso por paso:**

```bash
# 1. Ir al directorio de la API
cd ~/public_html/api

# 2. Descargar últimos cambios de GitHub
git pull origin main

# 3. Limpiar TODOS los caches
ea-php83 artisan config:clear
ea-php83 artisan cache:clear
ea-php83 artisan view:clear
ea-php83 artisan route:clear
ea-php83 artisan optimize:clear

# 4. Regenerar caches optimizados
ea-php83 artisan config:cache
ea-php83 artisan route:cache
ea-php83 artisan optimize

# 5. Verificar que .htaccess se actualizó
cat public/.htaccess | grep "Access-Control"
```

### Paso 3: Verificar Permisos del .htaccess

Si el archivo `.htaccess` no se actualizó, ejecuta:

```bash
cd ~/public_html/api/public
chmod 644 .htaccess
```

---

## 🔍 Verificar que Funciona

### Opción 1: Desde el Navegador

1. Abre el sitio: **https://avanzadigiprenda.stockgenius-sotecpro.com**
2. Intenta hacer login
3. Abre la consola del navegador (F12)
4. Verifica que NO haya errores CORS

### Opción 2: Desde Terminal (Prueba Directa)

```bash
curl -I -X OPTIONS https://backendigiprenda.stockgenius-sotecpro.com/api/v1/auth/login \
  -H "Origin: https://avanzadigiprenda.stockgenius-sotecpro.com" \
  -H "Access-Control-Request-Method: POST" \
  -H "Access-Control-Request-Headers: Content-Type, Authorization"
```

**Respuesta esperada:**
```
HTTP/1.1 204 No Content
Access-Control-Allow-Origin: *
Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH
Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization, X-XSRF-TOKEN
Access-Control-Allow-Credentials: true
```

---

## 📋 Cambios Realizados

### 1. Archivo `public/.htaccess` (NUEVO)

Ahora incluye headers CORS directamente en Apache:

```apache
# CORS Headers - Permitir peticiones desde Avanza
Header always set Access-Control-Allow-Origin "*"
Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS, PATCH"
Header always set Access-Control-Allow-Headers "Origin, X-Requested-With, Content-Type, Accept, Authorization, X-XSRF-TOKEN"
Header always set Access-Control-Allow-Credentials "true"
Header always set Access-Control-Max-Age "3600"

# Handle preflight OPTIONS requests
RewriteCond %{REQUEST_METHOD} OPTIONS
RewriteRule ^(.*)$ $1 [R=204,L]
```

### 2. Archivo `config/cors.php` (ACTUALIZADO)

Agregado el dominio de Avanza:

```php
'allowed_origins' => [
    'https://avanzadigiprenda.stockgenius-sotecpro.com', // ✅ Nuevo
    'https://digiprenda.inniserver.net',
    // ... otros
],
```

---

## ⚠️ Si TODAVÍA No Funciona

### Problema 1: mod_headers no está habilitado

Si obtienes error "Invalid command 'Header'", contacta a tu proveedor de hosting para que habiliten `mod_headers`.

**Solución alternativa:** Crear archivo `.user.ini` en `public/`:

```ini
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS, PATCH");
header("Access-Control-Allow-Headers: Origin, X-Requested-With, Content-Type, Accept, Authorization, X-XSRF-TOKEN");
header("Access-Control-Allow-Credentials: true");
```

### Problema 2: Cache del Navegador

Limpia el cache del navegador:
- **Chrome/Edge:** `Ctrl + Shift + Del` → Limpiar todo
- **Firefox:** `Ctrl + Shift + Del` → Limpiar todo
- **Modo Incógnito:** Prueba en ventana privada

### Problema 3: CDN o Cloudflare

Si usan Cloudflare o CDN:
1. Ve al panel de Cloudflare
2. Purge Cache (Limpiar cache)
3. Verifica que SSL/TLS esté en "Full" o "Full (strict)"

---

## 🆘 Troubleshooting Adicional

### Ver logs de Apache en servidor compartido:

```bash
tail -f ~/logs/error_log
```

### Ver logs de Laravel:

```bash
tail -f ~/public_html/api/storage/logs/laravel.log
```

### Verificar versión de PHP en el servidor:

```bash
ea-php83 -v
```

---

## 📞 Contacto

Si después de estos pasos el problema persiste:

1. Toma captura de pantalla del error completo
2. Ejecuta el comando `curl` de verificación y comparte el resultado
3. Comparte los últimos 20 líneas del log de errores:
   ```bash
   tail -20 ~/public_html/api/storage/logs/laravel.log
   ```

---

**Última actualización:** Junio 2026
**Commit:** 7504433 + .htaccess actualizado
