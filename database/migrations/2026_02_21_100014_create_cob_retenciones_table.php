<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cob_retenciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimacion_id')->constrained('cob_estimaciones')->cascadeOnDelete();
            $table->foreignId('tipo_retencion_id')->constrained('cob_tipos_retenciones');
            $table->decimal('monto', 15, 2)->default(0);
            $table->string('moneda', 3)->default('MXN');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cob_retenciones');
    }
};
