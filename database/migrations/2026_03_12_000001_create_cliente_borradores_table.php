<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cliente_borradores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->string('titulo', 100)->default('Borrador');
            $table->json('datos'); // Todos los campos del formulario de cliente
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cliente_borradores');
    }
};
