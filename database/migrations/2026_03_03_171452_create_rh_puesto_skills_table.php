<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_puesto_skills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('puesto_id')->constrained('rh_puestos')->cascadeOnDelete();
            $table->foreignId('skill_id')->constrained('rh_skills')->cascadeOnDelete();
            $table->enum('nivel_requerido', ['basico', 'intermedio', 'avanzado'])->default('basico');
            $table->timestamps();

            $table->unique(['puesto_id', 'skill_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_puesto_skills');
    }
};
