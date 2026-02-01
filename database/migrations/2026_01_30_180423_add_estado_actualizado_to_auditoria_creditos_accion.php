<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Modificar el ENUM para agregar 'estado_actualizado' y 'reactivado'
        DB::statement("ALTER TABLE `auditoria_creditos` MODIFY COLUMN `accion` ENUM(
            'creado',
            'modificado',
            'aprobado',
            'rechazado',
            'desembolsado',
            'pagado',
            'renovado',
            'rescate',
            'anulado',
            'movimiento_anulado',
            'estado_cambiado',
            'estado_actualizado',
            'reactivado'
        ) NOT NULL COMMENT 'Tipo de acción realizada'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Volver al ENUM original
        DB::statement("ALTER TABLE `auditoria_creditos` MODIFY COLUMN `accion` ENUM(
            'creado',
            'modificado',
            'aprobado',
            'rechazado',
            'desembolsado',
            'pagado',
            'renovado',
            'rescate',
            'anulado',
            'movimiento_anulado',
            'estado_cambiado'
        ) NOT NULL COMMENT 'Tipo de acción realizada'");
    }
};
