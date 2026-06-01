<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Refactor de Fase 4.2: las entregas dejan de pertenecer a la Factura y
 * pasan a pertenecer a la Orden de Compra. Esto habilita registrar
 * recepciones por partida (con orden_compra_detalle_id + cantidad_recibida)
 * y el 3-way match que llega en Fase 4.3.
 *
 * - costos_entregas.factura_id  ->  costos_entregas.orden_compra_id
 * - se (re)crea costos_entrega_detalle como pivote entrega<->partida OC.
 *
 * Preserva la data existente copiando factura.orden_compra_id -> entrega.orden_compra_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Agregar orden_compra_id nullable, poblarlo desde factura.orden_compra_id.
        Schema::table('costos_entregas', function (Blueprint $table) {
            $table->foreignId('orden_compra_id')->nullable()->after('id')
                ->constrained('costos_ordenes_compra');
        });

        DB::statement(
            'UPDATE costos_entregas
             SET orden_compra_id = (
                 SELECT orden_compra_id FROM costos_facturas
                 WHERE costos_facturas.id = costos_entregas.factura_id
             )'
        );

        // 2. Hacer orden_compra_id obligatorio y eliminar factura_id.
        Schema::table('costos_entregas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('factura_id');
        });

        Schema::table('costos_entregas', function (Blueprint $table) {
            $table->foreignId('orden_compra_id')->nullable(false)->change();
        });

        // 3. (Re)crear costos_entrega_detalle: detalle por partida de recepción.
        Schema::dropIfExists('costos_entrega_detalle');
        Schema::create('costos_entrega_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrega_id')->constrained('costos_entregas')->cascadeOnDelete();
            $table->foreignId('orden_compra_detalle_id')->constrained('costos_ordenes_compra_detalle');
            $table->decimal('cantidad_recibida', 12, 2);
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_entrega_detalle');

        Schema::table('costos_entregas', function (Blueprint $table) {
            $table->foreignId('factura_id')->nullable()->after('id')
                ->constrained('costos_facturas');
        });

        DB::statement(
            'UPDATE costos_entregas
             SET factura_id = (
                 SELECT id FROM costos_facturas
                 WHERE costos_facturas.orden_compra_id = costos_entregas.orden_compra_id
                 LIMIT 1
             )'
        );

        Schema::table('costos_entregas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('orden_compra_id');
            $table->foreignId('factura_id')->nullable(false)->change();
        });
    }
};
