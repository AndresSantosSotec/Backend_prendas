<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Estos índices optimizan las consultas más frecuentes del sistema
     * y resuelven el problema de "cargando infinito" en el frontend.
     */
    public function up(): void
    {
        // Índices para tabla clientes
        // Optimiza filtros por estado, tipo_cliente y genero
        Schema::table('clientes', function (Blueprint $table) {
            // Índice compuesto para filtros comunes (eliminado + estado)
            if (!$this->indexExists('clientes', 'idx_clientes_eliminado_estado')) {
                $table->index(['eliminado', 'estado'], 'idx_clientes_eliminado_estado');
            }

            // Índice compuesto para filtros de tipo de cliente
            if (!$this->indexExists('clientes', 'idx_clientes_eliminado_tipo')) {
                $table->index(['eliminado', 'tipo_cliente'], 'idx_clientes_eliminado_tipo');
            }

            // Índice compuesto para filtros de género
            if (!$this->indexExists('clientes', 'idx_clientes_eliminado_genero')) {
                $table->index(['eliminado', 'genero'], 'idx_clientes_eliminado_genero');
            }

            // Índice para búsquedas rápidas por nombre, apellido y DPI
            if (!$this->indexExists('clientes', 'idx_clientes_busqueda')) {
                $table->index(['nombres', 'apellidos', 'dpi'], 'idx_clientes_busqueda');
            }

            // Índice para búsqueda por código de cliente
            if (!$this->indexExists('clientes', 'idx_clientes_codigo')) {
                $table->index('codigo_cliente', 'idx_clientes_codigo');
            }
        });

        // Índices para tabla creditos_prendarios
        // Optimiza listados, filtros y búsquedas de créditos
        Schema::table('creditos_prendarios', function (Blueprint $table) {
            // Índice para filtros por estado
            if (!$this->indexExists('creditos_prendarios', 'idx_creditos_estado')) {
                $table->index('estado', 'idx_creditos_estado');
            }

            // Índice compuesto para créditos de un cliente por estado
            if (!$this->indexExists('creditos_prendarios', 'idx_creditos_cliente_estado')) {
                $table->index(['cliente_id', 'estado'], 'idx_creditos_cliente_estado');
            }

            // Índice para ordenamiento por fecha (DESC para mostrar más recientes primero)
            if (!$this->indexExists('creditos_prendarios', 'idx_creditos_fecha_solicitud')) {
                DB::statement('CREATE INDEX idx_creditos_fecha_solicitud ON creditos_prendarios(fecha_solicitud DESC)');
            }

            // Índice para filtros por monto
            if (!$this->indexExists('creditos_prendarios', 'idx_creditos_monto')) {
                $table->index('monto_aprobado', 'idx_creditos_monto');
            }

            // Índice para búsquedas por número de crédito
            if (!$this->indexExists('creditos_prendarios', 'idx_creditos_numero')) {
                $table->index('numero_credito', 'idx_creditos_numero');
            }

            // Índice compuesto para búsquedas complejas (número + estado + fecha)
            if (!$this->indexExists('creditos_prendarios', 'idx_creditos_busqueda')) {
                DB::statement('CREATE INDEX idx_creditos_busqueda ON creditos_prendarios(numero_credito, estado, fecha_solicitud DESC)');
            }

            // Índice para consultas de estadísticas (sumas y conteos por estado)
            if (!$this->indexExists('creditos_prendarios', 'idx_creditos_stats')) {
                $table->index(['estado', 'monto_desembolsado', 'capital_pendiente'], 'idx_creditos_stats');
            }

            // Índice para consultas de fechas de vencimiento
            if (!$this->indexExists('creditos_prendarios', 'idx_creditos_vencimiento')) {
                $table->index('fecha_vencimiento', 'idx_creditos_vencimiento');
            }
        });

        // Índices para tabla prendas
        // Optimiza la carga de prendas asociadas a créditos
        Schema::table('prendas', function (Blueprint $table) {
            // Índice para buscar prendas por crédito
            if (!$this->indexExists('prendas', 'idx_prendas_credito')) {
                $table->index('credito_prendario_id', 'idx_prendas_credito');
            }

            // Índice para búsqueda por código de prenda
            if (!$this->indexExists('prendas', 'idx_prendas_codigo')) {
                $table->index('codigo_prenda', 'idx_prendas_codigo');
            }

            // Índice para filtros por categoría
            if (!$this->indexExists('prendas', 'idx_prendas_categoria')) {
                $table->index('categoria_producto_id', 'idx_prendas_categoria');
            }
        });

        // Índices para tabla prenda_imagenes
        // Optimiza la carga de imágenes de prendas
        if (Schema::hasTable('prenda_imagenes')) {
            Schema::table('prenda_imagenes', function (Blueprint $table) {
                // Índice para buscar imágenes por prenda
                if (!$this->indexExists('prenda_imagenes', 'idx_prenda_imagenes_prenda')) {
                    $table->index('prenda_id', 'idx_prenda_imagenes_prenda');
                }

                // Índice compuesto para obtener imagen principal
                if (!$this->indexExists('prenda_imagenes', 'idx_prenda_imagenes_principal')) {
                    $table->index(['prenda_id', 'es_principal'], 'idx_prenda_imagenes_principal');
                }
            });
        }

        // Índices para tabla users
        // Optimiza autenticación y búsquedas de usuarios
        Schema::table('users', function (Blueprint $table) {
            // Índice para login (username único)
            if (!$this->indexExists('users', 'idx_users_username')) {
                $table->index('username', 'idx_users_username');
            }

            // Índice para filtros por estado activo
            if (!$this->indexExists('users', 'idx_users_activo')) {
                $table->index('activo', 'idx_users_activo');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Eliminar índices de clientes
        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex('idx_clientes_eliminado_estado');
            $table->dropIndex('idx_clientes_eliminado_tipo');
            $table->dropIndex('idx_clientes_eliminado_genero');
            $table->dropIndex('idx_clientes_busqueda');
            $table->dropIndex('idx_clientes_codigo');
        });

        // Eliminar índices de creditos_prendarios
        Schema::table('creditos_prendarios', function (Blueprint $table) {
            $table->dropIndex('idx_creditos_estado');
            $table->dropIndex('idx_creditos_cliente_estado');
            $table->dropIndex('idx_creditos_monto');
            $table->dropIndex('idx_creditos_numero');
            $table->dropIndex('idx_creditos_vencimiento');
            $table->dropIndex('idx_creditos_stats');
        });

        // Eliminar índices creados con DB::statement
        DB::statement('DROP INDEX IF EXISTS idx_creditos_fecha_solicitud ON creditos_prendarios');
        DB::statement('DROP INDEX IF EXISTS idx_creditos_busqueda ON creditos_prendarios');

        // Eliminar índices de prendas
        Schema::table('prendas', function (Blueprint $table) {
            $table->dropIndex('idx_prendas_credito');
            $table->dropIndex('idx_prendas_codigo');
            $table->dropIndex('idx_prendas_categoria');
        });

        // Eliminar índices de prenda_imagenes
        if (Schema::hasTable('prenda_imagenes')) {
            Schema::table('prenda_imagenes', function (Blueprint $table) {
                $table->dropIndex('idx_prenda_imagenes_prenda');
                $table->dropIndex('idx_prenda_imagenes_principal');
            });
        }

        // Eliminar índices de users
        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('idx_users_username');
            $table->dropIndex('idx_users_activo');
        });
    }

    /**
     * Verificar si un índice existe
     */
    private function indexExists(string $table, string $index): bool
    {
        $indexes = DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = '{$index}'");
        return !empty($indexes);
    }
};
