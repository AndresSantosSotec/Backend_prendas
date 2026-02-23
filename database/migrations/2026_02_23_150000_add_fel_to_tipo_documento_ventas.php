<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Ampliar el ENUM tipo_documento en ventas para incluir 'FEL'
     * (Factura Electrónica en Línea - Guatemala)
     */
    public function up(): void
    {
        // Modificar el ENUM directamente con ALTER TABLE
        DB::statement("ALTER TABLE ventas MODIFY COLUMN tipo_documento ENUM('NOTA', 'FACTURA', 'RECIBO', 'COTIZACION', 'FEL') DEFAULT 'NOTA'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revertir: primero actualizar los registros con FEL a FACTURA
        DB::statement("UPDATE ventas SET tipo_documento = 'FACTURA' WHERE tipo_documento = 'FEL'");

        // Luego restaurar el ENUM original
        DB::statement("ALTER TABLE ventas MODIFY COLUMN tipo_documento ENUM('NOTA', 'FACTURA', 'RECIBO', 'COTIZACION') DEFAULT 'NOTA'");
    }
};
