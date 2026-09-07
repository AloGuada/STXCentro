<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los renglones de una hoja: qué artículos le tocan, en qué orden.
     *
     * `cantidad_sistema` y `cantidad_contada` nacen nulas. La primera se sella
     * en el momento de capturar, no al generar la hoja: entre que se programó y
     * que se contó pudieron pasar semanas de recepciones y salidas, y comparar
     * contra un saldo viejo inventaría diferencias que no existen. La segunda
     * es lo único que el almacenista teclea; la diferencia se calcula.
     *
     * `existencia_id` apunta al renglón de saldo del que salió el artículo, que
     * es de donde se lee la ubicación para la hoja impresa. Anulable porque una
     * existencia se puede borrar y la hoja tiene que sobrevivirla.
     */
    public function up(): void
    {
        Schema::create('alm_conteo_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conteo_id')->constrained('alm_conteos')->cascadeOnDelete();
            $table->foreignId('articulo_id')->constrained('alm_articulos')->restrictOnDelete();
            $table->foreignId('existencia_id')->nullable()->constrained('alm_existencias')->nullOnDelete();
            $table->unsignedSmallInteger('orden');
            $table->decimal('cantidad_sistema', 16, 4)->nullable();
            $table->decimal('cantidad_contada', 16, 4)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->unique(['conteo_id', 'articulo_id']);
            $table->index('articulo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alm_conteo_detalle');
    }
};
