<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotiz_resumen_columna_tarjetas', function (Blueprint $table) {
            // Vínculo 1:1 columna ↔ tarjeta. UNIQUE(tarjeta) → cada tarjeta en exactamente una columna.
            $table->id();
            $table->foreignId('columna_id')->constrained('cotiz_resumen_columnas')->cascadeOnDelete();
            $table->foreignId('tarjeta_id')->constrained('cotiz_tarjetas')->cascadeOnDelete();
            $table->timestamps();

            $table->unique('tarjeta_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotiz_resumen_columna_tarjetas');
    }
};
