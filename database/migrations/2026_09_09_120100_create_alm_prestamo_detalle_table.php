<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Un renglón del resguardo. Dos formas, según el catálogo:
     *
     * - Con `activo_id`: una pieza con serie, cantidad 1. Vuelve entera, y al
     *   volver se dice cómo (bien, o dañada → en reparación).
     * - Sin `activo_id`: un activo por cantidad (extensiones, arneses). Se
     *   prestan N contra la existencia y pueden volver por partes:
     *   `cantidad_devuelta` va subiendo hasta igualar `cantidad`.
     *
     * `condicion_salida` y `condicion_retorno` se guardan las dos: comparar
     * cómo salió contra cómo volvió es lo que permite reclamar un daño.
     */
    public function up(): void
    {
        Schema::create('alm_prestamo_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prestamo_id')->constrained('alm_prestamos')->cascadeOnDelete();
            $table->foreignId('articulo_id')->constrained('alm_articulos')->restrictOnDelete();
            $table->foreignId('activo_id')->nullable()->constrained('alm_activos')->restrictOnDelete();
            $table->decimal('cantidad', 16, 4);
            $table->decimal('cantidad_devuelta', 16, 4)->default(0);
            $table->string('condicion_salida')->nullable();
            $table->string('condicion_retorno')->nullable();
            $table->timestamp('devuelto_en')->nullable();
            $table->foreignUuid('recibido_por')->nullable()->constrained('usuarios');
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->index('articulo_id');
            $table->index('activo_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alm_prestamo_detalle');
    }
};
