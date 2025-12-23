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
        // Agregar campos a sucursales
        Schema::table('sucursales', function (Blueprint $table) {
            $table->string('municipio', 100)->nullable()->after('departamento');
            $table->integer('departamento_geoname_id')->nullable()->after('municipio');
            $table->integer('municipio_geoname_id')->nullable()->after('departamento_geoname_id');
        });

        // Agregar campos a clientes
        Schema::table('clientes', function (Blueprint $table) {
            $table->string('municipio', 100)->nullable()->after('direccion');
            $table->integer('departamento_geoname_id')->nullable()->after('municipio');
            $table->integer('municipio_geoname_id')->nullable()->after('departamento_geoname_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sucursales', function (Blueprint $table) {
            $table->dropColumn(['municipio', 'departamento_geoname_id', 'municipio_geoname_id']);
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropColumn(['municipio', 'departamento_geoname_id', 'municipio_geoname_id']);
        });
    }
};
