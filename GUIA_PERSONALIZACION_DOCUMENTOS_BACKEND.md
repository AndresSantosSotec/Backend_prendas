# 📄 Guía Técnica: Creación y Personalización de Documentos en el Backend (Multi-Documento y Multi-Marca)

Esta guía explica en detalle cómo diseñar, programar y registrar documentos PDF y formatos impresos personalizados (contratos de crédito, recibos térmicos, finiquitos, pagarés, planes de pago y reportes financieros) para diferentes organizaciones o clientes dentro de la plataforma **Dev Empeños (Laravel 12 + DomPDF + React)**.

---

## 🏛️ 1. Arquitectura del Motor de Documentos

El sistema utiliza una arquitectura desacoplada basada en el patrón **Strategy + Registry**, inspirada en la lógica flexible de `tb_documentos` de Microsystem Plus, pero con diseño moderno y tipado estricto en PHP 8.3:

```
[ Solicitud HTTP / Controlador ]
              │
              ▼
    DocumentService::generate(...)
              │
              ├─► 1. Jerarquía de Resolución (Sucursal ➔ Organización ➔ Default) en tb_docs
              ├─► 2. Inyección de Datos Institucionales (Empresa, Logo Base64, Firmantes)
              ├─► 3. LogoResolverService (Multi-logo sin dependencias estáticas)
              └─► 4. RendererRegistry (Lista blanca estricta de renderizadores permitidos)
                             │
                             ▼
                   DocumentRenderer::render()
                             │
                             ▼
                 [ Vista Blade (.blade.php) ] ➔ DomPDF ➔ DocumentResult
```

### Componentes Clave:

| Componente | Ubicación en el Proyecto | Responsabilidad |
| :--- | :--- | :--- |
| **`DocumentRenderer`** | `app/Documents/Contracts/DocumentRenderer.php` | Interfaz obligatoria que implementa cada renderizador (`render(array $data, ?TbDoc $config)`). |
| **`DocumentResult`** | `app/Documents/DTOs/DocumentResult.php` | Objeto inmutable que contiene el binario del PDF, nombre de archivo, mimeType y helpers (Base64, DataURI). |
| **`RendererRegistry`** | `app/Documents/RendererRegistry.php` | Lista blanca de claves permitidas (ej. `contrato.estandar`, `recibo.ticket_80mm`). **Previene ejecución arbitraria de clases**. |
| **`DocumentService`** | `app/Documents/DocumentService.php` | Orquestador: busca la plantilla adecuada, une los datos de negocio con la empresa y genera previsualizaciones. |
| **`LogoResolverService`** | `app/Services/LogoResolverService.php` | Resuelve qué logo mostrar (de BD, `.env`, slug de cliente o catálogo en disco) en formato Base64. |
| **`tb_docs` (Modelo `TbDoc`)** | `app/Models/TbDoc.php` | Tabla en base de datos donde se define qué vista Blade, variante, logo y textos usa cada documento. |

---

## 🗂️ 2. Estructura de Carpetas de Vistas

Las plantillas de los documentos se crean como vistas Blade estándar dentro de `resources/views/`:

```
empenios-api/resources/views/
├── creditos/
│   ├── contrato.blade.php           <-- Contrato estándar de crédito prendario
│   ├── plan-pagos.blade.php         <-- Tabla de amortización estándar
│   ├── recibo.blade.php             <-- Recibo de pago carta / media carta
│   └── recibo_pago.blade.php        <-- Comprobante detallado
├── pdf/
│   ├── partials/
│   │   ├── logo.blade.php           <-- Encabezado de logo inteligente con LogoResolverService
│   │   └── firmas.blade.php         <-- Bloque estándar de firmas y huella digital
│   ├── contrato-compra.blade.php    <-- Contrato de compraventa de bien mueble
│   ├── recibo-compra.blade.php      <-- Recibo de liquidación de compra
│   └── custom/                      <-- (Recomendado) Aquí creas plantillas exclusivas de clientes
│       ├── cemadec/
│       │   └── contrato.blade.php
│       └── guateprenda/
│           └── contrato_notarial.blade.php
└── reportes/
    └── contabilidad/
        ├── balance-general.blade.php
        └── estado-resultados.blade.php
```

---

## 🚀 3. Paso a Paso: Crear un Nuevo Formato Personalizado para un Cliente

Supongamos que un cliente nuevo llamado **"PrendaFácil"** necesita un contrato con cláusulas legales notariales específicas, tipografía `Times New Roman` y márgenes personalizados de 3 cm.

### Paso 1: Crear la Vista Blade Personalizada

Crea el archivo `resources/views/pdf/custom/prendafacil_contrato.blade.php`:

```blade
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ $tituloDocumento ?? 'Contrato de Préstamo Prendario' }}</title>
    <style>
        @page {
            size: letter portrait;
            margin: 2.5cm 2cm 2.5cm 2.5cm;
        }
        body {
            font-family: 'Times New Roman', Times, serif;
            font-size: 11pt;
            line-height: 1.4;
            color: #111;
            text-align: justify;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .titulo {
            font-size: 13pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
            margin-bottom: 12px;
        }
        .clausula {
            margin-bottom: 8px;
            text-indent: 1.5em;
        }
        .clausula-titulo {
            font-weight: bold;
        }
        .tabla-firmas {
            width: 100%;
            margin-top: 50px;
            page-break-inside: avoid;
        }
        .linea-firma {
            border-top: 1px solid #000;
            width: 80%;
            margin: 0 auto 5px auto;
        }
    </style>
</head>
<body>

    {{-- 1. ENCABEZADO CON LOGO DINÁMICO --}}
    <table class="header-table">
        <tr>
            <td width="30%" style="vertical-align: middle;">
                {{-- Inclusión del partial oficial de logo --}}
                @include('pdf.partials.logo', ['height' => '70px'])
            </td>
            <td width="70%" style="text-align: right; vertical-align: middle;">
                <div style="font-size: 14pt; font-weight: bold;">{{ $empresa['nombre'] }}</div>
                <div style="font-size: 10pt; color: #555;">NIT: {{ $empresa['nit'] }} | Tel: {{ $empresa['telefono'] }}</div>
                <div style="font-size: 9pt; color: #777;">{{ $sucursal->direccion ?? $empresa['direccion'] }}</div>
            </td>
        </tr>
    </table>

    {{-- 2. TÍTULO DEL DOCUMENTO --}}
    <div class="titulo">
        {{ $tituloDocumento ?? 'CONTRATO DE MUTUO CON GARANTÍA PRENDARIA' }}
    </div>

    {{-- 3. CUERPO LEGAL Y CLÁUSULAS --}}
    <p class="clausula">
        En la ciudad de Guatemala, el día <strong>{{ $fechaContrato ?? date('d/m/Y') }}</strong>, comparecen por una parte
        <strong>{{ $empresa['razon_social'] ?? $empresa['nombre'] }}</strong> (en adelante "La Acreedora"), y por la otra parte
        el señor(a) <strong>{{ $cliente->nombre_completo ?? ($cliente->nombre . ' ' . $cliente->apellido) }}</strong>,
        con DPI número <strong>{{ $cliente->dpi ?? $cliente->numero_documento }}</strong>, quien en adelante se denominará "El Deudor".
    </p>

    <div class="clausula">
        <span class="clausula-titulo">PRIMERA (Del Préstamo):</span>
        La Acreedora entrega en calidad de mutuo la cantidad de <strong>Q{{ number_format($credito->monto_prestamo ?? $credito->monto_aprobado, 2) }}</strong>,
        correspondiente al crédito número <strong>{{ $credito->codigo_credito ?? $credito->numero_credito }}</strong>.
    </div>

    <div class="clausula">
        <span class="clausula-titulo">SEGUNDA (De las Prendas en Garantía):</span>
        El Deudor garantiza el cumplimiento de la obligación prendando los siguientes bienes muebles:
        <ul>
            @foreach($prendas as $prenda)
                <li>
                    <strong>{{ $prenda->descripcion ?? $prenda->descripcion_general }}</strong> - 
                    Valor de Avalúo: Q{{ number_format($prenda->valor_tasacion ?? $prenda->monto_avaluo, 2) }}
                    (Serie/Detalles: {{ $prenda->numero_serie ?? $prenda->observaciones ?? 'N/A' }})
                </li>
            @endforeach
        </ul>
    </div>

    {{-- 4. FIRMAS --}}
    @if($mostrarFirmas ?? true)
    <table class="tabla-firmas">
        <tr>
            <td width="50%" align="center">
                <div class="linea-firma"></div>
                <strong>{{ $cliente->nombre_completo ?? ($cliente->nombre . ' ' . $cliente->apellido) }}</strong><br>
                <span>EL DEUDOR (CLIENTE)</span><br>
                <small>DPI: {{ $cliente->dpi ?? $cliente->numero_documento }}</small>
            </td>
            <td width="50%" align="center">
                <div class="linea-firma"></div>
                <strong>{{ $firmante2Nombre ?? $empresa['nombre'] }}</strong><br>
                <span>{{ $firmante2Titulo ?? 'REPRESENTANTE LEGAL' }}</span><br>
                <small>{{ $empresa['nombre'] }}</small>
            </td>
        </tr>
    </table>
    @endif

</body>
</html>
```

---

### Paso 2: Registrar la Variante en `RendererRegistry.php`

Abre `app/Documents/RendererRegistry.php`. Si vas a reutilizar el renderer estándar de contratos (`ContratoStandardRenderer`), simplemente registra la nueva clave de variante en el catálogo para que aparezca en la interfaz web y sea validada:

```php
// En app/Documents/RendererRegistry.php

private array $variantesCatalogo = [
    'recibo.estandar' => 'Recibo Estándar (Carta / Media Carta)',
    'recibo.ticket_80mm' => 'Recibo Térmico POS (80mm)',
    'contrato.estandar' => 'Contrato Estándar Prendario',
    'contrato.notarial_prendafacil' => 'Contrato Notarial Extendido (PrendaFácil)', // <-- NUEVA VARIANTE
    'plan_pagos.estandar' => 'Plan de Pagos / Cronograma de Cuotas',
    // ...
];

public function getPorTipo(string $tipoDocumento, string $variante = 'estandar'): DocumentRenderer
{
    $key = "{$tipoDocumento}.{$variante}";

    return match ($key) {
        'contrato.estandar',
        'contrato.notarial_prendafacil' => app(\App\Documents\Renderers\ContratoStandardRenderer::class),
        
        'recibo.estandar' => app(\App\Documents\Renderers\ReciboStandardRenderer::class),
        'recibo.ticket_80mm' => app(\App\Documents\Renderers\ReciboTicketRenderer::class),
        // ...
        default => throw new \InvalidArgumentException("No existe un renderizador para [{$key}]")
    };
}
```

---

### Paso 3: Configurar la Plantilla en Base de Datos (`tb_docs`)

El motor de documentos busca en la tabla `tb_docs` qué vista debe cargar. Puedes configurarlo de cualquiera de estas 3 formas:

#### Opción A: Desde el Seeder (`database/seeders/TbDocSeeder.php`)
Agrega la plantilla para que se cree al inicializar el sistema:

```php
TbDoc::updateOrCreate(
    [
        'tipo_documento' => 'contrato_credito',
        'plantilla_variante' => 'notarial_prendafacil',
        'sucursal_id' => null, // null = aplica a toda la empresa
    ],
    [
        'organizacion_code' => '01',
        'nombre_documento' => 'Contrato Notarial PrendaFácil',
        'vista_pdf' => 'pdf.custom.prendafacil_contrato', // <-- La ruta a tu vista Blade
        'titulo_personalizado' => 'Contrato de Mutuo con Garantía Mobiliaria',
        'mostrar_logo' => true,
        'mostrar_firmas' => true,
        'activo' => true,
    ]
);
```

#### Opción B: Por SQL directo o phpMyAdmin
```sql
INSERT INTO tb_docs (
    tipo_documento, nombre_documento, plantilla_variante, vista_pdf, 
    titulo_personalizado, mostrar_logo, mostrar_firmas, activo, created_at, updated_at
) VALUES (
    'contrato_credito', 'Contrato Notarial PrendaFácil', 'notarial_prendafacil', 
    'pdf.custom.prendafacil_contrato', 'Contrato Notarial Mobiliario', 1, 1, 1, NOW(), NOW()
);
```

#### Opción C: Desde la Interfaz Web (Solo Superadmin)
Ingresa al módulo **Módulo Contable ➔ Plantillas PDF / Logo ➔ Plantillas por Documento**, edita el registro del Contrato e indica:
- **Vista Blade:** `pdf.custom.prendafacil_contrato`
- **Variante:** `notarial_prendafacil`
- Presiona **"Guardar Cambios"** y luego el botón **"Vista Previa"** para verificar el resultado visual de inmediato.

---

## 🎨 4. Variables Globales Inyectadas Automáticamente en las Vistas

Cada vez que se procesa una plantilla, `DocumentService::prepararDatosUnificados` inyecta automáticamente las siguientes variables sin que tengas que consultarlas manualmente en el controlador:

### Datos Institucionales de la Empresa:
| Variable | Tipo | Descripción |
| :--- | :--- | :--- |
| **`$empresa['nombre']`** | `string` | Nombre comercial configurado en el sistema. |
| **`$empresa['razon_social']`**| `string` | Razón social legal (S.A., S.C., etc.). |
| **`$empresa['nit']`** | `string` | Número de Identificación Tributaria. |
| **`$empresa['direccion']`** | `string` | Dirección principal o sede central. |
| **`$empresa['telefono']`** | `string` | Teléfono / PBX de contacto. |
| **`$empresa['email']`** | `string` | Correo electrónico oficial. |
| **`$logoBase64`** | `string\|null` | Imagen del logotipo lista en Data URI (`data:image/png;base64,...`). |
| **`$mostrarLogo`** | `bool` | `true` si la plantilla tiene activo el checkbox de mostrar logo. |

### Autoridades y Firmantes:
| Variable | Tipo | Descripción |
| :--- | :--- | :--- |
| **`$firmante1Nombre`** | `string` | Nombre del primer firmante (normalmente el Contador General). |
| **`$firmante1Titulo`** | `string` | Título del firmante (ej. "Contador Autorizado"). |
| **`$firmante2Nombre`** | `string` | Nombre del segundo firmante (Representante Legal / Gerente). |
| **`$firmante2Titulo`** | `string` | Título del firmante (ej. "Representante Legal"). |
| **`$peritoContadorNombre`** | `string` | Nombre del Perito Contador para balances y estados financieros. |
| **`$peritoContadorRegistro`**| `string` | Número de registro profesional del Perito Contador. |

### Datos de Negocio (específicos del evento):
- **Contratos:** `$credito`, `$cliente`, `$sucursal`, `$prendas`, `$fechaContrato`.
- **Recibos de Pago:** `$recibo`, `$credito`, `$cliente`, `$sucursal`, `$pagosDetalle`.
- **Planes de Pago:** `$credito`, `$cliente`, `$planPagos` (colección ordenada de cuotas).

---

## 🏷️ 5. Manejo Multi-Logo Dinámico para Nuevos Clientes

Para evitar tener que borrar o sobreescribir archivos en cada instalación, el sistema cuenta con `LogoResolverService`:

1. **Colocar el archivo:** Guarda el logo en `resources/logos/prendafacil_logo.png` (o súbelo desde la interfaz web).
2. **Asignación por Entorno:** En el archivo `.env` del servidor del cliente:
   ```env
   APP_LOGO=prendafacil_logo.png
   # O por slug:
   ORGANIZATION_SLUG=prendafacil
   ```
3. **Asignación por Base de Datos:** También puedes asignarlo con el comando Artisan:
   ```bash
   php artisan organizacion:configurar --logo=prendafacil_logo.png
   ```
4. **Comportamiento Seguro:** Si no se define ningún logo, el partial `pdf.partials.logo` renderiza automáticamente el nombre de la empresa con tipografía sobria, garantizando **cero llamadas a logos ajenos**.

---

## 📏 6. Formatos Térmicos POS (Ticket 80mm)

Si el cliente requiere imprimir tickets térmicos para impresoras de punto de venta (Epson TM-T20, Bixolon, etc.):

1. Configura el `@page` con ancho fijo de `80mm` o `76mm` y altura auto:
   ```css
   @page {
       size: 80mm 250mm;
       margin: 2mm 3mm;
   }
   body {
       font-family: 'Courier New', Courier, monospace;
       font-size: 8pt;
       width: 74mm;
   }
   ```
2. Asocia la variante `recibo.ticket_80mm` en `tb_docs`. El renderizador `ReciboTicketRenderer` configurará automáticamente el tamaño de papel en DomPDF:
   ```php
   $pdf->setPaper([0, 0, 226.77, 600], 'portrait'); // 80mm en puntos tipográficos
   ```

---

## 🧪 7. Pruebas y Validación Inmediata

### Vía Artisan Tinker:
Para verificar que tu nueva vista compila y genera PDF sin errores de sintaxis:

```bash
php artisan tinker --execute="
\$service = app(\App\Documents\DocumentService::class);
\$res = \$service->generarPreview(1); // ID del documento en tb_docs
echo 'Generado: ' . strlen(\$res->content) . ' bytes. Mime: ' . \$res->mimeType;
"
```

### Vía Navegador:
Como usuario con rol `superadmin`, visita en el navegador o Postman:
```http
GET /api/v1/configuracion/documentos/{id}/preview
Headers:
  Authorization: Bearer TU_TOKEN_SUPERADMIN
```
El endpoint responderá con el PDF en streaming con `Content-Disposition: inline`, permitiendo visualizarlo en el visor de PDF integrado de React o en el navegador.

---

## ⚠️ 8. Reglas de Oro para Plantillas DomPDF

1. **Usa Tablas HTML para Layouts:** DomPDF no soporta Flexbox moderno (`display: flex`) ni CSS Grid. Usa etiquetas `<table>` con `width="100%"` para alinear columnas, logotipos y firmas.
2. **Evita URLs HTTP externas:** DomPDF se ralentiza o lanza errores si intenta descargar imágenes vía `http://`. Usa siempre el logo inyectado en Base64 mediante `@include('pdf.partials.logo')`.
3. **Control de Saltos de Página:** Evita que firmas o tablas se corten a la mitad con la propiedad CSS:
   ```css
   .tabla-firmas, tr {
       page-break-inside: avoid;
   }
   ```
4. **Permisos de Archivo:** Asegúrate de que las carpetas `storage/app/public/logos` tengan permisos de lectura para el servidor web (`chmod -R 775 storage`).
