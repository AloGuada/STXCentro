<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La clasificación de cada tira de la prueba (5A…0A o 5B…0B). Se usan tres
     * y cada una puede dar distinto.
     */
    public function up(): void
    {
        Schema::create('qal_adherencia_tiras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('adherencia_id')->constrained('qal_adherencia')->cascadeOnDelete();
            $table->unsignedTinyInteger('orden');
            $table->string('clasificacion', 3);

            $table->unique(['adherencia_id', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_adherencia_tiras');
    }
};
