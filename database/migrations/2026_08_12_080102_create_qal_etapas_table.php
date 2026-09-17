<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Etapas de una obra de Calidad. Agrupan las piezas que se inspeccionan.
     *
     * `obra_id` apunta a `qal_obras`, no a `obras` del core: en `cal_etapas`
     * empezó apuntando al core y hubo que corregirlo con una migración que
     * vació las tablas. Aquí nace apuntando a donde debe.
     */
    public function up(): void
    {
        Schema::create('qal_etapas', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion');
            $table->foreignId('obra_id')->constrained('qal_obras')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_etapas');
    }
};
