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
        Schema::table('cli_pep', function (Blueprint $table) {
            $table->string('origen')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cli_pep', function (Blueprint $table) {
            $table->enum('origen', [
                'Bienes muebles e inmuebles por herencia',
                'Bienes muebles e inmuebles',
                'Negocio propio',
                'Servicios profesionales',
                'Préstamos bancarios',
                'Trabajos anteriores',
                'Trabajo actual',
                'Otros',
            ])->change();
        });
    }
};
