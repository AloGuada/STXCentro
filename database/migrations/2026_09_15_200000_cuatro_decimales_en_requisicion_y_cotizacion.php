<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cantidades y precios unitarios a 4 decimales en la requisición y la
 * cotización, como ya los tiene la orden de compra (cantidad 14,4 y precio
 * 18,4) y la recepción. Los importes que se pagan (subtotal, IVA, total) se
 * quedan a 2: el peso tiene centavos y el CFDI cuadra a 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_requisicion_detalle', function (Blueprint $table) {
            $table->decimal('cantidad', 14, 4)->change();
        });

        Schema::table('costos_requisicion_seleccion', function (Blueprint $table) {
            $table->decimal('cantidad', 14, 4)->change();
        });

        Schema::table('costos_requisicion_cotizacion_precio', function (Blueprint $table) {
            $table->decimal('precio_unitario', 18, 4)->change();
        });

        Schema::table('costos_producto_precios', function (Blueprint $table) {
            $table->decimal('precio', 18, 4)->change();
        });
    }

    public function down(): void
    {
        Schema::table('costos_requisicion_detalle', function (Blueprint $table) {
            $table->decimal('cantidad', 14, 2)->change();
        });

        Schema::table('costos_requisicion_seleccion', function (Blueprint $table) {
            $table->decimal('cantidad', 14, 2)->change();
        });

        Schema::table('costos_requisicion_cotizacion_precio', function (Blueprint $table) {
            $table->decimal('precio_unitario', 14, 2)->change();
        });

        Schema::table('costos_producto_precios', function (Blueprint $table) {
            $table->decimal('precio', 14, 2)->change();
        });
    }
};
