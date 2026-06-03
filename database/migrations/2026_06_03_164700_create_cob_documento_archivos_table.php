<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cob_documento_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obra_id')->constrained('obras')->cascadeOnDelete();
            $table->foreignId('seccion_id')->constrained('cob_documento_secciones')->cascadeOnDelete();
            $table->foreignId('carpeta_id')->nullable()->constrained('cob_documento_carpetas')->cascadeOnDelete();
            $table->string('nombre_original');
            $table->string('path');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->uuid('subido_por_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('subido_por_id')->references('id')->on('usuarios')->nullOnDelete();
            $table->index(['obra_id', 'seccion_id', 'carpeta_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cob_documento_archivos');
    }
};
