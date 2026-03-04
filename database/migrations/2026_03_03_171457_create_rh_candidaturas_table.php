<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_candidaturas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisicion_id')->constrained('rh_requisiciones')->cascadeOnDelete();
            $table->foreignId('persona_id')->constrained('rh_personas')->cascadeOnDelete();
            $table->date('fecha_aplicacion')->nullable();
            $table->decimal('porcentaje_match', 5, 2)->nullable();
            $table->decimal('porcentaje_skills', 5, 2)->nullable();
            $table->decimal('porcentaje_requisitos', 5, 2)->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->unique(['requisicion_id', 'persona_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_candidaturas');
    }
};
