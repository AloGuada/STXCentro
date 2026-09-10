<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El renglón recibido dice qué artículo es, sin tener que ir a la orden.
     *
     * Se denormaliza por tres razones: las entradas sin orden no tienen partida
     * de dónde heredarlo; el kardex no debería hacer dos joins para saber qué
     * entró; y si alguien re-apunta la partida de la OC, el movimiento ya sellado
     * no debe cambiar de artículo.
     *
     * `descripcion` y `unidad` también, porque sin orden no hay de dónde
     * heredarlas y el renglón tiene que poder imprimirse solo.
     */
    public function up(): void
    {
        Schema::table('costos_entrega_detalle', function (Blueprint $table) {
            $table->foreignId('producto_id')->nullable()->after('orden_compra_detalle_id')
                ->constrained('costos_productos')->restrictOnDelete();
            $table->string('descripcion')->nullable()->after('producto_id');
            $table->string('unidad', 20)->nullable()->after('descripcion');
        });

        Schema::table('costos_entrega_detalle', function (Blueprint $table) {
            $table->foreignId('orden_compra_detalle_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('costos_entrega_detalle', function (Blueprint $table) {
            $table->dropConstrainedForeignId('producto_id');
            $table->dropColumn(['descripcion', 'unidad']);
        });
    }
};
