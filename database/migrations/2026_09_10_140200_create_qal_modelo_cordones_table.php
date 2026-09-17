<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los cordones de soldadura que se detectaron en una marca.
     *
     * Tekla no exporta soldaduras, así que salen de la geometría: dónde se tocan
     * dos piezas y si ahí hay rincón (filete) o una junta a tope (costura). Es la
     * plantilla sobre la que el inspector reporta, cordón por cordón, si quedó
     * correcto o con defecto. Los mínimos AISC y la preparación son lo que se
     * puede fijar sólo con la geometría; el cateto de diseño no viene en el IFC.
     *
     * `puntos` y `centro` están en metros, en el sistema del .glb de la marca.
     */
    public function up(): void
    {
        Schema::create('qal_modelo_cordones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('modelo_marca_id')->constrained('qal_modelo_marcas')->cascadeOnDelete();
            $table->unsignedInteger('numero');
            $table->string('tipo', 10);
            $table->string('junta', 10)->nullable();
            $table->json('piezas');
            $table->decimal('largo_mm', 10, 1);
            $table->decimal('ancho_mm', 10, 1)->nullable();
            $table->decimal('angulo', 5, 1)->nullable();
            $table->decimal('t1_mm', 8, 2)->nullable();
            $table->decimal('t2_mm', 8, 2)->nullable();
            $table->decimal('cateto_min_mm', 6, 2)->nullable();
            $table->decimal('cateto_max_mm', 6, 2)->nullable();
            $table->decimal('garganta_min_mm', 6, 2)->nullable();
            $table->json('preparacion')->nullable();
            $table->json('avisos')->nullable();
            $table->json('centro')->nullable();
            $table->json('puntos');

            $table->unique(['modelo_marca_id', 'numero']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_modelo_cordones');
    }
};
