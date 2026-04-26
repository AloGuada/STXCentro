<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costos_requisiciones', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->foreignUuid('solicitante_id')->constrained('usuarios');
            $table->foreignId('departamento_id')->constrained('departamentos');
            $table->string('concepto');
            $table->text('justificacion')->nullable();
            $table->date('fecha_requerida')->nullable();
            $table->string('estatus')->default('borrador');
            $table->text('motivo_rechazo')->nullable();
            $table->foreignUuid('locked_by')->nullable()->constrained('usuarios')->nullOnDelete();
            $table->timestamp('locked_at')->nullable();
            $table->timestamps();

            $table->index(['estatus', 'departamento_id']);
            $table->index('solicitante_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_requisiciones');
    }
};
