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
        Schema::create('cal_piezas_planos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pieza_id')->constrained('cal_piezas')->cascadeOnDelete();
            $table->string('pdf_path')->nullable();
            $table->string('plano_normal')->nullable();
            $table->string('dwg_path')->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cal_piezas_planos');
    }
};
