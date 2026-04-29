<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Permite que un mismo proveedor en una misma partida genere multiples OCs
 * distintas (ej. OC-1 con material A urgente, OC-2 con material B normal).
 * El agrupamiento al generar OCs cambia de (proveedor) a (proveedor, numero_oc).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_requisicion_seleccion', function (Blueprint $table) {
            $table->unsignedSmallInteger('numero_oc')->default(1)->after('proveedor_id');
        });
    }

    public function down(): void
    {
        Schema::table('costos_requisicion_seleccion', function (Blueprint $table) {
            $table->dropColumn('numero_oc');
        });
    }
};
