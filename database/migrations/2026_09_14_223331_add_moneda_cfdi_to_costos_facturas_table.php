<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La factura se guarda en la moneda de su orden, pero el proveedor puede
     * timbrarla en otra (cotizó en dólares y factura en pesos). Estas columnas
     * conservan lo que dice el CFDI: su moneda, su total y cuántos pesos vale
     * cada unidad de la moneda de la orden según esa factura.
     */
    public function up(): void
    {
        Schema::table('costos_facturas', function (Blueprint $table) {
            $table->string('moneda_cfdi', 10)->nullable()->after('tipo_cambio');
            $table->decimal('total_cfdi', 18, 2)->nullable()->after('moneda_cfdi');
            $table->decimal('tipo_cambio_cfdi', 14, 6)->nullable()->after('total_cfdi');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('costos_facturas', function (Blueprint $table) {
            $table->dropColumn(['moneda_cfdi', 'total_cfdi', 'tipo_cambio_cfdi']);
        });
    }
};
