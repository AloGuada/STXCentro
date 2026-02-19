<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('costos_entregas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('factura_id')->constrained('costos_facturas');
            $table->foreignUuid('recibido_por')->constrained('usuarios');
            $table->date('fecha_entrega');
            $table->text('observaciones')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('costos_entregas');
    }
};
