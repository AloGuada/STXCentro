<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Defectos de soldadura que puede marcar el inspector, con su contador.
     *
     * Van en tabla propia y no junto a los de pintura porque son dos listas
     * distintas en el origen y se administran por separado.
     *
     * Cuidado al renombrar uno: los registros ya guardados conservan el texto
     * viejo, así que en los reportes saldrían como dos defectos distintos.
     */
    public function up(): void
    {
        Schema::create('qal_defectos_soldadura', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_defectos_soldadura');
    }
};
