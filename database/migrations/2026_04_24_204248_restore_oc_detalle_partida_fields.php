<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Restaura los campos de partida detallada en costos_ordenes_compra_detalle
 * que habian sido eliminados en la simplificacion del 2026_02_17 y deja el
 * detalle listo para el 3-way match de la Fase 4.
 *
 * - descripcion (texto corto): nombre de la partida
 * - unidad (20): pza, kg, m3, hr, etc.
 * - cantidad (decimal 12,2): unidades pedidas
 * - precio_unitario (decimal 14,2): precio por unidad
 * - subtotal (decimal 14,2): cantidad * precio_unitario (canonico)
 *
 * Migra cualquier registro existente copiando monto -> subtotal con
 * cantidad = 1 para preservar el total; luego elimina monto.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_ordenes_compra_detalle', function (Blueprint $table) {
            $table->string('descripcion')->default('')->after('obra_rubro_id');
            $table->string('unidad', 20)->default('pza')->after('descripcion');
            $table->decimal('cantidad', 12, 2)->default(1)->after('unidad');
            $table->decimal('precio_unitario', 14, 2)->default(0)->after('cantidad');
            $table->decimal('subtotal', 14, 2)->default(0)->after('precio_unitario');
        });

        // Preservar el total financiero de partidas existentes: subtotal = monto,
        // precio_unitario = monto, cantidad = 1.
        DB::statement(
            'UPDATE costos_ordenes_compra_detalle SET subtotal = monto, precio_unitario = monto'
        );

        Schema::table('costos_ordenes_compra_detalle', function (Blueprint $table) {
            $table->dropColumn('monto');
        });
    }

    public function down(): void
    {
        Schema::table('costos_ordenes_compra_detalle', function (Blueprint $table) {
            $table->decimal('monto', 14, 2)->default(0)->after('obra_rubro_id');
        });

        DB::statement('UPDATE costos_ordenes_compra_detalle SET monto = subtotal');

        Schema::table('costos_ordenes_compra_detalle', function (Blueprint $table) {
            $table->dropColumn(['descripcion', 'unidad', 'cantidad', 'precio_unitario', 'subtotal']);
        });
    }
};
