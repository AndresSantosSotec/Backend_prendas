<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Modificar tabla ventas para soporte completo de sistema de ventas
     * Basado en StockGenius adaptado a PrendaPlus
     */
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            // Hacer prenda_id nullable ya que ahora puede vender múltiples productos
            if (Schema::hasColumn('ventas', 'prenda_id')) {
                $table->foreignId('prenda_id')->nullable()->change();
            }

            // Agregar campos del sistema StockGenius solo si no existen
            if (!Schema::hasColumn('ventas', 'tipo_documento')) {
                $table->enum('tipo_documento', ['NOTA', 'FACTURA', 'RECIBO', 'COTIZACION'])->default('NOTA')->after('id');
            }
            if (!Schema::hasColumn('ventas', 'numero_documento')) {
                $table->string('numero_documento', 50)->unique()->nullable()->after('tipo_documento');
            }
            if (!Schema::hasColumn('ventas', 'serie_documento')) {
                $table->string('serie_documento', 10)->nullable()->after('numero_documento');
            }

            // Cliente mejorado (puede ser de tabla clientes o consumidor final)
            if (!Schema::hasColumn('ventas', 'cliente_id')) {
                $table->foreignId('cliente_id')->nullable()->after('credito_prendario_id')->constrained('clientes')->nullOnDelete();
            }
            if (!Schema::hasColumn('ventas', 'consumidor_final')) {
                $table->boolean('consumidor_final')->default(false)->after('cliente_id');
            }

            // Moneda y cambio
            if (!Schema::hasColumn('ventas', 'moneda_id')) {
                $table->foreignId('moneda_id')->nullable()->after('consumidor_final')->constrained('monedas');
            }
            if (!Schema::hasColumn('ventas', 'tipo_cambio')) {
                $table->decimal('tipo_cambio', 10, 4)->default(1.0000)->after('moneda_id');
            }

            // Totales desglosados
            if (!Schema::hasColumn('ventas', 'subtotal')) {
                $table->decimal('subtotal', 20, 2)->default(0)->after('precio_final');
            }
            if (!Schema::hasColumn('ventas', 'total_descuentos')) {
                $table->decimal('total_descuentos', 20, 2)->default(0)->after('subtotal');
            }
            if (!Schema::hasColumn('ventas', 'total_impuestos')) {
                $table->decimal('total_impuestos', 20, 2)->default(0)->after('total_descuentos');
            }
            if (!Schema::hasColumn('ventas', 'total_final')) {
                $table->decimal('total_final', 20, 2)->default(0)->after('total_impuestos');
            }

            // Información de pago
            if (!Schema::hasColumn('ventas', 'total_pagado')) {
                $table->decimal('total_pagado', 20, 2)->default(0)->after('total_final');
            }
            if (!Schema::hasColumn('ventas', 'cambio_devuelto')) {
                $table->decimal('cambio_devuelto', 20, 2)->default(0)->after('total_pagado');
            }

            // Tipo de venta
            if (!Schema::hasColumn('ventas', 'tipo_venta')) {
                $table->enum('tipo_venta', ['contado', 'apartado', 'plan_pagos'])->default('contado')->after('cambio_devuelto');
            }

            // Certificación fiscal (para factura electrónica)
            if (!Schema::hasColumn('ventas', 'certificada')) {
                $table->boolean('certificada')->default(false)->after('estado');
            }
            if (!Schema::hasColumn('ventas', 'no_autorizacion')) {
                $table->string('no_autorizacion', 100)->nullable()->after('certificada');
            }
            if (!Schema::hasColumn('ventas', 'fecha_certificacion')) {
                $table->timestamp('fecha_certificacion')->nullable()->after('no_autorizacion');
            }

            // Notas
            if (!Schema::hasColumn('ventas', 'notas')) {
                $table->text('notas')->nullable()->after('observaciones');
            }
        });

        // Modificar estado en una transacción separada para evitar conflictos
        Schema::table('ventas', function (Blueprint $table) {
            // Modificar estado para tener más opciones
            $table->enum('estado', [
                'pendiente',      // Cotización o venta no finalizada
                'pagada',         // Pagada completamente
                'apartado',       // Con anticipo, pendiente de completar
                'plan_pagos',     // En plan de pagos
                'cancelada',      // Cancelada
                'devuelta',       // Devuelta
                'anulada'         // Anulada por error
            ])->default('pendiente')->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn([
                'tipo_documento',
                'numero_documento',
                'serie_documento',
                'cliente_id',
                'consumidor_final',
                'moneda_id',
                'tipo_cambio',
                'subtotal',
                'total_descuentos',
                'total_impuestos',
                'total_final',
                'total_pagado',
                'cambio_devuelto',
                'tipo_venta',
                'certificada',
                'no_autorizacion',
                'fecha_certificacion',
                'notas'
            ]);

            // Restaurar prenda_id como requerido
            $table->foreignId('prenda_id')->nullable(false)->change();

            // Restaurar estado original
            $table->enum('estado', ['completada', 'cancelada', 'pendiente_entrega'])->default('completada')->change();
        });
    }
};

