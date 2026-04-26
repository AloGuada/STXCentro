<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costos_requisicion_detalle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisicion_id')->constrained('costos_requisiciones')->cascadeOnDelete();
            $table->string('descripcion');
            $table->string('unidad', 20)->default('pza');
            $table->decimal('cantidad', 14, 2);
            $table->text('notas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_requisicion_detalle');
    }
};
