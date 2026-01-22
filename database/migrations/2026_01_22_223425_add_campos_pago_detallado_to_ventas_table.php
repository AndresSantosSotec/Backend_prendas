<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            // Agregar campos para pagos detallados (mixto)
            $table->decimal('monto_efectivo', 20, 2)->default(0)->after('metodo_pago');
            $table->decimal('monto_tarjeta', 20, 2)->default(0)->after('monto_efectivo');
            $table->decimal('monto_transferencia', 20, 2)->default(0)->after('monto_tarjeta');
            $table->decimal('monto_cheque', 20, 2)->default(0)->after('monto_transferencia');

            // Referencias de pago por método
            $table->string('referencia_tarjeta', 100)->nullable()->after('monto_cheque');
            $table->string('referencia_transferencia', 100)->nullable()->after('referencia_tarjeta');
            $table->string('referencia_cheque', 100)->nullable()->after('referencia_transferencia');
            $table->string('banco', 100)->nullable()->after('referencia_cheque');

            // Campos para facturación electrónica (FEL Guatemala)
            $table->boolean('facturada')->default(false)->after('estado');
            $table->string('numero_factura', 50)->nullable()->after('facturada');
            $table->string('serie_factura', 20)->nullable()->after('numero_factura');
            $table->string('uuid_fel', 100)->nullable()->after('serie_factura');
            $table->string('numero_autorizacion', 100)->nullable()->after('uuid_fel');
            $table->timestamp('fecha_certificacion')->nullable()->after('numero_autorizacion');

            // Campos adicionales
            $table->decimal('iva', 20, 2)->default(0)->after('descuento');
            $table->decimal('utilidad', 20, 2)->default(0)->after('iva');
            $table->foreignId('caja_id')->nullable()->after('sucursal_id')
                ->constrained('caja_apertura_cierres')->nullOnDelete();

            // Índices
            $table->index('facturada');
            $table->index('numero_factura');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropForeign(['caja_id']);
            $table->dropColumn([
                'monto_efectivo',
                'monto_tarjeta',
                'monto_transferencia',
                'monto_cheque',
                'referencia_tarjeta',
                'referencia_transferencia',
                'referencia_cheque',
                'banco',
                'facturada',
                'numero_factura',
                'serie_factura',
                'uuid_fel',
                'numero_autorizacion',
                'fecha_certificacion',
                'iva',
                'utilidad',
                'caja_id'
            ]);
        });
    }
};
