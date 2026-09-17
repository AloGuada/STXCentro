<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los 18 puntos del mapeo, contestados junta por junta (OK, defecto, no
     * aplica). Apuntan al mismo catálogo de puntos que la pieza, con ámbito de
     * junta.
     */
    public function up(): void
    {
        Schema::create('qal_junta_puntos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('junta_id')->constrained('qal_juntas')->cascadeOnDelete();
            $table->foreignId('punto_id')->constrained('qal_puntos_inspeccion')->restrictOnDelete();
            $table->string('resultado', 10);

            $table->unique(['junta_id', 'punto_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_junta_puntos');
    }
};
