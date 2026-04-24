<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categoria_productos', function (Blueprint $table) {
            if (!Schema::hasColumn('categoria_productos', 'campos_dinamicos')) {
                $table->json('campos_dinamicos')->nullable()->after('campos_adicionales')
                    ->comment('Campos dinámicos personalizados para la categoría');
            }
        });
    }

    public function down(): void
    {
        Schema::table('categoria_productos', function (Blueprint $table) {
            if (Schema::hasColumn('categoria_productos', 'campos_dinamicos')) {
                $table->dropColumn('campos_dinamicos');
            }
        });
    }
};
