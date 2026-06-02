# 🔧 CONFIGURACIÓN CLOUDFLARE PARA AVANZA - SOLUCIÓN CORS

## 🎯 Problema

Cloudflare está bloqueando las peticiones entre:
- **Frontend:** https://avanzadigiprenda.stockgenius-sotecpro.com
- **Backend:** https://backendigiprenda.stockgenius-sotecpro.com

---

## 📋 USER-AGENT DEL FRONTEND

Las peticiones del frontend (navegador) usan estos User-Agents:

### Chrome/Edge:
```
Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36
```

### Firefox:
```
Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/120.0
```

### Safari:
```
Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Safari/605.1.15
```

---

## ✅ SOLUCIÓN 1: Reglas de Cloudflare (RECOMENDADO)

### Paso 1: Acceder a Cloudflare

1. Ingresa a: https://dash.cloudflare.com
2. Selecciona tu dominio: **stockgenius-sotecpro.com**
3. Ve a la sección: **Security → WAF**

### Paso 2: Crear Regla de Permiso

1. Click en **"Create rule"** o **"Crear regla"**
2. Nombre de la regla: **"Permitir Avanza Frontend"**

### Paso 3: Configurar la Regla

**Campo 1: Incoming Request**
```
Hostname contains: backendigiprenda.stockgenius-sotecpro.com
```

**Y (AND)**

**Campo 2: Referer**
```
HTTP Referer contains: avanzadigiprenda.stockgenius-sotecpro.com
```

**Acción:**
```
Skip → All remaining custom rules
```

### Paso 4: Guardar y Desplegar

Click en **"Deploy"** o **"Implementar"**

---

## ✅ SOLUCIÓN 2: Desactivar Bot Fight Mode

Si la solución 1 no funciona:

### Paso 1: Ve a Security → Bots

1. Busca **"Bot Fight Mode"**
2. Cámbialo a **OFF** (Desactivado)

### Paso 2: Configura Reglas de Firewall Personalizadas

1. Ve a **Security → WAF → Custom rules**
2. Crea una nueva regla:

**Nombre:** Permitir API Avanza

**Expresión:**
```
(http.host eq "backendigiprenda.stockgenius-sotecpro.com" and http.referer contains "avanzadigiprenda.stockgenius-sotecpro.com")
```

**Acción:** Allow (Permitir)

---

## ✅ SOLUCIÓN 3: Configurar SSL/TLS Correcto

### Verifica el modo SSL/TLS:

1. Ve a **SSL/TLS → Overview**
2. Asegúrate que esté en: **Full (strict)** o **Full**
3. NO usar "Flexible" (causa problemas con APIs)

### Configuración recomendada:

- **SSL/TLS encryption mode:** Full (strict)
- **Always Use HTTPS:** ON
- **Minimum TLS Version:** TLS 1.2
- **Opportunistic Encryption:** ON
- **TLS 1.3:** ON

---

## ✅ SOLUCIÓN 4: Page Rules para CORS

### Crear Page Rule específica:

1. Ve a **Rules → Page Rules**
2. Click en **"Create Page Rule"**

**URL pattern:**
```
backendigiprenda.stockgenius-sotecpro.com/api/*
```

**Settings:**
- **Security Level:** Essentially Off
- **Cache Level:** Bypass
- **Disable Apps:** ON
- **Disable Performance:** ON

**Guardar y Deploy**

---

## ✅ SOLUCIÓN 5: Agregar Dominios a Whitelist

### En Cloudflare Dashboard:

1. Ve a **Security → WAF → Tools**
2. En **IP Access Rules**, agrega:

**Value:** `avanzadigiprenda.stockgenius-sotecpro.com`
**Action:** Whitelist
**Zone:** This website

---

## 🔍 VERIFICAR QUE FUNCIONA

### Opción 1: Desde el Navegador

1. Abre: https://avanzadigiprenda.stockgenius-sotecpro.com
2. Abre Consola del navegador (F12) → Network
3. Intenta hacer login
4. Verifica que las peticiones a la API sean exitosas (código 200)

### Opción 2: Prueba con cURL

```bash
curl -v -X POST https://backendigiprenda.stockgenius-sotecpro.com/api/v1/auth/login \
  -H "Origin: https://avanzadigiprenda.stockgenius-sotecpro.com" \
  -H "Referer: https://avanzadigiprenda.stockgenius-sotecpro.com/" \
  -H "Content-Type: application/json" \
  -H "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36" \
  -d '{"email":"test@test.com","password":"test123"}'
```

**Respuesta esperada:** Código HTTP 200 o 401 (no 403 de Cloudflare)

### Opción 3: Ver Logs de Cloudflare

1. Ve a **Analytics → Security**
2. Revisa los eventos bloqueados
3. Busca peticiones desde tu IP o dominio
4. Verifica si están siendo bloqueadas

---

## 🚨 ERRORES COMUNES Y SOLUCIONES

### Error: "Cloudflare Ray ID" en respuesta

**Causa:** Cloudflare está bloqueando la petición

**Solución:**
1. Verifica que las reglas de WAF estén activas
2. Desactiva temporalmente "Bot Fight Mode"
3. Agrega el dominio a la whitelist

### Error: Mixed Content (HTTP/HTTPS)

**Causa:** Frontend en HTTPS, pero intenta llamar a HTTP

**Solución:**
- Asegúrate que TODAS las URLs en el frontend usen `https://`
- Verifica el archivo `.env` del frontend:
  ```
  VITE_API_URL=https://backendigiprenda.stockgenius-sotecpro.com/api/v1
  ```

### Error: 522 Connection timed out

**Causa:** El servidor backend no responde

**Solución:**
1. Verifica que el servidor backend esté funcionando
2. Revisa los logs del servidor
3. Contacta al hosting

---

## 📝 CONFIGURACIÓN COMPLETA RECOMENDADA

### 1. Cloudflare (Panel Web)

```
✅ SSL/TLS: Full (strict)
✅ Always Use HTTPS: ON
✅ Bot Fight Mode: OFF (o con reglas personalizadas)
✅ WAF Custom Rule: Permitir avanzadigiprenda.stockgenius-sotecpro.com
✅ Page Rule: backendigiprenda.stockgenius-sotecpro.com/api/* → Security: Essentially Off
```

### 2. Backend (Servidor)

```bash
# Archivo: public/.htaccess (YA CONFIGURADO)
Header always set Access-Control-Allow-Origin "*"
Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS, PATCH"
Header always set Access-Control-Allow-Headers "Origin, X-Requested-With, Content-Type, Accept, Authorization"
```

```bash
# Archivo: config/cors.php (YA CONFIGURADO)
'allowed_origins' => [
    'https://avanzadigiprenda.stockgenius-sotecpro.com',
],
```

### 3. Frontend (.env)

```env
VITE_API_URL=https://backendigiprenda.stockgenius-sotecpro.com/api/v1
VITE_APP_URL=https://avanzadigiprenda.stockgenius-sotecpro.com
```

---

## 📞 CONTACTO CON SOPORTE

Si después de estos pasos el problema persiste, contacta a:

### Soporte de Cloudflare:
1. Ve a **Support → Contact Support**
2. Proporciona:
   - Dominio afectado
   - Ray ID del error (visible en el error de Cloudflare)
   - Descripción: "CORS error between frontend and backend API"

### Información a proporcionar:
- **Frontend:** https://avanzadigiprenda.stockgenius-sotecpro.com
- **Backend:** https://backendigiprenda.stockgenius-sotecpro.com
- **Error:** "No 'Access-Control-Allow-Origin' header is present"
- **Ray ID:** [Se muestra en el error de Cloudflare]

---

## ✅ CHECKLIST FINAL

Antes de contactar soporte, verifica:

- [ ] SSL/TLS está en "Full (strict)"
- [ ] Bot Fight Mode está desactivado
- [ ] WAF tiene regla para permitir el frontend
- [ ] Page Rule configurada para `/api/*`
- [ ] Dominio frontend en whitelist
- [ ] Backend `.htaccess` actualizado (git pull)
- [ ] Backend caches limpiados (php artisan optimize:clear)
- [ ] Frontend usa HTTPS en todas las URLs
- [ ] Cache del navegador limpiado
- [ ] Probado en modo incógnito

---

**Última actualización:** Junio 2026
**Documentación:** SOLUCION_CLOUDFLARE_CORS.md
