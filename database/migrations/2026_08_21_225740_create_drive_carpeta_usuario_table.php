<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drive_carpeta_usuario', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carpeta_id')->constrained('drive_carpetas')->cascadeOnDelete();
            $table->uuid('usuario_id');
            $table->boolean('puede_escribir')->default(false);
            $table->timestamps();

            $table->foreign('usuario_id')->references('id')->on('usuarios')->cascadeOnDelete();
            $table->unique(['carpeta_id', 'usuario_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_carpeta_usuario');
    }
};
