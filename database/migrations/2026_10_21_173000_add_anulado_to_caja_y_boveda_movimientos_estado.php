<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE movimiento_cajas MODIFY COLUMN estado VARCHAR(20) NOT NULL DEFAULT 'aplicado'");
        DB::statement("ALTER TABLE boveda_movimientos MODIFY COLUMN estado VARCHAR(20) NOT NULL DEFAULT 'aprobado'");
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();
        if ($driver !== 'mysql') {
            return;
        }

        DB::table('movimiento_cajas')->where('estado', 'anulado')->update(['estado' => 'rechazado']);
        DB::table('boveda_movimientos')->where('estado', 'anulado')->update(['estado' => 'rechazado']);

        DB::statement("ALTER TABLE movimiento_cajas MODIFY COLUMN estado ENUM('pendiente','aplicado','rechazado') NOT NULL DEFAULT 'aplicado'");
        DB::statement("ALTER TABLE boveda_movimientos MODIFY COLUMN estado ENUM('pendiente','aprobado','rechazado') NOT NULL DEFAULT 'aprobado'");
    }
};
