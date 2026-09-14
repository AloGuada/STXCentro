<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuando el proveedor factura en pesos una orden en dólares, el tipo de
     * cambio que aplicó sale de dividir la factura entre lo que ampara. Esta
     * tolerancia, en por ciento contra el FIX de Banxico del día de la factura,
     * es lo que separa un tipo de cambio pactado de un XML que no es de esta
     * orden.
     */
    public function up(): void
    {
        Schema::table('costos_configuracion', function (Blueprint $table) {
            $table->decimal('tolerancia_tipo_cambio', 5, 2)->default(5)->after('tolerancia_recepcion');
        });
    }

    public function down(): void
    {
        Schema::table('costos_configuracion', function (Blueprint $table) {
            $table->dropColumn('tolerancia_tipo_cambio');
        });
    }
};
