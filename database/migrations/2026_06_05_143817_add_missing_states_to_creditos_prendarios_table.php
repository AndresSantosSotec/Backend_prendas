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
        DB::statement("ALTER TABLE creditos_prendarios MODIFY COLUMN estado ENUM('solicitado','en_analisis','aprobado','vigente','pagado','vencido','en_mora','incobrable','recuperado','vendido','rechazado','cancelado','rematado','rescatado','en_inventario','anulado','renovado') NOT NULL DEFAULT 'solicitado'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE creditos_prendarios MODIFY COLUMN estado ENUM('solicitado','en_analisis','aprobado','vigente','pagado','vencido','en_mora','incobrable','recuperado','vendido','rechazado','cancelado','rematado') NOT NULL DEFAULT 'solicitado'");
    }
};
