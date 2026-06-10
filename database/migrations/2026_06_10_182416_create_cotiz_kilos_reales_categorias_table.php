<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotiz_kilos_reales_categorias', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion')->unique();
            $table->string('tipo_corte');
            $table->integer('orden')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cotiz_kilos_reales_categorias');
    }
};
