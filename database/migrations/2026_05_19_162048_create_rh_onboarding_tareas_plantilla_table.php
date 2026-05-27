<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_onboarding_tareas_plantilla', function (Blueprint $table) {
            $table->id();
            $table->foreignId('puesto_id')->constrained('rh_puestos')->cascadeOnDelete();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->integer('dias_desde_inicio')->nullable();
            $table->integer('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_onboarding_tareas_plantilla');
    }
};
