<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El tipo de pieza (Columna, Viga, Placa...) entra al snapshot del renglón
     * liquidado. La orden de pago se imprime agrupada por obra y tipo, y un
     * renglón ya pagado no debe cambiar de grupo porque alguien reclasificó la
     * marca en el catálogo después. Para las liquidaciones anteriores a esta
     * columna se lee del catálogo vivo por `concepto_id`, que es lo único que
     * hay.
     */
    public function up(): void
    {
        Schema::table('prod_liquidacion_detalle', function (Blueprint $table) {
            $table->string('categoria_nombre', 100)->nullable()->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('prod_liquidacion_detalle', function (Blueprint $table) {
            $table->dropColumn('categoria_nombre');
        });
    }
};
