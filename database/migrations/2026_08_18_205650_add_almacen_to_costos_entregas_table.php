<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La recepción se captura desde Almacén, pero la tabla se queda donde está.
     *
     * `costos_entregas` ya es lo que destraba la factura y ajusta el presupuesto
     * por diferencia de precio; una entrada aparte en `alm_` haría que el
     * almacenista recibiera dos veces y que dos tablas contaran lo mismo. Lo que
     * se muda es la **pantalla**.
     *
     * `almacen_id` va en la cabecera y no por renglón: una recepción es un
     * evento físico en una puerta. Un camión con material para dos almacenes son
     * dos folios.
     *
     * Nullable porque el histórico no lo tiene y porque los tests y seeders de
     * Costos crean entregas sin almacén; el Form Request de Almacén sí lo exige.
     *
     * `orden_compra_id` pasa a nullable para la entrada sin orden — material que
     * llega sin compra de por medio, como el que devuelve un tercero.
     */
    public function up(): void
    {
        Schema::table('costos_entregas', function (Blueprint $table) {
            $table->foreignId('almacen_id')->nullable()->after('orden_compra_id')
                ->constrained('alm_almacenes')->restrictOnDelete();
            $table->index(['almacen_id', 'fecha_entrega']);
        });

        Schema::table('costos_entregas', function (Blueprint $table) {
            $table->foreignId('orden_compra_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('costos_entregas', function (Blueprint $table) {
            $table->dropIndex(['almacen_id', 'fecha_entrega']);
            $table->dropConstrainedForeignId('almacen_id');
        });
    }
};
