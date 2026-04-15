<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dg_notas', function (Blueprint $table) {
            $table->id();
            $table->uuid('usuario_id');
            $table->string('titulo')->default('Sin título');
            $table->longText('contenido')->nullable();
            $table->timestamps();

            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
            $table->index('usuario_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dg_notas');
    }
};
