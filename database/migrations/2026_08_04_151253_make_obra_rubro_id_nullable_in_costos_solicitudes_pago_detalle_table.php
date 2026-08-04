<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Una OC de contado nacida de una requisición "sin obra" genera su solicitud de
 * pago copiando las partidas sin centro de costos: el detalle debe admitir
 * `obra_rubro_id` nulo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_solicitudes_pago_detalle', function (Blueprint $table) {
            $table->foreignId('obra_rubro_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('costos_solicitudes_pago_detalle', function (Blueprint $table) {
            $table->foreignId('obra_rubro_id')->nullable(false)->change();
        });
    }
};
