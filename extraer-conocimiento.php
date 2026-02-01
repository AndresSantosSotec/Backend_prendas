#!/usr/bin/env php
<?php

/**
 * Script para extraer información del sistema y generar knowledge base
 *
 * Uso: php extraer-conocimiento.php > knowledge-base-generado.json
 */

require __DIR__.'/vendor/autoload.php';

$knowledgeBase = [
    'meta' => [
        'sistema' => 'Digiprenda',
        'version' => '2.1.0',
        'fecha_generacion' => date('Y-m-d H:i:s'),
    ],
    'modulos' => [],
    'validaciones' => [],
    'estados' => [],
    'permisos' => [],
];

echo "🔍 Extrayendo información del sistema Digiprenda...\n\n";

// ========================================
// 1. EXTRAER CONTROLADORES
// ========================================
echo "📂 Analizando controladores...\n";

$controllersPath = __DIR__ . '/app/Http/Controllers/';
$controllers = glob($controllersPath . '*Controller.php');

foreach ($controllers as $controllerFile) {
    $controllerName = basename($controllerFile, '.php');
    $moduleName = str_replace('Controller', '', $controllerName);
    $moduleName = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $moduleName));

    $content = file_get_contents($controllerFile);

    // Extraer funciones públicas
    preg_match_all('/public function (\w+)\(/', $content, $matches);
    $funciones = $matches[1] ?? [];

    // Filtrar funciones comunes de Laravel
    $funciones = array_filter($funciones, function($f) {
        return !in_array($f, ['__construct', 'middleware']);
    });

    if (!empty($funciones)) {
        $knowledgeBase['modulos'][$moduleName] = [
            'controlador' => $controllerName,
            'funciones' => array_values($funciones),
            'archivo' => basename($controllerFile)
        ];
    }
}

echo "✅ " . count($knowledgeBase['modulos']) . " módulos encontrados\n\n";

// ========================================
// 2. EXTRAER MODELOS Y CAMPOS
// ========================================
echo "📊 Analizando modelos...\n";

$modelsPath = __DIR__ . '/app/Models/';
$models = glob($modelsPath . '*.php');

foreach ($models as $modelFile) {
    $modelName = basename($modelFile, '.php');
    $modelNameSnake = strtolower(preg_replace('/([a-z])([A-Z])/', '$1_$2', $modelName));

    $content = file_get_contents($modelFile);

    // Extraer fillable
    if (preg_match('/protected \$fillable = \[(.*?)\];/s', $content, $matches)) {
        $fillableRaw = $matches[1];
        preg_match_all("/'([^']+)'/", $fillableRaw, $fieldsMatches);
        $campos = $fieldsMatches[1] ?? [];

        if (!empty($campos)) {
            if (!isset($knowledgeBase['modulos'][$modelNameSnake])) {
                $knowledgeBase['modulos'][$modelNameSnake] = [];
            }
            $knowledgeBase['modulos'][$modelNameSnake]['campos'] = $campos;
            $knowledgeBase['modulos'][$modelNameSnake]['modelo'] = $modelName;
        }
    }

    // Extraer relaciones
    preg_match_all('/public function (\w+)\(\)\s*\{\s*return \$this->(hasMany|belongsTo|hasOne|belongsToMany)\(([^)]+)\)/',
                   $content, $relationMatches);

    if (!empty($relationMatches[1])) {
        $relaciones = [];
        for ($i = 0; $i < count($relationMatches[1]); $i++) {
            $relaciones[] = [
                'nombre' => $relationMatches[1][$i],
                'tipo' => $relationMatches[2][$i]
            ];
        }

        if (!empty($relaciones)) {
            if (!isset($knowledgeBase['modulos'][$modelNameSnake])) {
                $knowledgeBase['modulos'][$modelNameSnake] = [];
            }
            $knowledgeBase['modulos'][$modelNameSnake]['relaciones'] = $relaciones;
        }
    }
}

echo "✅ " . count($models) . " modelos analizados\n\n";

// ========================================
// 3. EXTRAER ENUMS
// ========================================
echo "🏷️  Analizando enums...\n";

$enumsPath = __DIR__ . '/app/Enums/';
if (is_dir($enumsPath)) {
    $enums = glob($enumsPath . '*.php');

    foreach ($enums as $enumFile) {
        $enumName = basename($enumFile, '.php');
        $content = file_get_contents($enumFile);

        // Extraer casos del enum
        preg_match_all('/case (\w+)\s*=\s*[\'"]([^\'"]+)[\'"];/', $content, $matches);

        if (!empty($matches[1])) {
            $casos = [];
            for ($i = 0; $i < count($matches[1]); $i++) {
                $casos[$matches[2][$i]] = $matches[1][$i];
            }

            $knowledgeBase['estados'][strtolower($enumName)] = $casos;
        }
    }

    echo "✅ " . count($knowledgeBase['estados']) . " enums procesados\n\n";
}

// ========================================
// 4. EXTRAER MIGRACIONES (Validaciones)
// ========================================
echo "📝 Analizando migraciones...\n";

$migrationsPath = __DIR__ . '/database/migrations/';
$migrations = glob($migrationsPath . '*.php');

foreach ($migrations as $migrationFile) {
    $content = file_get_contents($migrationFile);

    // Buscar tabla creada
    if (preg_match('/Schema::create\([\'"](\w+)[\'"]/', $content, $tableMatch)) {
        $tableName = $tableMatch[1];

        // Extraer campos con validaciones
        preg_match_all('/\$table->(\w+)\([\'"](\w+)[\'"](?:,\s*(\d+))?\)(?:->(\w+)\(\))*/',
                       $content, $fieldMatches);

        if (!empty($fieldMatches[2])) {
            $validaciones = [];
            for ($i = 0; $i < count($fieldMatches[2]); $i++) {
                $campo = $fieldMatches[2][$i];
                $tipo = $fieldMatches[1][$i];
                $longitud = $fieldMatches[3][$i] ?? null;
                $constraint = $fieldMatches[4][$i] ?? null;

                $validaciones[$campo] = [
                    'tipo' => $tipo,
                    'longitud' => $longitud,
                    'constraint' => $constraint
                ];
            }

            $knowledgeBase['validaciones'][$tableName] = $validaciones;
        }
    }
}

echo "✅ " . count($knowledgeBase['validaciones']) . " tablas analizadas\n\n";

// ========================================
// 5. EXTRAER RUTAS API
// ========================================
echo "🛣️  Analizando rutas...\n";

$routesFile = __DIR__ . '/routes/api.php';
if (file_exists($routesFile)) {
    $content = file_get_contents($routesFile);

    preg_match_all('/Route::(get|post|put|patch|delete)\([\'"]([^\'"]+)[\'"]/', $content, $routeMatches);

    $rutas = [];
    for ($i = 0; $i < count($routeMatches[2]); $i++) {
        $rutas[] = [
            'metodo' => strtoupper($routeMatches[1][$i]),
            'ruta' => $routeMatches[2][$i]
        ];
    }

    $knowledgeBase['rutas'] = $rutas;
    echo "✅ " . count($rutas) . " rutas encontradas\n\n";
}

// ========================================
// 6. GENERAR JSON
// ========================================
echo "💾 Generando JSON...\n\n";

$json = json_encode($knowledgeBase, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

echo $json;

echo "\n\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "✅ Extracción completada!\n";
echo "═══════════════════════════════════════════════════════════\n";
echo "📊 Estadísticas:\n";
echo "   - Módulos: " . count($knowledgeBase['modulos']) . "\n";
echo "   - Estados: " . count($knowledgeBase['estados']) . "\n";
echo "   - Validaciones: " . count($knowledgeBase['validaciones']) . "\n";
echo "   - Rutas: " . (count($knowledgeBase['rutas'] ?? [])) . "\n";
echo "\n";
echo "💡 Uso:\n";
echo "   php extraer-conocimiento.php > knowledge-base.json\n";
echo "   cat knowledge-base.json\n";
echo "═══════════════════════════════════════════════════════════\n";
