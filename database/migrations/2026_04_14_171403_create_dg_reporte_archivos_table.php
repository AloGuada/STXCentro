<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dg_reporte_archivos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporte_id')->constrained('dg_reportes')->cascadeOnDelete();
            $table->string('nombre_original');
            $table->string('path');
            $table->string('mime')->nullable();
            $table->unsignedBigInteger('size')->nullable();
            $table->uuid('subido_por_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('subido_por_id')->references('id')->on('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dg_reporte_archivos');
    }
};
