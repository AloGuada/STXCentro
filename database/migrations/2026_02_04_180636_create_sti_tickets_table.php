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
        Schema::create('sti_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_solicitante');
            $table->text('comentario');
            $table->foreignId('tecnico_id')->nullable()->constrained('sti_tecnicos')->nullOnDelete();
            $table->foreignId('equipo_id')->nullable()->constrained('sti_equipos')->nullOnDelete();
            $table->foreignId('departamento_id')->constrained('departamentos')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sti_tickets');
    }
};
