<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('cal_piezas', function (Blueprint $table) {
            $table->id();
            $table->string('marca');
            $table->integer('cantidad')->default(1);
            $table->foreignId('etapa_id')->constrained('cal_etapas')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cal_piezas');
    }
};
