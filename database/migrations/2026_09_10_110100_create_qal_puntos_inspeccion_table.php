<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Catálogo de los puntos que revisa el inspector.
     *
     * Es el cambio estructural más grande respecto a la aplicación anterior:
     * ahí cada casilla del formulario era una columna de `registros`. Añadir un
     * punto obligaba a alterar la tabla, y quitarlo dejaba la columna muerta
     * para siempre porque los registros viejos la seguían usando. Aquí un punto
     * nuevo es una fila, y desactivarlo no toca lo ya capturado.
     *
     * La clave es la del formulario viejo (`p1_defl`, `p2_bisel`, `m_poros`),
     * que es como los inspectores y los formatos impresos conocen cada punto.
     *
     * `opciones` guarda, para los puntos de selección, cada respuesta con lo
     * que significa: «Fuera de tol.» y «Con defecto» se escriben distinto pero
     * las dos son «no cumple».
     *
     * Las filas las siembra QalPuntosInspeccionSeeder.
     */
    public function up(): void
    {
        Schema::create('qal_puntos_inspeccion', function (Blueprint $table) {
            $table->id();
            $table->string('clave', 40)->unique();
            $table->string('ambito', 10);
            $table->string('fase', 4);
            $table->string('subetapa', 20)->nullable();
            $table->string('subtipo', 10)->nullable();
            $table->string('seccion', 80);
            $table->string('etiqueta', 160);
            $table->string('tipo_dato', 12)->default('seleccion');
            $table->json('opciones')->nullable();
            $table->string('unidad', 20)->nullable();
            $table->boolean('calculado')->default(false);
            $table->boolean('obligatorio')->default(false);
            $table->unsignedSmallInteger('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['fase', 'ambito', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_puntos_inspeccion');
    }
};
