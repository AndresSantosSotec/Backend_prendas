<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * numero_comprobante usa formato PI-2026-S1-000001 (17+ chars).
     * Las columnas estaban en 12 y provocaban "Data too long".
     */
    public function up(): void
    {
        if (Schema::hasTable('ctb_diario')) {
            Schema::table('ctb_diario', function (Blueprint $table) {
                $table->string('numero_comprobante', 50)->change();
            });
        }

        if (Schema::hasTable('ctb_movimientos')) {
            Schema::table('ctb_movimientos', function (Blueprint $table) {
                $table->string('numero_comprobante', 50)->change();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('ctb_diario')) {
            Schema::table('ctb_diario', function (Blueprint $table) {
                $table->string('numero_comprobante', 12)->change();
            });
        }

        if (Schema::hasTable('ctb_movimientos')) {
            Schema::table('ctb_movimientos', function (Blueprint $table) {
                $table->string('numero_comprobante', 12)->change();
            });
        }
    }
};
