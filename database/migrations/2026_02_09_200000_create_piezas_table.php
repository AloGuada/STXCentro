<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('piezas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('obra_id')->constrained('obras')->cascadeOnDelete();
            $table->string('marca')->comment('Identificador de la pieza/marca de armado');
            $table->string('descripcion');
            $table->decimal('peso_unitario', 10, 2)->default(0)->comment('kg por pieza');
            $table->integer('cantidad')->default(1)->comment('Cantidad de piezas en la obra');
            $table->decimal('peso_total', 12, 2)->default(0)->comment('peso_unitario * cantidad');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('piezas');
    }
};
