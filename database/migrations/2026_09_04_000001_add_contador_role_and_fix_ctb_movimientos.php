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
        $driver = DB::getDriverName();

        // 1. Agregar 'contador' al enum rol en la tabla users (si es MySQL)
        if ($driver === 'mysql' && Schema::hasTable('users') && Schema::hasColumn('users', 'rol')) {
            DB::statement("ALTER TABLE users MODIFY COLUMN rol ENUM('administrador', 'cajero', 'tasador', 'supervisor', 'vendedor', 'superadmin', 'contador') DEFAULT 'cajero'");
        }

        // 2. Asegurar que numero_comprobante en ctb_movimientos soporte hasta 50 caracteres igual que en ctb_diario
        if (Schema::hasTable('ctb_movimientos') && Schema::hasColumn('ctb_movimientos', 'numero_comprobante')) {
            Schema::table('ctb_movimientos', function (Blueprint $table) use ($driver) {
                if ($driver === 'mysql') {
                    DB::statement("ALTER TABLE ctb_movimientos MODIFY COLUMN numero_comprobante VARCHAR(50)");
                }
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'mysql' && Schema::hasTable('users') && Schema::hasColumn('users', 'rol')) {
            DB::statement("ALTER TABLE users MODIFY COLUMN rol ENUM('administrador', 'cajero', 'tasador', 'supervisor', 'vendedor', 'superadmin') DEFAULT 'cajero'");
        }

        if (Schema::hasTable('ctb_movimientos') && Schema::hasColumn('ctb_movimientos', 'numero_comprobante')) {
            if ($driver === 'mysql') {
                DB::statement("ALTER TABLE ctb_movimientos MODIFY COLUMN numero_comprobante VARCHAR(12)");
            }
        }
    }
};
