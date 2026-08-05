<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La pieza física del catálogo, identificada por su QS.
     *
     * El layout trae un renglón por pieza: diez piezas de la misma marca son
     * diez QS distintos que repiten marca y etapa. `conceptos` guarda el modelo
     * (la marca) una sola vez y aquí cuelgan sus unidades.
     *
     * `unique (catalogo_id, qs)` es la regla que sostiene todo lo demás: el
     * import de avance de planta resuelve por QS sin adivinar, y cada QS se paga
     * a lo más una vez por proceso.
     *
     * `pieza_origen_id` es el linaje entre versiones del catálogo, igual que
     * `concepto_origen_id` en la marca: al versionar, las piezas copiadas son
     * filas nuevas sin producción propia, y sin el linaje el acumulado se
     * reiniciaría y se podría volver a pagar lo ya fabricado.
     */
    public function up(): void
    {
        Schema::create('prod_piezas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('catalogo_id')->constrained('prod_catalogos')->cascadeOnDelete();
            $table->foreignId('concepto_id')->constrained('conceptos')->cascadeOnDelete();
            $table->string('qs', 50);
            $table->unsignedBigInteger('pieza_origen_id')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['catalogo_id', 'qs']);
            $table->index('concepto_id');
            $table->index('pieza_origen_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prod_piezas');
    }
};
