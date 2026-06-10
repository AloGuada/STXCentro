<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotiz_insumos', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion');
            $table->string('codigo_stumis')->nullable();
            $table->foreignId('unidad_id')->constrained('cotiz_unidades');
            $table->decimal('precio_unitario', 14, 4)->default(0);
            $table->decimal('peso_lineal', 14, 6)->nullable();
            $table->decimal('peso_default', 14, 6)->nullable();
            $table->foreignId('centro_costo_id')->constrained('cotiz_centros_costos');
            $table->foreignId('categoria_tarjeta_id')->nullable()->constrained('cotiz_categorias_tarjeta')->nullOnDelete();
            $table->timestamps();

            $table->unique('descripcion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotiz_insumos');
    }
};
