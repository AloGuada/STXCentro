<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cob_estimaciones_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('estimacion_id')->constrained('cob_estimaciones')->cascadeOnDelete();
            $table->decimal('monto_pagado', 15, 2);
            $table->date('fecha_pago');
            $table->string('folio')->nullable();
            $table->string('comprobante')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cob_estimaciones_pagos');
    }
};
