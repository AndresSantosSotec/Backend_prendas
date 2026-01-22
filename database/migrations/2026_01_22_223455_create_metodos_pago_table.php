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
        Schema::create('metodos_pago', function (Blueprint $table) {
            $table->id();
            $table->string('codigo', 20)->unique();
            $table->string('nombre', 100);
            $table->text('descripcion')->nullable();
            $table->string('icono', 50)->nullable();
            $table->boolean('requiere_referencia')->default(false);
            $table->boolean('requiere_banco')->default(false);
            $table->boolean('activo')->default(true);
            $table->integer('orden')->default(0);
            $table->timestamps();
        });

        // Insertar métodos de pago por defecto
        DB::table('metodos_pago')->insert([
            ['codigo' => 'efectivo', 'nombre' => 'Efectivo', 'descripcion' => 'Pago en efectivo', 'icono' => 'Money', 'requiere_referencia' => false, 'requiere_banco' => false, 'orden' => 1, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'tarjeta', 'nombre' => 'Tarjeta', 'descripcion' => 'Pago con tarjeta de crédito/débito', 'icono' => 'CreditCard', 'requiere_referencia' => true, 'requiere_banco' => false, 'orden' => 2, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'transferencia', 'nombre' => 'Transferencia', 'descripcion' => 'Pago por transferencia bancaria', 'icono' => 'Bank', 'requiere_referencia' => true, 'requiere_banco' => true, 'orden' => 3, 'created_at' => now(), 'updated_at' => now()],
            ['codigo' => 'cheque', 'nombre' => 'Cheque', 'descripcion' => 'Pago con cheque', 'icono' => 'Receipt', 'requiere_referencia' => true, 'requiere_banco' => true, 'orden' => 4, 'created_at' => now(), 'updated_at' => now()],
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('metodos_pago');
    }
};
