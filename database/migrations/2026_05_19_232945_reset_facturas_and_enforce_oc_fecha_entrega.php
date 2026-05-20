<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reset de facturas: el flujo de proveedores cambia a "no se puede facturar
 * sin recepción del almacén". Como el sistema aún no está en uso productivo,
 * se borran las facturas existentes (y dependientes) en lugar de migrarlas.
 *
 * Además: la fecha de entrega esperada pasa a ser obligatoria en la OC
 * (con backfill de created_at+7d para registros previos).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('costos_pagos')
            ->where('pagable_type', \App\Models\Costos\Factura::class)
            ->delete();

        DB::table('media')
            ->where('mediable_type', \App\Models\Costos\Factura::class)
            ->delete();

        if (Schema::hasTable('costos_anticipo_aplicaciones')) {
            DB::table('costos_anticipo_aplicaciones')->delete();
        }

        DB::table('costos_notas_credito')->delete();
        DB::table('costos_factura_detalle')->delete();
        DB::table('costos_facturas')->delete();

        DB::table('costos_ordenes_compra')
            ->where('estatus', 'pendiente_entrega')
            ->update(['estatus' => 'pendiente_factura']);

        DB::table('costos_ordenes_compra')
            ->whereNull('fecha_entrega_esperada')
            ->update([
                'fecha_entrega_esperada' => DB::raw("date(created_at, '+7 days')"),
            ]);

        Schema::table('costos_ordenes_compra', function (Blueprint $table) {
            $table->date('fecha_entrega_esperada')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('costos_ordenes_compra', function (Blueprint $table) {
            $table->date('fecha_entrega_esperada')->nullable()->change();
        });
    }
};
