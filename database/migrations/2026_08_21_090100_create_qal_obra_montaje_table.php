<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La semana de montaje de una obra: cuánto se montó y si se revisó.
     *
     * Corrige el problema que arrastra el Excel «ESTADISTICAS INCIDENCIAS EN
     * OBRA». Allá cada fila mezcla dos cosas de grano distinto: las piezas
     * montadas de la semana —un hecho, uno por semana— y **una** incidencia.
     * Con tres incidencias hacen falta tres filas, y como las piezas montadas
     * no se pueden repetir sin contarlas tres veces, se escriben en la primera
     * y se pone cero en las demás. Funciona por disciplina, no por diseño: en
     * cuanto alguien teclea la cifra en la fila equivocada el porcentaje se va.
     * En el archivo de este año ya pasó —PLAZA AMALIA con 1 pieza montada y 23
     * defectos, o sea 2300 % de incidencias—.
     *
     * Aquí el denominador vive solo, uno por obra y semana, y las incidencias
     * cuelgan aparte.
     *
     * `pz_montadas` es **nullable** a propósito: permite dejar constancia de
     * que la semana se revisó sin tener todavía la cifra de avance. Nulo es
     * «falta el dato»; cero es «no se montó nada».
     *
     * `sin_incidencias` es un dato, no un hueco. Una semana en blanco puede
     * significar que no hubo hallazgos o que nadie la revisó, y no son lo
     * mismo. En la aplicación anterior esto se guardaba como una incidencia
     * fantasma con descripción «Sin incidencias»; aquí es una bandera de la
     * semana, que es de quien es el hecho.
     */
    public function up(): void
    {
        Schema::create('qal_obra_montaje', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qal_obra_id')->constrained('qal_obras')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('semana');
            $table->unsignedInteger('pz_montadas')->nullable();
            $table->boolean('sin_incidencias')->default(false);
            $table->string('notas')->nullable();
            $table->foreignUuid('capturista_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();

            $table->unique(['qal_obra_id', 'anio', 'semana'], 'qal_obra_montaje_semana_unique');
            $table->index(['anio', 'semana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_obra_montaje');
    }
};
