<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drive_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('carpeta_id')->constrained('drive_carpetas')->cascadeOnDelete();
            $table->string('nombre_original');
            $table->string('path');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->string('descripcion')->nullable();
            $table->string('subido_por_type');
            $table->unsignedBigInteger('subido_por_id');
            $table->string('link_token')->nullable()->unique();
            $table->timestamp('link_expira_en')->nullable();
            $table->timestamp('auto_eliminar_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drive_archivos');
    }
};
