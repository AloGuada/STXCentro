<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Configuración (fila única) del módulo de Producción. Por ahora sólo el
     * salario mínimo diario, base del pago garantizado por día asistido.
     */
    public function up(): void
    {
        Schema::create('prod_configuracion', function (Blueprint $table) {
            $table->id();
            $table->decimal('salario_minimo_diario', 10, 2)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prod_configuracion');
    }
};
