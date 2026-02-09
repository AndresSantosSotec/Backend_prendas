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
        // Modificar el enum de rol para incluir 'superadmin'
        DB::statement("ALTER TABLE users MODIFY COLUMN rol ENUM('administrador', 'cajero', 'tasador', 'supervisor', 'vendedor', 'superadmin') DEFAULT 'cajero'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir al enum original
        DB::statement("ALTER TABLE users MODIFY COLUMN rol ENUM('administrador', 'cajero', 'tasador', 'supervisor', 'vendedor') DEFAULT 'cajero'");
    }
};
