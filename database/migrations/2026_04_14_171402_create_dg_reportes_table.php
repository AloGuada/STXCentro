<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dg_reportes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('departamento_id')->constrained('departamentos')->cascadeOnDelete();
            $table->unsignedSmallInteger('anio');
            $table->unsignedTinyInteger('semana');
            $table->uuid('creado_por_id')->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamps();

            $table->foreign('creado_por_id')->references('id')->on('usuarios')->nullOnDelete();
            $table->unique(['departamento_id', 'anio', 'semana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dg_reportes');
    }
};
