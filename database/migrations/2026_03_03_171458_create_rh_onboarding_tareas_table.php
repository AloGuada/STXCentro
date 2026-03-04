<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_onboarding_tareas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('onboarding_id')->constrained('rh_onboarding')->cascadeOnDelete();
            $table->string('titulo');
            $table->text('descripcion')->nullable();
            $table->boolean('completada')->default(false);
            $table->date('fecha_vencimiento')->nullable();
            $table->date('fecha_completada')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_onboarding_tareas');
    }
};
