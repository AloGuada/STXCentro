<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El muestreo AQL de un lote de piezas iguales en 1ª.
     *
     * Se guardan la aceptación y el rechazo vigentes al capturar, no sólo el
     * nivel: si mañana cambia la tabla de muestreo, el lote de hoy tiene que
     * seguir diciendo contra qué se aceptó.
     *
     * `veredicto` nulo es «en curso»: todavía no se miró la muestra completa.
     */
    public function up(): void
    {
        Schema::create('qal_muestreos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inspeccion_id')->unique()->constrained('qal_inspecciones')->cascadeOnDelete();
            $table->unsignedInteger('tamano_lote');
            $table->string('nivel', 3);
            $table->unsignedInteger('muestra');
            $table->unsignedInteger('aceptacion');
            $table->unsignedInteger('rechazo');
            $table->unsignedInteger('conformes')->default(0);
            $table->unsignedInteger('rechazadas')->default(0);
            $table->string('veredicto', 10)->nullable();
            $table->string('disposicion', 120)->nullable();
            $table->text('detalle_fallas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_muestreos');
    }
};
