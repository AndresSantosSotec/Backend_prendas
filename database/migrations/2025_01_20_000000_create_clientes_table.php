<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->string('nombres');
            $table->string('apellidos');
            $table->string('dpi', 20)->unique();
            $table->date('fecha_nacimiento');
            $table->enum('genero', ['masculino', 'femenino', 'otro']);
            $table->string('telefono', 20);
            $table->string('email')->nullable();
            $table->text('direccion');
            $table->text('fotografia')->nullable(); // Almacena base64 o ruta de archivo
            $table->enum('estado', ['activo', 'inactivo'])->default('activo');
            $table->string('sucursal')->nullable();
            $table->enum('tipo_cliente', ['regular', 'vip'])->default('regular');
            $table->boolean('eliminado')->default(false);
            $table->timestamp('eliminado_en')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('dpi');
            $table->index('estado');
            $table->index('tipo_cliente');
            $table->index('eliminado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('clientes');
    }
};

