<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Añadir soporte multi-categoría a planes de interés.
     * 
     * Estrategia:
     * 1. Crear tabla pivote plan_interes_categorias (muchos-a-muchos)
     * 2. Migrar los registros existentes al pivote
     * 3. Hacer nullable la FK directa (compatibilidad con créditos ya otorgados)
     */
    public function up(): void
    {
        // 1. Crear tabla pivote
        Schema::create('plan_interes_categorias', function (Blueprint $table) {
            $table->id();

            $table->foreignId('plan_id')
                ->constrained('planes_interes_categoria')
                ->onDelete('cascade')
                ->comment('Plan de interés');

            $table->foreignId('categoria_id')
                ->constrained('categoria_productos')
                ->onDelete('cascade')
                ->comment('Categoría de producto');

            $table->boolean('es_default')
                ->default(false)
                ->comment('Si es el plan predeterminado para esta categoría en este pivote');

            $table->integer('orden')
                ->default(0)
                ->comment('Orden de presentación dentro de la categoría');

            $table->timestamps();

            // Clave única: un plan no puede estar dos veces en la misma categoría
            $table->unique(['plan_id', 'categoria_id']);
            $table->index(['categoria_id', 'es_default']);
            $table->index('plan_id');
        });

        // 2. Migrar datos existentes: por cada plan con categoria_producto_id,
        //    insertar en el pivote para preservar la relación
        $planes = DB::table('planes_interes_categoria')
            ->whereNotNull('categoria_producto_id')
            ->whereNull('deleted_at')
            ->select('id', 'categoria_producto_id', 'es_default', 'orden')
            ->get();

        foreach ($planes as $plan) {
            DB::table('plan_interes_categorias')->insertOrIgnore([
                'plan_id'    => $plan->id,
                'categoria_id' => $plan->categoria_producto_id,
                'es_default' => $plan->es_default ?? false,
                'orden'      => $plan->orden ?? 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        // 3. Hacer nullable la FK directa (los créditos existentes la siguen usando)
        Schema::table('planes_interes_categoria', function (Blueprint $table) {
            $table->unsignedBigInteger('categoria_producto_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        // Restaurar NOT NULL antes de eliminar el pivote
        Schema::table('planes_interes_categoria', function (Blueprint $table) {
            $table->unsignedBigInteger('categoria_producto_id')->nullable(false)->change();
        });

        Schema::dropIfExists('plan_interes_categorias');
    }
};
