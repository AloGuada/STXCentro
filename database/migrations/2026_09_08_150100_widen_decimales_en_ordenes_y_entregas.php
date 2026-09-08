<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cantidades y precios unitarios con cuatro decimales en la orden y en la
     * recepción. Los CFDI traen precios a cuatro o seis decimales y las
     * cantidades a granel no siempre son enteras; guardarlos a dos obligaba a
     * redondear antes de comparar, y ese redondeo era buena parte de los
     * "no cuadra" de la entrada.
     *
     * Ampliar no toca los valores existentes: un 12.50 sigue siendo 12.5000.
     * `change()` exige repetir los atributos que la columna ya tenía.
     */
    public function up(): void
    {
        Schema::table('costos_ordenes_compra_detalle', function (Blueprint $table) {
            $table->decimal('cantidad', 14, 4)->change();
            $table->decimal('precio_unitario', 18, 4)->change();
        });

        Schema::table('costos_entrega_detalle', function (Blueprint $table) {
            $table->decimal('cantidad_recibida', 14, 4)->change();
            $table->decimal('precio_unitario', 18, 4)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('costos_ordenes_compra_detalle', function (Blueprint $table) {
            $table->decimal('cantidad', 12, 2)->change();
            $table->decimal('precio_unitario', 14, 2)->change();
        });

        Schema::table('costos_entrega_detalle', function (Blueprint $table) {
            $table->decimal('cantidad_recibida', 12, 2)->change();
            $table->decimal('precio_unitario', 14, 2)->nullable()->change();
        });
    }
};
