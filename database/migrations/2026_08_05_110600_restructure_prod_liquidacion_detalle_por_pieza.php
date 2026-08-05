<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El detalle de liquidación pasa a ser un renglón por pieza y proceso.
     *
     * Es el libro de lo pagado, no la vista: agrupar aquí por marca perdería qué
     * QS exactamente se pagó, y sin ese dato borrar un registro y recapturarlo
     * permitiría cobrar dos veces la misma pieza. La orden de pago vuelve a
     * agrupar por marca al imprimirse, que es donde esa agrupación sí sirve.
     *
     * Sigue sin FK a propósito (el renglón sobrevive al borrado de la pieza) y
     * carga su propia copia de qs, marca, etapa y proceso: una semana cerrada no
     * debe cambiar aunque después se edite el catálogo.
     */
    public function up(): void
    {
        Schema::table('prod_liquidacion_detalle', function (Blueprint $table) {
            $table->unsignedBigInteger('pieza_id')->nullable()->after('concepto_id');
            $table->string('qs', 50)->nullable()->after('pieza_id');
            $table->unsignedBigInteger('proceso_id')->nullable()->after('etapa');
            $table->string('proceso_nombre')->nullable()->after('proceso_id');
            $table->dropColumn('cantidad');
        });
    }

    public function down(): void
    {
        Schema::table('prod_liquidacion_detalle', function (Blueprint $table) {
            $table->integer('cantidad')->default(0);
            $table->dropColumn(['pieza_id', 'qs', 'proceso_id', 'proceso_nombre']);
        });
    }
};
