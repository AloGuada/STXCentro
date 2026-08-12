<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Áreas del catálogo de almacén. Clasifican el artículo: a qué parte de la
     * operación pertenece lo que se compra y se guarda.
     *
     * Es una lista de un solo campo a propósito. No es la ubicación física —eso
     * es `alm_ubicaciones`, que cuelga de un almacén y dice en qué anaquel
     * está—, ni el almacén: un mismo artículo del área de Pintura puede vivir
     * en planta y en obra.
     *
     * `activo` en vez de borrado: sacar un área de los desplegables no debe
     * tocar los artículos que ya la mencionan.
     */
    public function up(): void
    {
        Schema::create('alm_areas', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alm_areas');
    }
};
