<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cob_comparativos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obra_id')->constrained('obras')->cascadeOnDelete();
            $table->text('descripcion');
            $table->decimal('monto_impacto', 15, 2)->default(0);
            $table->date('fecha_identificacion')->nullable();
            $table->string('estado')->default('analisis');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cob_comparativos');
    }
};
