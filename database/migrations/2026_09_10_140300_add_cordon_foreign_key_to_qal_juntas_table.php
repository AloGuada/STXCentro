<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La junta capturada sobre un cordón del modelo apunta a su fila. Un cordón
     * con juntas no se deja borrar: es la plantilla sobre la que se reportó, y
     * sin ella la junta se queda sin saber de qué soldadura habla.
     */
    public function up(): void
    {
        Schema::table('qal_juntas', function (Blueprint $table) {
            $table->foreign('cordon_id')->references('id')->on('qal_modelo_cordones')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('qal_juntas', function (Blueprint $table) {
            $table->dropForeign(['cordon_id']);
        });
    }
};
