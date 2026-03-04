<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_onboarding', function (Blueprint $table) {
            $table->id();
            $table->foreignId('periodo_id')->constrained('rh_periodos_laborales')->cascadeOnDelete();
            $table->date('fecha_inicio')->nullable();
            $table->integer('progreso')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_onboarding');
    }
};
