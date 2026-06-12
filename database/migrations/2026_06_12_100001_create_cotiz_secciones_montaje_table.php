<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotiz_secciones_montaje', function (Blueprint $table) {
            // Zona/sección de obra (Nave Principal, Nave Oficinas, Mezzanine).
            $table->id();
            $table->foreignId('obra_id')->constrained('cotiz_obras')->cascadeOnDelete();
            $table->string('nombre');
            $table->decimal('area_m2', 16, 4)->nullable();
            $table->unsignedInteger('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotiz_secciones_montaje');
    }
};
