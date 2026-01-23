#!/usr/bin/env php
<?php

/**
 * Script de diagnóstico para el sistema de empeños
 * Verifica conectividad, rendimiento y detecta problemas comunes
 *
 * Uso: php diagnostico.php
 */

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║   DIAGNÓSTICO - SISTEMA DE EMPEÑOS (Backend Laravel)        ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

$startTime = microtime(true);
$errors = [];
$warnings = [];
$success = [];

// Cargar Laravel
require __DIR__.'/vendor/autoload.php';

try {
    $app = require_once __DIR__.'/bootstrap/app.php';
    $kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
    $kernel->bootstrap();
} catch (Exception $e) {
    die("❌ ERROR: No se pudo cargar Laravel: " . $e->getMessage() . "\n");
}

echo "🔍 Iniciando diagnóstico...\n\n";

// ============================================
// 1. VERIFICAR CONEXIÓN A BASE DE DATOS
// ============================================
echo "📊 [1/8] Verificando conexión a base de datos...\n";
try {
    DB::connection()->getPdo();
    $dbName = DB::connection()->getDatabaseName();
    $success[] = "✅ Conectado a MySQL: {$dbName}";
    echo "   ✅ Conectado a MySQL: {$dbName}\n";

    // Verificar si hay tablas
    $tables = DB::select('SHOW TABLES');
    if (count($tables) > 0) {
        $success[] = "✅ Base de datos contiene " . count($tables) . " tablas";
        echo "   ✅ Base de datos contiene " . count($tables) . " tablas\n";
    } else {
        $warnings[] = "⚠️  Base de datos vacía - ejecuta las migraciones";
        echo "   ⚠️  Base de datos vacía - ejecuta: php artisan migrate\n";
    }
} catch (Exception $e) {
    $errors[] = "❌ No se pudo conectar a MySQL: " . $e->getMessage();
    echo "   ❌ ERROR: " . $e->getMessage() . "\n";
    echo "   💡 Verifica que XAMPP esté corriendo y .env tenga las credenciales correctas\n";
}

echo "\n";

// ============================================
// 2. VERIFICAR TABLAS CRÍTICAS
// ============================================
echo "📋 [2/8] Verificando tablas críticas...\n";
$tablasRequeridas = [
    'users',
    'clientes',
    'creditos_prendarios',
    'prendas',
    'sucursales'
];

foreach ($tablasRequeridas as $tabla) {
    try {
        $exists = DB::select("SHOW TABLES LIKE '{$tabla}'");
        if (!empty($exists)) {
            $count = DB::table($tabla)->count();
            echo "   ✅ {$tabla}: {$count} registros\n";
            $success[] = "✅ Tabla {$tabla} existe con {$count} registros";
        } else {
            $errors[] = "❌ Tabla {$tabla} no existe";
            echo "   ❌ Tabla {$tabla} NO EXISTE\n";
        }
    } catch (Exception $e) {
        $errors[] = "❌ Error verificando tabla {$tabla}: " . $e->getMessage();
        echo "   ❌ Error: " . $e->getMessage() . "\n";
    }
}

echo "\n";

// ============================================
// 3. VERIFICAR ÍNDICES DE RENDIMIENTO
// ============================================
echo "⚡ [3/8] Verificando índices de rendimiento...\n";
$indicesRequeridos = [
    'clientes' => ['idx_clientes_eliminado_estado', 'idx_clientes_busqueda'],
    'creditos_prendarios' => ['idx_creditos_estado', 'idx_creditos_busqueda'],
];

foreach ($indicesRequeridos as $tabla => $indices) {
    try {
        foreach ($indices as $indice) {
            $exists = DB::select("SHOW INDEX FROM `{$tabla}` WHERE Key_name = '{$indice}'");
            if (!empty($exists)) {
                echo "   ✅ Índice {$indice} en {$tabla}\n";
                $success[] = "✅ Índice {$indice} existe";
            } else {
                $warnings[] = "⚠️  Falta índice {$indice} en {$tabla}";
                echo "   ⚠️  FALTA: {$indice} en {$tabla}\n";
            }
        }
    } catch (Exception $e) {
        // Tabla no existe, ya lo reportamos antes
    }
}

if (count($warnings) > 0 && strpos($warnings[count($warnings)-1], 'Falta índice') !== false) {
    echo "\n   💡 Para crear índices: php artisan migrate\n";
}

echo "\n";

// ============================================
// 4. TEST DE RENDIMIENTO - CONSULTAS CRÍTICAS
// ============================================
echo "🚀 [4/8] Test de rendimiento de consultas críticas...\n";

// Test 1: Listar clientes
try {
    $start = microtime(true);
    DB::table('clientes')->where('eliminado', false)->limit(10)->get();
    $duration = round((microtime(true) - $start) * 1000, 2);

    if ($duration < 500) {
        echo "   ✅ Clientes (10 registros): {$duration}ms - EXCELENTE\n";
        $success[] = "✅ Query clientes: {$duration}ms";
    } elseif ($duration < 2000) {
        echo "   ⚠️  Clientes (10 registros): {$duration}ms - ACEPTABLE\n";
        $warnings[] = "⚠️  Query clientes lenta: {$duration}ms";
    } else {
        echo "   ❌ Clientes (10 registros): {$duration}ms - MUY LENTO\n";
        $errors[] = "❌ Query clientes muy lenta: {$duration}ms - Necesita índices";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test 2: Estadísticas de clientes (la consulta optimizada)
try {
    $start = microtime(true);
    DB::table('clientes')
        ->where('eliminado', false)
        ->selectRaw('
            COUNT(*) as total,
            SUM(CASE WHEN estado = "activo" THEN 1 ELSE 0 END) as activos
        ')
        ->first();
    $duration = round((microtime(true) - $start) * 1000, 2);

    if ($duration < 1000) {
        echo "   ✅ Estadísticas clientes: {$duration}ms - EXCELENTE\n";
        $success[] = "✅ Estadísticas optimizadas: {$duration}ms";
    } else {
        echo "   ⚠️  Estadísticas clientes: {$duration}ms - Considera índices\n";
        $warnings[] = "⚠️  Estadísticas lentas: {$duration}ms";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

// Test 3: Créditos prendarios
try {
    $start = microtime(true);
    DB::table('creditos_prendarios')->limit(10)->get();
    $duration = round((microtime(true) - $start) * 1000, 2);

    if ($duration < 500) {
        echo "   ✅ Créditos (10 registros): {$duration}ms - EXCELENTE\n";
        $success[] = "✅ Query créditos: {$duration}ms";
    } else {
        echo "   ⚠️  Créditos (10 registros): {$duration}ms\n";
        $warnings[] = "⚠️  Query créditos: {$duration}ms";
    }
} catch (Exception $e) {
    echo "   ❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// ============================================
// 5. VERIFICAR CONFIGURACIÓN PHP
// ============================================
echo "⚙️  [5/8] Verificando configuración PHP...\n";

$maxExecution = ini_get('max_execution_time');
$memoryLimit = ini_get('memory_limit');
$uploadMax = ini_get('upload_max_filesize');

echo "   • max_execution_time: {$maxExecution}s";
if ($maxExecution < 60) {
    echo " ⚠️  (Recomendado: 120s)\n";
    $warnings[] = "⚠️  max_execution_time bajo: {$maxExecution}s";
} else {
    echo " ✅\n";
}

echo "   • memory_limit: {$memoryLimit}";
if (intval($memoryLimit) < 128) {
    echo " ⚠️  (Recomendado: 256M)\n";
    $warnings[] = "⚠️  memory_limit bajo: {$memoryLimit}";
} else {
    echo " ✅\n";
}

echo "   • upload_max_filesize: {$uploadMax} ✅\n";
echo "   • PHP Version: " . PHP_VERSION . " ✅\n";

echo "\n";

// ============================================
// 6. VERIFICAR RUTAS API
// ============================================
echo "🛣️  [6/8] Verificando rutas API críticas...\n";

$rutasCriticas = [
    '/api/v1/auth/login',
    '/api/v1/clientes',
    '/api/v1/creditos-prendarios',
    '/api/v1/BD'
];

foreach ($rutasCriticas as $ruta) {
    try {
        $routes = app('router')->getRoutes();
        $found = false;
        foreach ($routes as $route) {
            if (str_contains($route->uri(), ltrim($ruta, '/'))) {
                $found = true;
                break;
            }
        }

        if ($found) {
            echo "   ✅ {$ruta}\n";
        } else {
            echo "   ⚠️  {$ruta} no encontrada\n";
            $warnings[] = "⚠️  Ruta {$ruta} no encontrada";
        }
    } catch (Exception $e) {
        echo "   ❌ Error verificando rutas\n";
    }
}

echo "\n";

// ============================================
// 7. VERIFICAR LOGS DE ERRORES RECIENTES
// ============================================
echo "📝 [7/8] Verificando logs de errores recientes...\n";

$logFile = storage_path('logs/laravel.log');
if (file_exists($logFile)) {
    $logSize = filesize($logFile);
    $logSizeMB = round($logSize / 1024 / 1024, 2);

    echo "   • Tamaño del log: {$logSizeMB}MB\n";

    if ($logSizeMB > 50) {
        echo "   ⚠️  Log muy grande - considera rotarlo\n";
        $warnings[] = "⚠️  Log muy grande: {$logSizeMB}MB";
    }

    // Leer últimas 100 líneas
    $handle = fopen($logFile, 'r');
    if ($handle) {
        fseek($handle, -min(50000, $logSize), SEEK_END);
        $content = fread($handle, 50000);
        fclose($handle);

        $errorCount = substr_count($content, '[error]');
        $criticalCount = substr_count($content, '[critical]');

        if ($errorCount > 0 || $criticalCount > 0) {
            echo "   ⚠️  Errores recientes: {$errorCount} errors, {$criticalCount} critical\n";
            $warnings[] = "⚠️  Log contiene {$errorCount} errors recientes";
        } else {
            echo "   ✅ No hay errores críticos recientes\n";
        }
    }
} else {
    echo "   ℹ️  No hay archivo de log (normal en instalación nueva)\n";
}

echo "\n";

// ============================================
// 8. VERIFICAR PERMISOS DE STORAGE
// ============================================
echo "🔐 [8/8] Verificando permisos de storage...\n";

$storageDirs = [
    storage_path('logs'),
    storage_path('framework/cache'),
    storage_path('framework/sessions'),
    storage_path('framework/views'),
];

foreach ($storageDirs as $dir) {
    if (is_writable($dir)) {
        echo "   ✅ " . basename(dirname($dir)) . "/" . basename($dir) . " - escribible\n";
    } else {
        echo "   ❌ " . basename(dirname($dir)) . "/" . basename($dir) . " - NO escribible\n";
        $errors[] = "❌ Directorio no escribible: {$dir}";
    }
}

// ============================================
// RESUMEN FINAL
// ============================================
$totalTime = round((microtime(true) - $startTime) * 1000, 2);

echo "\n";
echo "╔══════════════════════════════════════════════════════════════╗\n";
echo "║                    RESUMEN DEL DIAGNÓSTICO                   ║\n";
echo "╚══════════════════════════════════════════════════════════════╝\n";
echo "\n";

echo "✅ Éxitos: " . count($success) . "\n";
echo "⚠️  Advertencias: " . count($warnings) . "\n";
echo "❌ Errores: " . count($errors) . "\n";
echo "\n";

if (count($errors) > 0) {
    echo "🔴 ERRORES CRÍTICOS:\n";
    foreach ($errors as $error) {
        echo "   " . $error . "\n";
    }
    echo "\n";
}

if (count($warnings) > 0) {
    echo "🟡 ADVERTENCIAS:\n";
    foreach ($warnings as $warning) {
        echo "   " . $warning . "\n";
    }
    echo "\n";
}

echo "⏱️  Tiempo total de diagnóstico: {$totalTime}ms\n";
echo "\n";

// Conclusión
if (count($errors) === 0 && count($warnings) === 0) {
    echo "🎉 ¡SISTEMA EN PERFECTO ESTADO!\n";
    exit(0);
} elseif (count($errors) === 0) {
    echo "✅ Sistema funcional - Hay algunas mejoras recomendadas\n";
    exit(0);
} else {
    echo "❌ HAY PROBLEMAS QUE REQUIEREN ATENCIÓN\n";
    echo "\n";
    echo "📚 PASOS RECOMENDADOS:\n";
    echo "   1. Verifica que XAMPP (Apache + MySQL) esté corriendo\n";
    echo "   2. Revisa las credenciales en .env\n";
    echo "   3. Ejecuta: php artisan migrate\n";
    echo "   4. Ejecuta este diagnóstico nuevamente\n";
    echo "\n";
    exit(1);
}
