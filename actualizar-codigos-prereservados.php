<?php

/**
 * Script para actualizar códigos prereservados antiguos (CR-XXXXXX) al nuevo formato
 *
 * Formato antiguo: CR-26019876
 * Formato nuevo: ORGDDMMYYAACORRELATIVO (16 dígitos)
 * Ejemplo: 0102022601000001
 *
 * Uso: php actualizar-codigos-prereservados.php
 */

require __DIR__.'/vendor/autoload.php';

use Illuminate\Support\Facades\DB;
use App\Models\CodigoPrereservado;
use Carbon\Carbon;

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

echo "\n=== ACTUALIZAR CÓDIGOS PRE-RESERVADOS AL NUEVO FORMATO ===\n\n";

try {
    // Buscar códigos prereservados con formato antiguo (CR-XXXXXX)
    $codigosAntiguos = CodigoPrereservado::where('codigo_credito', 'LIKE', 'CR-%')
        ->orderBy('created_at')
        ->get();

    if ($codigosAntiguos->isEmpty()) {
        echo "✅ No hay códigos prereservados con formato antiguo para actualizar.\n\n";
        exit(0);
    }

    echo "📊 Se encontraron {$codigosAntiguos->count()} códigos prereservados con formato antiguo.\n\n";

    // Agrupar por fecha y agencia para mantener orden correlativo
    $codigosPorFechaAgencia = [];

    foreach ($codigosAntiguos as $codigo) {
        $fechaCreacion = Carbon::parse($codigo->created_at);
        $organizacion = str_pad(env('ORGANIZATION_CODE', '01'), 2, '0', STR_PAD_LEFT);
        $fecha = $fechaCreacion->format('dmy');
        $agencia = '01'; // Por defecto agencia 1
        $clave = $organizacion . '-' . $fecha . '-' . $agencia;
        $codigosPorFechaAgencia[$clave][] = $codigo;
    }

    $actualizados = 0;
    $errores = 0;

    // Procesar cada grupo
    foreach ($codigosPorFechaAgencia as $clave => $codigos) {
        list($organizacion, $fecha, $agencia) = explode('-', $clave);

        // Buscar último correlativo usado en esa fecha para créditos
        $prefijoBusqueda = $organizacion . $fecha . $agencia;
        $ultimoCredito = DB::table('creditos_prendarios')
            ->where('numero_credito', 'LIKE', $prefijoBusqueda . '%')
            ->where('numero_credito', 'NOT LIKE', 'CR-%')
            ->orderBy('id', 'desc')
            ->first();

        $correlativo = 0;
        if ($ultimoCredito && isset($ultimoCredito->numero_credito)) {
            $correlativo = (int) substr($ultimoCredito->numero_credito, -6);
        }

        // Asignar correlativos secuenciales
        foreach ($codigos as $codigo) {
            $correlativo++;
            $nuevoNumero = $organizacion . $fecha . $agencia . str_pad($correlativo, 6, '0', STR_PAD_LEFT);

            echo "Actualizando: {$codigo->codigo_credito} → {$nuevoNumero}\n";

            try {
                $codigo->codigo_credito = $nuevoNumero;
                $codigo->save();
                $actualizados++;
            } catch (\Exception $e) {
                echo "  ❌ Error: {$e->getMessage()}\n";
                $errores++;
            }
        }
    }

    echo "\n✅ Actualización completada.\n";
    echo "   📊 Códigos actualizados: {$actualizados}\n";
    echo "   ❌ Errores: {$errores}\n\n";

} catch (\Exception $e) {
    echo "\n❌ ERROR: {$e->getMessage()}\n";
    echo "Archivo: {$e->getFile()}\n";
    echo "Línea: {$e->getLine()}\n\n";
    exit(1);
}
