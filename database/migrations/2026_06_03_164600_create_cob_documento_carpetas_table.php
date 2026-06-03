<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cob_documento_carpetas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obra_id')->constrained('obras')->cascadeOnDelete();
            $table->foreignId('seccion_id')->constrained('cob_documento_secciones')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('cob_documento_carpetas')->cascadeOnDelete();
            $table->string('nombre');
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->index(['obra_id', 'seccion_id', 'parent_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cob_documento_carpetas');
    }
};
