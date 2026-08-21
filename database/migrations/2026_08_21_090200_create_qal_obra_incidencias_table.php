<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lo que sale mal **en obra**, atribuido a un departamento responsable.
     *
     * Es un circuito aparte del taller: aquí no se mide lo que el inspector
     * rechaza en planta, sino lo que aparece durante el montaje. Ninguna, una o
     * veinte por semana; el denominador —las piezas montadas— vive en
     * `qal_obra_montaje` y no se repite en cada renglón.
     *
     * `pz_defecto` es el numerador: una incidencia puede afectar a varias
     * piezas, y lo que se publica es piezas con defecto, no número de partes.
     *
     * El estado se guarda como `cerrada_en` y no como una columna de texto
     * aparte: dos representaciones del mismo hecho terminan por no coincidir, y
     * la fecha de cierre hace falta de todos modos.
     *
     * `area` y `departamento` son enums de la aplicación y no catálogos
     * editables: son las categorías con las que se llevan las estadísticas
     * históricas del Excel, y cambiarlas rompe la comparación entre ejercicios.
     */
    public function up(): void
    {
        Schema::create('qal_obra_incidencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qal_obra_id')->constrained('qal_obras')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('semana');
            $table->date('fecha');
            $table->string('area', 20);
            $table->string('departamento', 20);
            $table->unsignedInteger('pz_defecto')->default(1);
            $table->string('folio')->nullable();
            $table->text('descripcion')->nullable();
            $table->timestamp('cerrada_en')->nullable();
            $table->foreignUuid('capturista_id')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamps();

            $table->index(['qal_obra_id', 'anio', 'semana']);
            $table->index(['anio', 'departamento']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_obra_incidencias');
    }
};
