<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tipos de pieza, identificados por el prefijo que usa ingeniería en la marca.
     *
     * El prefijo es lo que hace que el tipo se deduzca solo al capturar: si la
     * marca es `PIP-TP12-3`, el prefijo `TP` la resuelve como trabe principal
     * sin que el inspector elija nada. Por eso es único y por eso sólo se
     * cambia de acuerdo con ingeniería.
     */
    public function up(): void
    {
        Schema::create('qal_tipos_pieza', function (Blueprint $table) {
            $table->id();
            $table->string('prefijo')->unique();
            $table->string('descripcion');
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_tipos_pieza');
    }
};
