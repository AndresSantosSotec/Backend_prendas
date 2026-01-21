<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Agrega configuración de campos dinámicos para el formulario de prendas
     * según la categoría seleccionada.
     */
    public function up(): void
    {
        Schema::table('categoria_productos', function (Blueprint $table) {
            // JSON con la configuración de campos requeridos/opcionales para esta categoría
            // Ejemplo: {"marca": true, "modelo": true, "numero_serie": false, "condicion_fisica": true}
            $table->json('campos_formulario')->nullable()->after('permite_pago_capital_diferente')
                ->comment('Configuración de campos dinámicos del formulario');

            // Campos adicionales específicos de la categoría (JSON array)
            // Ejemplo: [{"nombre": "quilates", "tipo": "number", "requerido": true}, {"nombre": "material", "tipo": "select", "opciones": ["oro", "plata"]}]
            $table->json('campos_adicionales')->nullable()->after('campos_formulario')
                ->comment('Campos personalizados adicionales para esta categoría');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categoria_productos', function (Blueprint $table) {
            $table->dropColumn(['campos_formulario', 'campos_adicionales']);
        });
    }
};
