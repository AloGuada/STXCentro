<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('sti_mantenimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('equipo_id')->constrained('sti_equipos')->cascadeOnDelete();
            $table->string('tipo');
            $table->dateTime('fecha_programada');
            $table->text('descripcion')->nullable();
            $table->foreignId('tecnico_id')->constrained('sti_tecnicos')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sti_mantenimientos');
    }
};
