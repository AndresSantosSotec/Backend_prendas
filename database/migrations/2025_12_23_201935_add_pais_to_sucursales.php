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
        Schema::table('sucursales', function (Blueprint $table) {
            $table->string('pais', 100)->default('Guatemala')->after('municipio_geoname_id');
            $table->integer('pais_geoname_id')->default(3595528)->after('pais'); // ID de Guatemala en GeoNames
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sucursales', function (Blueprint $table) {
            $table->dropColumn(['pais', 'pais_geoname_id']);
        });
    }
};
