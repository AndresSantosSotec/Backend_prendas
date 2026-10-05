<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\TbDoc;
use App\Models\ConfiguracionSistema;
use App\Models\Sucursal;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;

class CrearFormatoDocumentoCommand extends Command
{
    /**
     * El nombre y firma del comando en consola.
     *
     * @var string
     */
    protected $signature = 'doc:crear-formato
                            {slug? : Identificador/slug de la marca, empresa o variante (ej: prendafacil, vip, esquipulas)}
                            {--tipo= : Tipo de documento (contrato_credito, plan_pagos, recibo_pago, contrato_compra, recibo_compra)}
                            {--heredar= : Clonar de una plantilla existente (prendamas, estandar, o slug de variante)}
                            {--desde-cero : Generar una plantilla limpia y profesional desde cero}
                            {--nombre= : Nombre amigable descriptivo para el documento}
                            {--org= : Código de organización (ej: 01, 02). Por defecto usa la activa en BD}
                            {--sucursal= : ID de sucursal específica (opcional, null = todas las sucursales)}
                            {--desactivar : Guardar la plantilla sin marcarla activa de inmediato}
                            {--force : Sobrescribir archivo Blade si ya existe}';

    /**
     * Descripción del comando.
     *
     * @var string
     */
    protected $description = 'Crea y registra automáticamente una nueva plantilla personalizada de documento PDF (heredando o desde cero) en Blade y tb_docs';

    /**
     * Tipos de documento soportados.
     */
    protected array $tiposDisponibles = [
        'contrato_credito' => 'Contrato de Crédito / Mutuo Prendario con Garantía',
        'plan_pagos'       => 'Plan de Pagos y Estado de Cuenta (Amortización)',
        'recibo_pago'      => 'Recibo / Comprobante de Pago de Crédito',
        'contrato_compra'  => 'Contrato de Compraventa de Bien Mueble',
        'recibo_compra'    => 'Recibo / Liquidación de Compraventa',
    ];

    /**
     * Mapeo de tipo de documento a nombre de archivo sugerido.
     */
    protected array $archivosPorTipo = [
        'contrato_credito' => 'contrato',
        'plan_pagos'       => 'plan-pagos',
        'recibo_pago'      => 'recibo',
        'contrato_compra'  => 'contrato-compra',
        'recibo_compra'    => 'recibo-compra',
    ];

    /**
     * Ejecuta el comando.
     */
    public function handle(): int
    {
        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════════════════');
        $this->info('    CREADOR AUTOMÁTICO DE FORMATOS Y DOCUMENTOS PERSONALIZADOS PDF     ');
        $this->info('                 Motor de Documentos - Dev Empeños                      ');
        $this->info('═══════════════════════════════════════════════════════════════════════');
        $this->newLine();

        // 1. Obtener o solicitar slug de la variante
        $slug = $this->argument('slug');
        if (empty($slug)) {
            $slug = $this->ask('1. Ingresa el identificador / slug de la variante (ej: prendafacil, quetzal_empenios, vip)');
        }
        $slug = Str::slug($slug, '_');

        if (empty($slug)) {
            $this->error('❌ El identificador/slug no puede estar vacío.');
            return Command::FAILURE;
        }

        // 2. Obtener o solicitar tipo de documento
        $tipo = $this->option('tipo');
        if (empty($tipo) || !array_key_exists($tipo, $this->tiposDisponibles)) {
            $opcionesTipo = [];
            foreach ($this->tiposDisponibles as $k => $desc) {
                $opcionesTipo[$k] = "{$k} ({$desc})";
            }
            $seleccionTipo = $this->choice(
                '2. Selecciona el tipo de documento a generar',
                array_values($opcionesTipo),
                0
            );
            $tipo = array_search($seleccionTipo, $opcionesTipo, true);
        }

        // 3. Determinar modo: heredar o desde cero
        $heredar = $this->option('heredar');
        $desdeCero = (bool) $this->option('desde-cero');

        if (empty($heredar) && !$desdeCero) {
            $opcionesModo = [
                'heredar_prendamas' => 'Heredar de PRENDAMÁS (Diseño corporativo completo con carátula / notarial)',
                'heredar_estandar'  => 'Heredar de ESTÁNDAR (Diseño base limpio y formal)',
                'desde_cero'        => 'Crear DESDE CERO (Scaffold limpio con logo, datos y tablas listos para maquetar)',
            ];
            $seleccionModo = $this->choice(
                '3. ¿Cómo deseas inicializar el diseño de la plantilla?',
                array_values($opcionesModo),
                0
            );
            $claveModo = array_search($seleccionModo, $opcionesModo, true);

            if ($claveModo === 'heredar_prendamas') {
                $heredar = 'prendamas';
            } elseif ($claveModo === 'heredar_estandar') {
                $heredar = 'estandar';
            } else {
                $desdeCero = true;
            }
        }

        // 4. Parámetros de organización y sucursal
        $orgCode = $this->option('org') ?: ConfiguracionSistema::obtener('organizacion_code') ?: env('ORGANIZATION_CODE', '01');
        $sucursalId = $this->option('sucursal') ? (int) $this->option('sucursal') : null;

        // 5. Nombre amigable
        $nombreDoc = $this->option('nombre');
        if (empty($nombreDoc)) {
            $nombreMarca = ucwords(str_replace('_', ' ', $slug));
            $nombreTipo = $this->tiposDisponibles[$tipo];
            $nombreDoc = "{$nombreTipo} ({$nombreMarca})";
        }

        $activo = !$this->option('desactivar');

        // 6. Rutas de archivo Blade
        $nombreArchivo = $this->archivosPorTipo[$tipo] ?? 'documento';
        $directorioDestino = resource_path("views/pdf/custom/{$slug}");
        $archivoDestino = "{$directorioDestino}/{$nombreArchivo}.blade.php";
        $vistaPdf = "pdf.custom.{$slug}.{$nombreArchivo}";

        $this->line("📁 Directorio de vista: <fg=cyan>{$directorioDestino}</>");
        $this->line("📄 Archivo de vista:   <fg=cyan>{$archivoDestino}</>");
        $this->line("🏷️  Vista Blade:        <fg=green>{$vistaPdf}</>");
        $this->newLine();

        // 7. Validar existencia previa de archivo
        if (File::exists($archivoDestino) && !$this->option('force')) {
            $sobreescribir = $this->confirm(
                "El archivo [{$archivoDestino}] ya existe. ¿Deseas sobrescribirlo?",
                false
            );
            if (!$sobreescribir) {
                $this->warn('Operación cancelada. El archivo no fue modificado.');
                return Command::SUCCESS;
            }
        }

        // 8. Crear directorio si no existe
        if (!File::isDirectory($directorioDestino)) {
            File::makeDirectory($directorioDestino, 0755, true);
        }

        // 9. Generar contenido de la vista
        $contenidoVista = '';

        if (!empty($heredar)) {
            $rutaOrigen = $this->resolverRutaOrigenHeredada($heredar, $tipo, $nombreArchivo);

            if ($rutaOrigen && File::exists($rutaOrigen)) {
                $this->info("🔄 Heredando contenido desde: {$rutaOrigen}");
                $contenidoVista = File::get($rutaOrigen);
            } else {
                $this->warn("⚠️  No se encontró la plantilla origen [{$heredar}] para [{$tipo}]. Generando desde cero como respaldo...");
                $contenidoVista = $this->generarScaffoldDesdeCero($tipo, $slug, $nombreDoc);
            }
        } else {
            $this->info("✨ Generando plantilla profesional desde cero...");
            $contenidoVista = $this->generarScaffoldDesdeCero($tipo, $slug, $nombreDoc);
        }

        File::put($archivoDestino, $contenidoVista);
        $this->info("✓ Vista Blade guardada exitosamente.");

        // 10. Registrar o actualizar en tb_docs
        $this->info("🗄️  Registrando en tabla tb_docs...");

        $empresaNombre = ConfiguracionSistema::obtener('empresa_nombre') ?: env('APP_NAME', 'SISTEMA DE EMPEÑOS');
        $repNombre = ConfiguracionSistema::obtener('representante_legal_nombre') ?: 'REPRESENTANTE LEGAL';
        $repTitulo = ConfiguracionSistema::obtener('representante_legal_titulo') ?: 'ADMINISTRADOR ÚNICO';

        $doc = TbDoc::updateOrCreate(
            [
                'organizacion_code' => $orgCode,
                'sucursal_id'       => $sucursalId,
                'tipo_documento'    => $tipo,
                'plantilla_variante'=> $slug,
            ],
            [
                'nombre_documento'        => $nombreDoc,
                'vista_pdf'               => $vistaPdf,
                'titulo_personalizado'    => strtoupper($nombreDoc),
                'subtitulo_personalizado' => $empresaNombre,
                'encabezado_texto'        => 'Documento oficial con validez legal emitido por el sistema.',
                'pie_pagina_texto'        => "{$empresaNombre} - Tu patrimonio en buenas manos",
                'firmante_1_nombre'       => 'CLIENTE / DEUDOR',
                'firmante_1_titulo'       => 'FIRMA DE CONFORMIDAD',
                'firmante_2_nombre'       => $repNombre,
                'firmante_2_titulo'       => $repTitulo,
                'mostrar_logo'            => true,
                'mostrar_firmas'          => true,
                'activo'                  => $activo,
            ]
        );

        // Si se activó, desactivar otras plantillas del mismo tipo en este ámbito para evitar conflictos
        if ($activo) {
            $desactivados = TbDoc::where('organizacion_code', $orgCode)
                ->where('tipo_documento', $tipo)
                ->when($sucursalId, fn($q) => $q->where('sucursal_id', $sucursalId), fn($q) => $q->whereNull('sucursal_id'))
                ->where('id', '!=', $doc->id)
                ->update(['activo' => false]);

            $this->info("✓ Se desactivaron {$desactivados} variantes anteriores para asegurar prioridad a '{$slug}'.");
        }

        $this->newLine();
        $this->info('═══════════════════════════════════════════════════════════════════════');
        $this->info('✅ FORMATO PERSONALIZADO CREADO Y ACTIVADO EXITOSAMENTE');
        $this->info('═══════════════════════════════════════════════════════════════════════');
        $this->line("   • ID tb_docs:       <fg=green>{$doc->id}</>");
        $this->line("   • Tipo Documento:   <fg=yellow>{$tipo}</>");
        $this->line("   • Variante (Slug):  <fg=cyan>{$slug}</>");
        $this->line("   • Vista Blade:      <fg=cyan>{$vistaPdf}</>");
        $this->line("   • Archivo físico:   <fg=white>{$archivoDestino}</>");
        $this->line("   • Estado Activo:    " . ($activo ? '<fg=green>SÍ (Predeterminado)</>' : '<fg=red>NO</>'));
        $this->newLine();

        $this->comment('💡 COMANDOS Y PRUEBAS RÁPIDAS:');
        $this->line("   Para editar el diseño, abre el archivo Blade:");
        $this->line("   > <fg=yellow>{$archivoDestino}</>");
        $this->newLine();
        $this->line("   Para limpiar caché de vistas y configuraciones:");
        $this->line("   > <fg=cyan>php artisan view:clear && php artisan optimize:clear</>");
        $this->newLine();

        return Command::SUCCESS;
    }

    /**
     * Resuelve la ruta del archivo Blade a heredar.
     */
    protected function resolverRutaOrigenHeredada(string $heredar, string $tipo, string $nombreArchivo): ?string
    {
        // 1. Si hereda de prendamas
        if ($heredar === 'prendamas') {
            if ($tipo === 'contrato_credito') {
                return resource_path('views/pdf/custom/prendamas/contrato.blade.php');
            }
            if ($tipo === 'plan_pagos') {
                return resource_path('views/pdf/custom/prendamas/plan-pagos.blade.php');
            }
            if ($tipo === 'recibo_pago') {
                return resource_path('views/creditos/recibo.blade.php');
            }
        }

        // 2. Si hereda de estandar
        if ($heredar === 'estandar') {
            if ($tipo === 'contrato_credito') {
                return resource_path('views/creditos/contrato.blade.php');
            }
            if ($tipo === 'plan_pagos') {
                return resource_path('views/creditos/plan-pagos.blade.php');
            }
            if ($tipo === 'recibo_pago') {
                return resource_path('views/creditos/recibo.blade.php');
            }
            if ($tipo === 'contrato_compra') {
                return resource_path('views/pdf/contrato-compra.blade.php');
            }
            if ($tipo === 'recibo_compra') {
                return resource_path('views/pdf/recibo-compra.blade.php');
            }
        }

        // 3. Si hereda de otra variante personalizada en resources/views/pdf/custom/{heredar}
        $rutaCustom = resource_path("views/pdf/custom/{$heredar}/{$nombreArchivo}.blade.php");
        if (File::exists($rutaCustom)) {
            return $rutaCustom;
        }

        return null;
    }

    /**
     * Genera el scaffold/código base Blade optimizado para DomPDF desde cero.
     */
    protected function generarScaffoldDesdeCero(string $tipo, string $slug, string $nombreDoc): string
    {
        $nombreMayus = strtoupper($nombreDoc);

        if ($tipo === 'contrato_credito') {
            return <<<BLADE
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ \$empresa['nombre'] ?? 'EMPRESA' }} - {{ \$docConfig->titulo_personalizado ?? 'CONTRATO DE CRÉDITO' }}</title>
    <style>
        @page {
            size: letter portrait;
            margin: 2cm 1.6cm 1.8cm 1.6cm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #111;
            text-align: justify;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #0a2d52;
            padding-bottom: 8px;
            margin-bottom: 15px;
        }
        .header-logo {
            text-align: left;
            vertical-align: middle;
        }
        .header-info {
            text-align: right;
            vertical-align: middle;
            font-size: 8.5pt;
            color: #333;
        }
        .header-title {
            font-size: 14pt;
            font-weight: bold;
            color: #0a2d52;
            text-transform: uppercase;
            margin: 0 0 4px 0;
        }
        .titulo-doc {
            text-align: center;
            font-size: 13pt;
            font-weight: bold;
            color: #0a2d52;
            text-transform: uppercase;
            margin: 10px 0 15px 0;
            letter-spacing: 0.5px;
        }
        p {
            margin: 0 0 10px 0;
            text-align: justify;
        }
        .tabla-datos {
            width: 100%;
            border-collapse: collapse;
            margin: 12px 0;
            border: 1px solid #0a2d52;
        }
        .tabla-datos th {
            background-color: #0a2d52;
            color: #ffffff;
            font-weight: bold;
            padding: 6px 8px;
            font-size: 9pt;
            text-transform: uppercase;
        }
        .tabla-datos td {
            padding: 6px 8px;
            border: 1px solid #ccc;
            font-size: 9pt;
        }
        .firmas-tabla {
            width: 100%;
            border-collapse: collapse;
            margin-top: 40px;
            page-break-inside: avoid;
        }
        .firmas-tabla td {
            width: 50%;
            text-align: center;
            vertical-align: top;
            padding: 0 15px;
        }
        .linea-firma {
            border-top: 1.5px solid #000;
            width: 80%;
            margin: 0 auto 6px auto;
            padding-top: 4px;
            font-weight: bold;
            font-size: 9.5pt;
            text-transform: uppercase;
        }
        .huella-box {
            display: inline-block;
            width: 50px;
            height: 65px;
            border: 1px dashed #666;
            margin-top: 6px;
            font-size: 7.5pt;
            color: #666;
            line-height: 65px;
            text-align: center;
        }
    </style>
</head>
<body>

    {{-- ENCABEZADO CON LOGO INTELIGENTE Y DATOS INSTITUCIONALES --}}
    <table class="header-table">
        <tr>
            <td class="header-logo" width="50%">
                @include('pdf.partials.logo', ['height' => '60px'])
            </td>
            <td class="header-info" width="50%">
                <div class="header-title">{{ \$empresa['nombre'] ?? 'PRENDAMÁS+' }}</div>
                <div>{{ \$empresa['razon_social'] ?? '' }}</div>
                <div>NIT: {{ \$empresa['nit'] ?? 'C/F' }} | Tel: {{ \$empresa['telefono'] ?? '' }}</div>
                <div>{{ \$sucursal->direccion ?? \$empresa['direccion'] ?? '' }}</div>
            </td>
        </tr>
    </table>

    <h1 class="titulo-doc">{{ \$docConfig->titulo_personalizado ?? 'CONTRATO DE MUTUO CON GARANTÍA PRENDARIA' }}</h1>

    <p>
        En el municipio de <strong>{{ \$sucursal->municipio ?? 'Quetzaltenango' }}</strong>, comparecen por una parte
        <strong>{{ \$docConfig->firmante_2_nombre ?: (\$empresa['representante_legal'] ?? 'REPRESENTANTE LEGAL') }}</strong>, en su calidad de
        <strong>{{ \$docConfig->firmante_2_titulo ?: 'REPRESENTANTE LEGAL' }}</strong> de la entidad <strong>{{ \$empresa['razon_social'] ?? \$empresa['nombre'] }}</strong>,
        y por la otra parte el(la) señor(a) <strong>{{ \$cliente->nombres ?? '' }} {{ \$cliente->apellidos ?? '' }}</strong>,
        quien se identifica con DPI <strong>{{ \$cliente->dpi ?? \$cliente->numero_documento ?? 'CF' }}</strong>,
        con domicilio en <strong>{{ \$cliente->direccion ?? 'Guatemala' }}</strong>, celebramos el presente contrato sujeto a las siguientes cláusulas:
    </p>

    <p>
        <strong>PRIMERA: DEL CRÉDITO Y MONTO DESEMBOLSADO.</strong> La parte acreedora concede a la parte deudora un crédito
        prendario por la cantidad convenida de <strong>Q {{ number_format(\$credito->monto_aprobado ?? 0, 2) }}</strong>,
        identificado bajo el crédito número <strong>{{ \$credito->codigo_credito ?? \$credito->numero_credito ?? 'S/N' }}</strong>,
        con una tasa de interés mensual del <strong>{{ number_format(\$credito->tasa_interes ?? 15, 2) }}%</strong>.
    </p>

    <p>
        <strong>SEGUNDA: DE LA PRENDA Y GARANTÍA.</strong> En garantía del cumplimiento del pago del capital e intereses,
        la parte deudora hace entrega material en depósito prendario de los siguientes bienes muebles:
    </p>

    <table class="tabla-datos">
        <thead>
            <tr>
                <th width="10%">No.</th>
                <th width="65%">Descripción de la Prenda</th>
                <th width="25%" align="right">Valor Tasación</th>
            </tr>
        </thead>
        <tbody>
            @forelse(\$prendas ?? [] as \$idx => \$p)
                <tr>
                    <td align="center">{{ \$loop->iteration }}</td>
                    <td>
                        <strong>{{ \$p->descripcion ?? \$p->descripcion_general ?? 'Bien Mueble' }}</strong>
                        @if(!empty(\$p->marca)) | Marca: {{ \$p->marca }} @endif
                        @if(!empty(\$p->modelo)) | Modelo: {{ \$p->modelo }} @endif
                        @if(!empty(\$p->serie)) | Serie: {{ \$p->serie }} @endif
                    </td>
                    <td align="right">Q {{ number_format(\$p->valor_tasacion ?? 0, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td align="center">1</td>
                    <td>Prenda prendaria en depósito legal según inventario</td>
                    <td align="right">Q {{ number_format(\$credito->monto_aprobado ?? 0, 2) }}</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <p>
        <strong>TERCERA: PLAZO, DÍAS DE GRACIA Y ENAJENACIÓN.</strong> El deudor se compromete a amortizar o cancelar
        su saldo antes de la fecha de vencimiento estipulada para el <strong>{{ !empty(\$credito->fecha_vencimiento) ? \Carbon\Carbon::parse(\$credito->fecha_vencimiento)->format('d/m/Y') : now()->addDays(30)->format('d/m/Y') }}</strong>.
        Cuenta con un período de gracia de <strong>{{ \$credito->dias_gracia ?? 4 }} días</strong> antes de devengar recargos por mora.
    </p>

    {{-- TABLA DE FIRMAS --}}
    <table class="firmas-tabla">
        <tr>
            <td>
                <div class="linea-firma">{{ \$cliente->nombres ?? '' }} {{ \$cliente->apellidos ?? '' }}</div>
                <div>DEUDOR(A) / CLIENTE</div>
                <div>DPI: {{ \$cliente->dpi ?? \$cliente->numero_documento ?? 'CF' }}</div>
                <div class="huella-box">Huella</div>
            </td>
            <td>
                <div class="linea-firma">{{ \$docConfig->firmante_2_nombre ?: (\$empresa['representante_legal'] ?? 'REPRESENTANTE LEGAL') }}</div>
                <div>{{ \$docConfig->firmante_2_titulo ?: 'REPRESENTANTE LEGAL' }}</div>
                <div>{{ \$empresa['nombre'] ?? '' }}</div>
            </td>
        </tr>
    </table>

</body>
</html>
BLADE;
        }

        if ($tipo === 'plan_pagos') {
            return <<<BLADE
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ \$empresa['nombre'] ?? 'EMPRESA' }} - Plan de Pagos {{ \$credito->codigo_credito ?? \$credito->numero_credito ?? 'S/N' }}</title>
    <style>
        @page {
            size: letter portrait;
            margin: 1.8cm 1.5cm;
        }
        * { box-sizing: border-box; }
        body {
            font-family: Arial, Helvetica, sans-serif;
            font-size: 9.5pt;
            line-height: 1.35;
            color: #111;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-bottom: 2px solid #0a2d52;
            padding-bottom: 8px;
            margin-bottom: 12px;
        }
        .brand-title {
            font-size: 15pt;
            font-weight: bold;
            color: #0a2d52;
            text-transform: uppercase;
        }
        .brand-sub {
            font-size: 8.5pt;
            color: #555;
        }
        .doc-title {
            text-align: center;
            font-size: 13pt;
            font-weight: bold;
            color: #0a2d52;
            text-transform: uppercase;
            margin: 10px 0 12px 0;
        }
        .info-grid {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
            font-size: 9pt;
        }
        .info-grid td {
            vertical-align: top;
            padding: 4px 6px;
            border: none;
        }
        .label {
            font-size: 8pt;
            font-weight: bold;
            color: #666;
            text-transform: uppercase;
            display: block;
        }
        .val {
            font-size: 9.5pt;
            font-weight: bold;
            color: #111;
        }
        .tabla-cuotas {
            width: 100%;
            border-collapse: collapse;
            margin-top: 6px;
            font-size: 8.5pt;
        }
        .tabla-cuotas th {
            background-color: #0a2d52;
            color: #ffffff;
            font-weight: bold;
            padding: 6px;
            border: 1px solid #0a2d52;
            text-align: right;
            text-transform: uppercase;
        }
        .tabla-cuotas th.text-center { text-align: center; }
        .tabla-cuotas th.text-left { text-align: left; }
        .tabla-cuotas td {
            padding: 5px 6px;
            border: 1px solid #ddd;
        }
        .total-row {
            background-color: #f1f5f9;
            font-weight: bold;
        }
        .firmas-box {
            margin-top: 35px;
            width: 100%;
            page-break-inside: avoid;
        }
        .firmas-box td {
            width: 50%;
            text-align: center;
            vertical-align: top;
        }
        .linea {
            border-top: 1.5px solid #000;
            width: 80%;
            margin: 0 auto 4px auto;
            padding-top: 4px;
            font-weight: bold;
            font-size: 9pt;
        }
    </style>
</head>
<body>

    <table class="header-table">
        <tr>
            <td width="40%">
                @include('pdf.partials.logo', ['height' => '55px'])
            </td>
            <td width="60%" align="right">
                <div class="brand-title">{{ \$empresa['nombre'] ?? 'PRENDAMÁS+' }}</div>
                <div class="brand-sub">{{ \$sucursal->direccion ?? \$empresa['direccion'] ?? '' }} | Tel: {{ \$sucursal->telefono ?? \$empresa['telefono'] ?? '' }}</div>
            </td>
        </tr>
    </table>

    <h2 class="doc-title">{{ \$docConfig->titulo_personalizado ?? 'ESTADO DE CUENTA Y PLAN DE PAGOS' }}</h2>

    <table class="info-grid">
        <tr>
            <td width="25%">
                <span class="label">Crédito No.</span>
                <span class="val">{{ \$credito->codigo_credito ?? \$credito->numero_credito ?? 'S/N' }}</span>
            </td>
            <td width="35%">
                <span class="label">Cliente</span>
                <span class="val">{{ \$cliente->nombres ?? '' }} {{ \$cliente->apellidos ?? '' }}</span>
            </td>
            <td width="20%">
                <span class="label">Monto Aprobado</span>
                <span class="val" style="color: #0a2d52;">Q {{ number_format(\$credito->monto_aprobado ?? 0, 2) }}</span>
            </td>
            <td width="20%">
                <span class="label">Tasa Interés</span>
                <span class="val">{{ number_format(\$credito->tasa_interes ?? 15, 2) }}%</span>
            </td>
        </tr>
        <tr>
            <td>
                <span class="label">Frecuencia</span>
                <span class="val">{{ strtoupper(\$credito->tipo_interes ?? 'MENSUAL') }}</span>
            </td>
            <td>
                <span class="label">Documento DPI</span>
                <span class="val">{{ \$cliente->dpi ?? \$cliente->numero_documento ?? 'CF' }}</span>
            </td>
            <td>
                <span class="label">Cuotas</span>
                <span class="val">{{ collect(\$planPagos ?? [])->count() }}</span>
            </td>
            <td>
                <span class="label">Desembolso</span>
                <span class="val">{{ !empty(\$credito->fecha_desembolso) ? \Carbon\Carbon::parse(\$credito->fecha_desembolso)->format('d/m/Y') : now()->format('d/m/Y') }}</span>
            </td>
        </tr>
    </table>

    {{-- TABLA DE CUOTAS --}}
    <table class="tabla-cuotas">
        <thead>
            <tr>
                <th class="text-center" width="8%">No.</th>
                <th class="text-center" width="16%">Vencimiento</th>
                <th width="15%">Capital</th>
                <th width="15%">Interés</th>
                <th width="15%">Otros</th>
                <th width="16%">Total Cuota</th>
                <th width="15%">Saldo Capital</th>
            </tr>
        </thead>
        <tbody>
            @php
                \$totCap = 0;
                \$totInt = 0;
                \$totOtros = 0;
                \$totGen = 0;
            @endphp
            @forelse(\$planPagos ?? [] as \$cuota)
                @php
                    \$cap = (float) (\$cuota->capital_proyectado ?? \$cuota->capital_pendiente ?? 0);
                    \$int = (float) (\$cuota->interes_proyectado ?? \$cuota->interes_pendiente ?? 0);
                    \$otr = (float) (\$cuota->otros_cargos_proyectados ?? \$cuota->otros_proyectados ?? 0);
                    \$tot = (float) (\$cuota->monto_cuota_proyectado ?? (\$cap + \$int + \$otr));
                    \$saldo = (float) (\$cuota->saldo_capital_credito ?? 0);

                    \$totCap += \$cap;
                    \$totInt += \$int;
                    \$totOtros += \$otr;
                    \$totGen += \$tot;
                @endphp
                <tr>
                    <td align="center">{{ \$cuota->numero_cuota ?? \$loop->iteration }}</td>
                    <td align="center">{{ !empty(\$cuota->fecha_vencimiento) ? \Carbon\Carbon::parse(\$cuota->fecha_vencimiento)->format('d/m/Y') : '-' }}</td>
                    <td align="right">Q {{ number_format(\$cap, 2) }}</td>
                    <td align="right">Q {{ number_format(\$int, 2) }}</td>
                    <td align="right">Q {{ number_format(\$otr, 2) }}</td>
                    <td align="right" style="font-weight: bold; color: #0a2d52;">Q {{ number_format(\$tot, 2) }}</td>
                    <td align="right">Q {{ number_format(\$saldo, 2) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" align="center">No hay cuotas proyectadas para este crédito.</td>
                </tr>
            @endforelse
            <tr class="total-row">
                <td colspan="2" align="right">TOTALES:</td>
                <td align="right">Q {{ number_format(\$totCap, 2) }}</td>
                <td align="right">Q {{ number_format(\$totInt, 2) }}</td>
                <td align="right">Q {{ number_format(\$totOtros, 2) }}</td>
                <td align="right" style="color: #0a2d52;">Q {{ number_format(\$totGen, 2) }}</td>
                <td align="right">Q 0.00</td>
            </tr>
        </tbody>
    </table>

    {{-- FIRMAS --}}
    <table class="firmas-box">
        <tr>
            <td>
                <div class="linea">RECIBÍ CONFORME (CLIENTE)</div>
                <div>{{ \$cliente->nombres ?? '' }} {{ \$cliente->apellidos ?? '' }}</div>
            </td>
            <td>
                <div class="linea">{{ \$docConfig->firmante_2_nombre ?: (\$empresa['nombre'] ?? 'ADMINISTRACIÓN') }}</div>
                <div>AUTORIZADO / POR LA EMPRESA</div>
            </td>
        </tr>
    </table>

</body>
</html>
BLADE;
        }

        // Scaffold genérico por defecto
        return <<<BLADE
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>{{ \$empresa['nombre'] ?? 'DOCUMENTO' }} - {{ \$docConfig->titulo_personalizado ?? '{$nombreMayus}' }}</title>
    <style>
        @page { size: letter portrait; margin: 2cm 1.5cm; }
        body { font-family: Arial, sans-serif; font-size: 10pt; color: #111; line-height: 1.4; }
        .header { width: 100%; border-bottom: 2px solid #0a2d52; padding-bottom: 8px; margin-bottom: 15px; }
        .title { text-align: center; font-size: 14pt; font-weight: bold; color: #0a2d52; margin: 15px 0; }
    </style>
</head>
<body>
    <table class="header">
        <tr>
            <td width="50%">@include('pdf.partials.logo', ['height' => '60px'])</td>
            <td width="50%" align="right">
                <div style="font-weight: bold; font-size: 13pt;">{{ \$empresa['nombre'] ?? '' }}</div>
                <div>{{ \$empresa['direccion'] ?? '' }} | Tel: {{ \$empresa['telefono'] ?? '' }}</div>
            </td>
        </tr>
    </table>

    <h1 class="title">{{ \$docConfig->titulo_personalizado ?? '{$nombreMayus}' }}</h1>

    <div style="margin-top: 20px;">
        <p>Documento oficial emitido con fecha {{ now()->format('d/m/Y H:i:s') }}.</p>
    </div>
</body>
</html>
BLADE;
    }
}
