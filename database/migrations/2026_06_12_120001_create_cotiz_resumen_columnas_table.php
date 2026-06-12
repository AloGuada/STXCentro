<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotiz_resumen_columnas', function (Blueprint $table) {
            // Una columna del Resumen por obra (auto-generada: UNA por tarjeta). M038+.
            $table->id();
            $table->foreignId('obra_id')->constrained('cotiz_obras')->cascadeOnDelete();
            $table->string('nombre');
            $table->unsignedInteger('orden')->default(0);
            $table->decimal('sueldo_mo_pza', 16, 4)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotiz_resumen_columnas');
    }
};
