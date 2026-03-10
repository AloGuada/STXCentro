<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drive_carpeta_accesos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carpeta_id')->constrained('drive_carpetas')->cascadeOnDelete();
            $table->foreignId('externo_id')->constrained('drive_externos')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['carpeta_id', 'externo_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_carpeta_accesos');
    }
};
