<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La prueba de adherencia de una pieza pintada (F-STX-CA-08, ASTM D3359).
     *
     * Es opcional: no todas las piezas la llevan. Las fotos de las tiras van a
     * la tabla `media`, colgadas de esta fila.
     */
    public function up(): void
    {
        Schema::create('qal_adherencia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspeccion_id')->unique()->constrained('qal_inspecciones')->cascadeOnDelete();
            $table->string('metodo', 1);
            $table->string('resultado', 20)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_adherencia');
    }
};
