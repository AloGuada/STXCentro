<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Los planos de una pieza. Se versionan porque ingeniería los reemite y el
     * reporte tiene que decir contra cuál se inspeccionó.
     */
    public function up(): void
    {
        Schema::create('qal_piezas_planos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pieza_id')->constrained('qal_piezas')->cascadeOnDelete();
            $table->string('pdf_path')->nullable();
            $table->string('plano_normal')->nullable();
            $table->string('dwg_path')->nullable();
            $table->integer('version')->default(1);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_piezas_planos');
    }
};
