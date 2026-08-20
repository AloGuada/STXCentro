<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las fotos que acompañan al informe de PND.
     *
     * Van aparte del PDF original —que vive en `qal_pnd_reportes.archivo_pdf`—
     * porque son cosas distintas: el PDF es el documento firmado que se entrega
     * en el dosier, y las fotos son la evidencia de lo que se vio, que a veces
     * llega suelta por mensajería.
     */
    public function up(): void
    {
        Schema::create('qal_pnd_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qal_pnd_reporte_id')->constrained('qal_pnd_reportes')->cascadeOnDelete();
            $table->string('ruta');
            $table->string('nombre')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qal_pnd_fotos');
    }
};
