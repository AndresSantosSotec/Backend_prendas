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
        Schema::table('ctb_diario', function (Blueprint $table) {
            // Añadir campo compra_id para relacionar asientos con compras
            if (!Schema::hasColumn('ctb_diario', 'compra_id')) {
                $table->foreignId('compra_id')
                    ->nullable()
                    ->after('venta_id')
                    ->constrained('compras')
                    ->onDelete('set null');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ctb_diario', function (Blueprint $table) {
            if (Schema::hasColumn('ctb_diario', 'compra_id')) {
                $table->dropForeign(['compra_id']);
                $table->dropColumn('compra_id');
            }
        });
    }
};
