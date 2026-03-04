<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_skills_demostradas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('persona_id')->constrained('rh_personas')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('rh_skills')->cascadeOnDelete();
            $table->boolean('onboarding')->default(false);
            $table->boolean('cumple')->default(false);
            $table->enum('nivel_alcanzado', ['basico', 'intermedio', 'avanzado'])->nullable();
            $table->text('evidencia')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_skills_demostradas');
    }
};
