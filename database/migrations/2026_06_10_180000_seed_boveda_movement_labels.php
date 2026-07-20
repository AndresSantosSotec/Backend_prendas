<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $now = now();

        $configs = [
            [
                'clave'                  => 'boveda_label_entrada',
                'valor'                  => 'Entrada',
                'tipo'                   => 'string',
                'grupo'                  => 'boveda',
                'descripcion'            => 'Etiqueta personalizada para movimientos de Entrada en Bóveda',
                'editable_por_usuario'   => true,
                'created_at'             => $now,
                'updated_at'             => $now,
            ],
            [
                'clave'                  => 'boveda_label_salida',
                'valor'                  => 'Salida',
                'tipo'                   => 'string',
                'grupo'                  => 'boveda',
                'descripcion'            => 'Etiqueta personalizada para movimientos de Salida en Bóveda',
                'editable_por_usuario'   => true,
                'created_at'             => $now,
                'updated_at'             => $now,
            ],
            [
                'clave'                  => 'boveda_label_transferencia_entrada',
                'valor'                  => 'Transferencia Entrada',
                'tipo'                   => 'string',
                'grupo'                  => 'boveda',
                'descripcion'            => 'Etiqueta personalizada para movimientos de Transferencia Entrada en Bóveda',
                'editable_por_usuario'   => true,
                'created_at'             => $now,
                'updated_at'             => $now,
            ],
            [
                'clave'                  => 'boveda_label_transferencia_salida',
                'valor'                  => 'Transferencia Salida',
                'tipo'                   => 'string',
                'grupo'                  => 'boveda',
                'descripcion'            => 'Etiqueta personalizada para movimientos de Transferencia Salida en Bóveda',
                'editable_por_usuario'   => true,
                'created_at'             => $now,
                'updated_at'             => $now,
            ],
            [
                'clave'                  => 'boveda_label_ingreso_cierre_diario',
                'valor'                  => 'Ingreso por Cierre Diario',
                'tipo'                   => 'string',
                'grupo'                  => 'boveda',
                'descripcion'            => 'Etiqueta personalizada para movimientos de Ingreso por Cierre Diario en Bóveda',
                'editable_por_usuario'   => true,
                'created_at'             => $now,
                'updated_at'             => $now,
            ],
        ];

        foreach ($configs as $config) {
            if (!DB::table('configuraciones_sistema')->where('clave', $config['clave'])->exists()) {
                DB::table('configuraciones_sistema')->insert($config);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('configuraciones_sistema')
            ->whereIn('clave', [
                'boveda_label_entrada',
                'boveda_label_salida',
                'boveda_label_transferencia_entrada',
                'boveda_label_transferencia_salida',
                'boveda_label_ingreso_cierre_diario'
            ])
            ->delete();
    }
};
