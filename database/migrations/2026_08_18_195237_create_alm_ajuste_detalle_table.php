<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Se captura **lo contado**, nunca la diferencia: un número a la vista es
     * una respuesta sugerida, y pedir la diferencia obliga a que el almacenista
     * haga la resta y se equivoque de signo.
     *
     * `cantidad_sistema` se sella al guardar y `diferencia` es lo que se mandó
     * al kardex. Las tres se guardan aunque dos sean derivables: recalcularlas
     * después daría otro resultado, porque el saldo ya se movió.
     *
     * `costo_unitario` sólo aplica a las diferencias positivas — material que
     * apareció y hay que valuar. Nulo lo carga al costo promedio vigente.
     */
    public function up(): void
    {
        Schema::create('alm_ajuste_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ajuste_id')->constrained('alm_ajustes')->cascadeOnDelete();
            $table->foreignId('producto_id')->constrained('costos_productos')->restrictOnDelete();
            $table->decimal('cantidad_contada', 16, 4);
            $table->decimal('cantidad_sistema', 16, 4);
            $table->decimal('diferencia', 16, 4);
            $table->decimal('costo_unitario', 16, 4)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('producto_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alm_ajuste_detalle');
    }
};
