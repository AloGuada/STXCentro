<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cob_estimacion_estado_historial', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimacion_id')->constrained('cob_estimaciones')->cascadeOnDelete();
            $table->string('estado_anterior')->nullable();
            $table->string('estado_nuevo');
            $table->string('folio')->nullable();
            $table->foreignUuid('usuario_id')->constrained('usuarios');
            $table->text('comentario')->nullable();
            $table->timestamp('fecha_cambio');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cob_estimacion_estado_historial');
    }
};
