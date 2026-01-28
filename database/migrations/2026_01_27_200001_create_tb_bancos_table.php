<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Catálogo de bancos de Guatemala
     */
    public function up(): void
    {
        if (!Schema::hasTable('tb_bancos')) {
            Schema::create('tb_bancos', function (Blueprint $table) {
                $table->id();

                $table->string('nombre', 100)->unique()->comment('Nombre del banco');
                $table->string('codigo_swift', 20)->nullable()->comment('Código SWIFT internacional');
                $table->string('codigo_local', 10)->nullable()->comment('Código bancario local');
                $table->string('abreviatura', 10)->nullable()->comment('Abreviatura del banco');

                $table->boolean('activo')->default(true)->comment('Si el banco está activo');

                $table->timestamps();
                $table->softDeletes();

                $table->index('activo');
                $table->index('nombre');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tb_bancos');
    }
};
