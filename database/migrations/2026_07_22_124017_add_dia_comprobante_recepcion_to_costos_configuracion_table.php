<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_configuracion', function (Blueprint $table) {
            // Día de la semana (numeración Carbon: 0=domingo … 6=sábado) en que el
            // proveedor puede subir el comprobante de recepción. null = libre.
            $table->unsignedTinyInteger('dia_comprobante_recepcion')->nullable()->after('corte_hora');
        });
    }

    public function down(): void
    {
        Schema::table('costos_configuracion', function (Blueprint $table) {
            $table->dropColumn('dia_comprobante_recepcion');
        });
    }
};
