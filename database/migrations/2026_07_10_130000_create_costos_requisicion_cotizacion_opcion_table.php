<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costos_requisicion_cotizacion_opcion', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requisicion_id')->constrained('costos_requisiciones')->cascadeOnDelete();
            $table->foreignId('proveedor_id')->constrained('proveedores');
            // Nombre de la columna-opción; si es null se muestra "Opción {orden}".
            $table->string('etiqueta')->nullable();
            $table->unsignedSmallInteger('orden')->default(1);
            $table->timestamps();

            $table->index(['requisicion_id', 'proveedor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_requisicion_cotizacion_opcion');
    }
};
