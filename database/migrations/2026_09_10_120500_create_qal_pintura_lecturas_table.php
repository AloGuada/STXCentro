<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cada lectura del calibre: medición (1 a 15) por lectura (1 a 3).
     *
     * En la aplicación anterior eran quince columnas con el promedio de cada
     * medición y las lecturas crudas en un texto JSON. Como filas, ni sobran las
     * que no se usan ni hace falta alterar la tabla si mañana son veinte.
     */
    public function up(): void
    {
        Schema::create('qal_pintura_lecturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pintura_id')->constrained('qal_pintura')->cascadeOnDelete();
            $table->unsignedTinyInteger('medicion');
            $table->unsignedTinyInteger('lectura');
            $table->decimal('valor_mils', 8, 2);

            $table->unique(['pintura_id', 'medicion', 'lectura']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_pintura_lecturas');
    }
};
