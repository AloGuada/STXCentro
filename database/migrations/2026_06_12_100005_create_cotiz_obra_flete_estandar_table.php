<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotiz_obra_flete_estandar', function (Blueprint $table) {
            // Por obra, lista de tarjetas con sus parámetros de camionaje (EXPL. M.O rows 89-117).
            // metodo='por_kg': camiones = ROUNDUP(vol_kg / kg_por_camion, 2)
            // metodo='por_piezas': pzas = CEIL(vol_ml / ml_por_pza); camiones = ROUNDUP(pzas / pzas_por_camion, 2)
            $table->id();
            $table->foreignId('obra_id')->constrained('cotiz_obras')->cascadeOnDelete();
            $table->foreignId('tarjeta_id')->constrained('cotiz_tarjetas')->cascadeOnDelete();
            $table->string('metodo')->default('por_kg');
            $table->string('grupo')->nullable();
            $table->decimal('volumen_override', 16, 4)->nullable();
            $table->decimal('kg_por_camion', 16, 4)->default(15000);
            $table->unsignedInteger('pzas_por_camion')->default(400);
            $table->decimal('ml_por_pza', 16, 4)->default(3.05);
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();

            $table->unique(['obra_id', 'tarjeta_id', 'metodo']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotiz_obra_flete_estandar');
    }
};
