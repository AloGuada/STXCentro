<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un pago se puede complementar con varios REP.
     *
     * La obligación nace por el monto completo del pago, pero el SAT emite un
     * complemento por cada parcialidad: si la factura se liquidó en tres
     * transferencias, llegan tres REP y cada uno cubre una parte. Antes sólo
     * cabía un `complemento_uuid` y el emparejado exigía que el importe cuadrara
     * exacto, así que una parcialidad se rechazaba siempre.
     *
     * Cada renglón aquí es un REP aplicado a esta obligación. `monto_cubierto`
     * en la obligación es su suma, y es lo que decide cuándo queda cumplida —
     * marcarla con el primer REP parcial liberaría al proveedor debiendo todavía
     * los complementos del resto.
     */
    public function up(): void
    {
        Schema::create('costos_complementos_recibidos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('complemento_pago_id')->constrained('costos_complementos_pago')->cascadeOnDelete();
            $table->string('uuid');
            $table->decimal('imp_pagado', 14, 2);
            $table->timestamp('recibido_at');
            $table->timestamps();

            // Un mismo REP puede tocar varias obligaciones (referencia varias
            // facturas), pero no dos veces la misma.
            $table->unique(['complemento_pago_id', 'uuid']);
            $table->index('uuid');
        });

        Schema::table('costos_complementos_pago', function (Blueprint $table) {
            $table->decimal('monto_cubierto', 14, 2)->default(0)->after('monto_pago');

            // El unique sobre `complemento_uuid` asumía un REP por obligación, y
            // un mismo REP cubre varias facturas a la vez. La unicidad se mueve a
            // `costos_complementos_recibidos`, que sí distingue la pareja
            // (obligación, REP). La columna se queda como referencia rápida al
            // último complemento aplicado.
            $table->dropUnique(['complemento_uuid']);
        });

        // Las obligaciones ya cumplidas se dan por cubiertas al 100%: su
        // complemento llegó cuando el emparejado exigía el importe exacto.
        DB::table('costos_complementos_pago')
            ->where('estatus', 'cumplido')
            ->update(['monto_cubierto' => DB::raw('monto_pago')]);
    }

    public function down(): void
    {
        Schema::table('costos_complementos_pago', function (Blueprint $table) {
            $table->dropColumn('monto_cubierto');
        });

        Schema::dropIfExists('costos_complementos_recibidos');
    }
};
