<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los parámetros con que el laboratorio corrió la prueba, como clave/valor.
     *
     * No son columnas porque cambian con el método: el ultrasonido reporta
     * frecuencia y palpador, los líquidos penetrantes reportan penetrante,
     * revelador y tiempo de permanencia. Con columnas fijas, cada método nuevo
     * o cada dato que el laboratorio decide incluir sería una alteración de
     * tabla, y las columnas de los otros cuatro métodos irían siempre en nulo.
     *
     * La clave no se repite dentro del mismo informe: dos «frecuencia» con
     * valores distintos es un error de captura, no un dato.
     */
    public function up(): void
    {
        Schema::create('qal_pnd_parametros', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qal_pnd_reporte_id')->constrained('qal_pnd_reportes')->cascadeOnDelete();
            $table->string('clave');
            $table->string('valor');
            $table->timestamps();

            $table->unique(['qal_pnd_reporte_id', 'clave'], 'qal_pnd_parametros_clave_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_pnd_parametros');
    }
};
