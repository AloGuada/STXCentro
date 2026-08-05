<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `conceptos` queda como la Marca: el modelo del que cuelgan las piezas.
     *
     * El QS se va a `prod_piezas`, que es donde vive de verdad: identifica una
     * unidad física, no un modelo. Aquí `cantidad` deja de ser el tope de pago y
     * pasa a ser el tamaño del modelo — sirve para avisar que el layout dice 10
     * y sólo llegaron 8 QS.
     *
     * El unique convierte en regla lo que antes era sólo convención del import:
     * dentro de un catálogo no puede haber dos marcas con la misma etapa.
     */
    public function up(): void
    {
        Schema::table('conceptos', function (Blueprint $table) {
            $table->dropIndex(['catalogo_id', 'marca', 'etapa']);
            $table->dropColumn('qs');
            $table->unique(['catalogo_id', 'marca', 'etapa']);
        });
    }

    public function down(): void
    {
        Schema::table('conceptos', function (Blueprint $table) {
            $table->dropUnique(['catalogo_id', 'marca', 'etapa']);
            $table->string('qs', 50)->nullable()->after('id');
            $table->index(['catalogo_id', 'marca', 'etapa']);
        });
    }
};
