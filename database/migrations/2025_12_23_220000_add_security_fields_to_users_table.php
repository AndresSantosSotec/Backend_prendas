<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agregar campos de seguridad a la tabla users
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Intentos de login fallidos
            $table->integer('failed_login_attempts')->default(0)->after('activo');
            
            // Fecha/hora del último intento fallido
            $table->timestamp('last_failed_login_at')->nullable()->after('failed_login_attempts');
            
            // Fecha/hora hasta cuando está bloqueado
            $table->timestamp('locked_until')->nullable()->after('last_failed_login_at');
            
            // Última dirección IP de login
            $table->string('last_login_ip', 45)->nullable()->after('locked_until');
            
            // Última fecha de login exitoso
            $table->timestamp('last_login_at')->nullable()->after('last_login_ip');
            
            // Fecha de último cambio de contraseña
            $table->timestamp('password_changed_at')->nullable()->after('last_login_at');
            
            // Forzar cambio de contraseña en el próximo login
            $table->boolean('force_password_change')->default(false)->after('password_changed_at');
        });
    }

    /**
     * Revertir cambios
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'failed_login_attempts',
                'last_failed_login_at',
                'locked_until',
                'last_login_ip',
                'last_login_at',
                'password_changed_at',
                'force_password_change'
            ]);
        });
    }
};

