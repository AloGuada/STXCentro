<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rh_requisiciones', function (Blueprint $table) {
            $table->id();
            $table->string('folio')->unique();
            $table->foreignId('puesto_id')->constrained('rh_puestos');
            $table->integer('cantidad')->default(1);
            $table->enum('estado', ['borrador', 'abierta', 'en_proceso', 'cerrada', 'cancelada'])->default('borrador');
            $table->enum('tipo_requisicion', ['nueva', 'reemplazo', 'temporal'])->default('nueva');
            $table->text('justificacion')->nullable();
            $table->string('nombre_solicitante')->nullable();
            $table->string('puesto_solicitante')->nullable();
            $table->string('responsable_entrevista')->nullable();
            $table->decimal('salario', 12, 2)->nullable();
            $table->date('fecha_creacion')->nullable();
            $table->date('fecha_cierre')->nullable();
            $table->unsignedBigInteger('solicitada_por_periodo_id')->nullable();
            $table->foreign('solicitada_por_periodo_id')->references('id')->on('rh_periodos_laborales')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rh_requisiciones');
    }
};
