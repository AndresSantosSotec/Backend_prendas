<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prendas', function (Blueprint $table) {
            $table->timestamp('fecha_publicacion_venta')->nullable()->after('fecha_venta')->comment('Fecha en que se publicó para venta');
        });
    }

    public function down(): void
    {
        Schema::table('prendas', function (Blueprint $table) {
            $table->dropColumn('fecha_publicacion_venta');
        });
    }
};
