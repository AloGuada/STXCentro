<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configuración (fila única) del módulo de Calidad.
     *
     * `formularios_segun_avance` limita lo que se captura en Formularios a lo
     * que Producción programó. Nace encendido; apagado, se inspecciona
     * cualquier pieza del catálogo, como antes.
     */
    public function up(): void
    {
        Schema::create('qal_configuracion', function (Blueprint $table) {
            $table->id();
            $table->boolean('formularios_segun_avance')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_configuracion');
    }
};
