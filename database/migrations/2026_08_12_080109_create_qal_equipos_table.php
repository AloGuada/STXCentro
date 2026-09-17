<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Máquinas de corte y habilitado que se eligen al capturar 1ª transformación.
     *
     * `activo` en vez de borrado: sacar un equipo de los desplegables no debe
     * tocar los registros que ya lo mencionan. Es la regla de todos los
     * catálogos de Calidad y viene del sistema que se está migrando.
     */
    public function up(): void
    {
        Schema::create('qal_equipos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_equipos');
    }
};
