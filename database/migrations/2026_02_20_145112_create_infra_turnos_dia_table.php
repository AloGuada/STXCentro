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
        Schema::create('infra_turnos_dia', function (Blueprint $table) {
            $table->id();
            $table->foreignId('infra_turno_id')->constrained('infra_turnos')->cascadeOnDelete();
            $table->tinyInteger('dia_semana');
            $table->timestamps();

            $table->unique(['infra_turno_id', 'dia_semana']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('infra_turnos_dia');
    }
};
