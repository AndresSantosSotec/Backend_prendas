# Guía de Despliegue en Limpio y Multi-Organización (White-Label)

Esta guía explica cómo arrancar el sistema en limpio para cualquier nueva empresa u organización (sea Avanza o cualquier otra marca), garantizando que **no existan nombres ni logotipos hardcodeados** y que toda la identidad corporativa se controle dinámicamente desde la base de datos o el archivo `.env`.

---

## 1. Variables de Entorno (`.env`)

En cada instalación o servidor, configura el archivo `.env` con las variables correspondientes a la organización:

```env
APP_NAME="Sistema de Gestión de Empeños"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://tu-dominio.com

# Identificador de Organización
ORGANIZATION_CODE=01

# Datos Iniciales de la Organización (Opcionales si se configuran en BD)
EMPRESA_NOMBRE="Tu Empresa de Empeños"
EMPRESA_RAZON_SOCIAL="Tu Empresa, Sociedad Anónima"
EMPRESA_NIT="1234567-8"
EMPRESA_DIRECCION="Dirección de la Sede Central"
EMPRESA_TELEFONO="PBX: 2200-0000"
EMPRESA_EMAIL="contacto@tuempresa.com"

# Firmantes y Autoridades Iniciales
REPRESENTANTE_LEGAL_NOMBRE="Nombre del Representante Legal"
PERITO_CONTADOR_NOMBRE="Nombre del Perito Contador"
PERITO_CONTADOR_REGISTRO="12345678"
```

---

## 2. Inicialización en Limpio de la Base de Datos

Para inicializar una nueva base de datos completamente limpia:

```bash
# 1. Ejecutar migraciones
php artisan migrate

# 2. Sembrar catálogos base con configuración limpia y agnóstica de marca
php artisan db:seed
```

El seeder inicializa:
- Usuarios base y permisos del sistema.
- Sucursales base y categorías de prendas / productos.
- `ConfiguracionSistemaSeeder`: Parámetros institucionales en la tabla `configuraciones_sistema`.
- `TbDocSeeder`: Plantillas predeterminadas de recibos, contratos, planes de pago y reportes en `tb_docs`, enlazadas a la configuración institucional.

---

## 3. Configuración Rápida de Marca (Artisan CLI)

Puedes personalizar todos los datos de la nueva organización en cualquier momento mediante el comando interactivo o con banderas:

```bash
# Modo interactivo (te preguntará cada dato con su valor actual por defecto)
php artisan organizacion:configurar

# Modo directo con parámetros:
php artisan organizacion:configurar \
  --nombre="Empeños El Ahorro" \
  --razon-social="Comercializadora El Ahorro, S.A." \
  --nit="9876543-2" \
  --direccion="4ta Calle 5-20 Zona 1, Quetzaltenango" \
  --telefono="7761-0000" \
  --email="info@elahorro.com" \
  --representante="Carlos Morales" \
  --contador="Lic. Roberto Gómez" \
  --registro-contador="54321"

# Restaurar valores limpios iniciales si es necesario:
php artisan organizacion:configurar --reset
```

---

## 4. Personalización desde la Interfaz Web (React)

El sistema cuenta con el módulo de **Personalización de Documentos y Logos** (`/configuracion/documentos`):
1. **Pestaña Organización y Logo:**
   - Permite subir el archivo de imagen del logotipo oficial (PNG, JPG, SVG, WebP).
   - El logo se almacena de forma segura y se inyecta automáticamente en Base64 en todos los PDFs (recibos, contratos, planes de pago, balances).
   - Permite actualizar en vivo: Nombre, NIT, Dirección, Teléfono y Correo.
2. **Pestaña Plantillas de Documentos (`tb_docs`):**
   - Configuración individual por documento: Recibo de Pago, Contrato de Crédito, Plan de Pagos, Recibo de Compra, etc.
   - Selección de variantes (`estandar` para carta u oficio, `ticket_80mm` para impresoras térmicas POS de ventanilla).
   - Botón de **"Vista Previa en Vivo"** con un clic para previsualizar el documento renderizado en tiempo real antes de guardar cambios.

---

## 5. Arquitectura Multi-Logo Dinámica

Para evitar tener que eliminar o renombrar archivos de logotipos en cada instalación:
- El repositorio mantiene los logos de los clientes en `resources/logos/` (ej: `avanza_logo.png`, `cemadec_logo.png`, etc.) y `storage/app/public/logos/`.
- **`LogoResolverService`**: Es el servicio centralizado que resuelve el logo activo con el siguiente orden de precedencia:
  1. **Configuración en Base de Datos**: `empresa_logo_url` en `configuraciones_sistema` o `tb_docs` (establecido desde el panel web o por CLI).
  2. **Variable `.env`**: `APP_LOGO=avanza_logo.png` (o `ORGANIZATION_LOGO=...`).
  3. **Identificador de Marca**: `ORGANIZATION_SLUG=avanza` (busca automáticamente `avanza_logo.png`).
  4. **Logo genérico subido**: `storage/logos/logo.png`.
  5. **Fallback Limpio**: Si no hay ninguna configuración que solicite un logo, el sistema **no asume ninguna marca** y renderiza el nombre institucional en tipografía limpia (100% marca blanca).

### ¿Cómo activar el logo de Avanza en una instalación de Avanza?
- Opción A: En `.env`, coloca `APP_LOGO=avanza_logo.png`
- Opción B: Ejecuta `php artisan organizacion:configurar --logo=avanza_logo.png`
- Opción C: En la interfaz web (`/configuracion/documentos`), en el catálogo de logos del servidor, presiona **"Activar"** sobre Avanza Logo.

### ¿Cómo iniciar una nueva organización sin logo de Avanza?
- Deja `APP_LOGO=` vacío en `.env`.
- Aunque el archivo `avanza_logo.png` exista en el servidor, **nunca se cargará en los contratos de la nueva organización**.
- Sube el nuevo logo desde la UI o colócalo en `resources/logos/tu_marca_logo.png`.

---

## 6. Arquitectura del Motor de Documentos

El motor se rige por una arquitectura limpia y desacoplada:
- **`configuraciones_sistema`**: Almacena los valores de configuración global de la organización.
- **`tb_docs`**: Catálogo de plantillas por tipo de documento y sucursal.
- **`RendererRegistry`**: Lista blanca estricta de renderizadores permitidos (`allowlist`).
- **`DocumentService`**: Resuelve la jerarquía (Sucursal $\to$ Organización $\to$ Default) y emite un `DocumentResult` con el PDF compilado.
- **`LogoResolverService`**: Inyecta dinámicamente el logo correspondiente en Base64 o permite renderizado tipográfico sin dependencias estáticas.
- **`resources/views/pdf/partials/logo.blade.php`**: Partial inteligente que consume `LogoResolverService` para DomPDF.
