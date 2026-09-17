<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * La pieza que se inspecciona, identificada por su marca.
     */
    public function up(): void
    {
        Schema::create('qal_piezas', function (Blueprint $table) {
            $table->id();
            $table->string('marca');
            $table->integer('cantidad')->default(1);
            $table->foreignId('etapa_id')->constrained('qal_etapas')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_piezas');
    }
};
