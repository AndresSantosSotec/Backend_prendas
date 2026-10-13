<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('clientes', 'es_cpe')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->boolean('es_cpe')
                    ->default(false)
                    ->after('tipo_cliente');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('clientes', 'es_cpe')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropColumn('es_cpe');
            });
        }
    }
};