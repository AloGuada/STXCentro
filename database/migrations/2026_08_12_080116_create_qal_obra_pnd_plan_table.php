<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuántas pruebas no destructivas se pactaron con el cliente, por método.
     *
     * Es el denominador del avance de PND. Sin esto sólo se puede decir cuántas
     * pruebas se hicieron, no si se va al día: el tablero cuenta ejecutadas y no
     * tiene contra qué compararlas.
     *
     * Se guarda por método a propósito. Comprometer 60 ensayos y hacer 60 del
     * más barato cumple el número y no cumple el contrato; con el desglose eso
     * se ve.
     *
     * Y por eso es una fila por método en vez de una columna con todos: **la
     * ausencia de fila significa «no entra en este contrato»**, mientras que una
     * fila con cero significa «se pactaron cero». El tablero los trata distinto,
     * y un cero por defecto borraría esa diferencia.
     */
    public function up(): void
    {
        Schema::create('qal_obra_pnd_plan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qal_obra_id')->constrained('qal_obras')->cascadeOnDelete();
            $table->string('metodo', 2);
            $table->unsignedInteger('comprometidas');
            $table->timestamps();

            $table->unique(['qal_obra_id', 'metodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_obra_pnd_plan');
    }
};
