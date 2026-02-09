<?php

/**
 * Script de prueba para verificar la generación de códigos pre-reservados
 *
 * Uso: php test-codigo-prereservado.php
 */

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\CodigoPrereservado;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n=== PRUEBA DE GENERACIÓN DE CÓDIGOS PRE-RESERVADOS ===\n\n";

try {
    // Iniciar transacción para no afectar la BD
    DB::beginTransaction();

    echo "📝 Generando código de crédito (Sucursal 1)...\n";
    $codigo1 = CodigoPrereservado::generarCodigoCredito(1);
    echo "   ✅ Código generado: {$codigo1}\n";
    echo "   📏 Longitud: " . strlen($codigo1) . " dígitos\n";
    echo "   🏢 Organización: " . substr($codigo1, 0, 2) . "\n";
    echo "   📅 Fecha: " . substr($codigo1, 2, 6) . " (DDMMYY)\n";
    echo "   🏪 Agencia: " . substr($codigo1, 8, 2) . "\n";
    echo "   🔢 Correlativo: " . substr($codigo1, 10, 6) . "\n\n";

    echo "📝 Generando código de crédito (Sucursal 5)...\n";
    $codigo2 = CodigoPrereservado::generarCodigoCredito(5);
    echo "   ✅ Código generado: {$codigo2}\n";
    echo "   📏 Longitud: " . strlen($codigo2) . " dígitos\n";
    echo "   🏢 Organización: " . substr($codigo2, 0, 2) . "\n";
    echo "   📅 Fecha: " . substr($codigo2, 2, 6) . " (DDMMYY)\n";
    echo "   🏪 Agencia: " . substr($codigo2, 8, 2) . "\n";
    echo "   🔢 Correlativo: " . substr($codigo2, 10, 6) . "\n\n";

    // Verificar formato
    $esFormatoValido = function($codigo) {
        return strlen($codigo) === 16 &&
               ctype_digit($codigo) &&
               !str_contains($codigo, 'CR-');
    };

    if ($esFormatoValido($codigo1) && $esFormatoValido($codigo2)) {
        echo "✅ FORMATO CORRECTO: Códigos de 16 dígitos sin guiones\n";
    } else {
        echo "❌ ERROR: Formato incorrecto\n";
    }

    // Rollback para no guardar en BD
    DB::rollBack();
    echo "\n✅ Prueba completada (sin afectar la base de datos)\n\n";

} catch (\Exception $e) {
    DB::rollBack();
    echo "\n❌ ERROR: " . $e->getMessage() . "\n";
    echo "Archivo: " . $e->getFile() . "\n";
    echo "Línea: " . $e->getLine() . "\n\n";
    exit(1);
}
