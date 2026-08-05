<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El registro de destajo deja de ser "N piezas de este modelo" y pasa a ser
     * "esta pieza, en este proceso".
     *
     * Por eso desaparece `cantidad`: un renglón es un QS, y capturar diez piezas
     * son diez renglones. El `porcentaje` se queda, porque una misma pieza sí se
     * puede pagar en parcialidades (60% esta semana, 40% la siguiente).
     *
     * No lleva unique por (pieza, proceso) justamente por eso: la misma pieza
     * puede tener dos renglones en semanas distintas. Que no se pague más del
     * 100% lo sostiene el tope de AvanceDePiezas, no la base de datos.
     */
    public function up(): void
    {
        Schema::table('prod_registros', function (Blueprint $table) {
            $table->dropConstrainedForeignId('concepto_id');
            $table->dropColumn('cantidad');
        });

        Schema::table('prod_registros', function (Blueprint $table) {
            $table->foreignId('pieza_id')->after('fecha')->constrained('prod_piezas')->cascadeOnDelete();
            $table->foreignId('proceso_id')->after('pieza_id')->constrained('prod_procesos');
        });
    }

    public function down(): void
    {
        Schema::table('prod_registros', function (Blueprint $table) {
            $table->dropConstrainedForeignId('pieza_id');
            $table->dropConstrainedForeignId('proceso_id');
        });

        Schema::table('prod_registros', function (Blueprint $table) {
            $table->foreignId('concepto_id')->after('fecha')->constrained('conceptos');
            $table->integer('cantidad')->default(0);
        });
    }
};
