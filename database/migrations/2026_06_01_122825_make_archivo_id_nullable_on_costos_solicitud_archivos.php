<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El PDF de la OC generado automáticamente no corresponde a un Documento
     * capturado por el usuario, por lo que archivo_id pasa a ser nullable.
     */
    public function up(): void
    {
        Schema::table('costos_solicitud_archivos', function (Blueprint $table) {
            $table->dropForeign(['archivo_id']);
        });

        Schema::table('costos_solicitud_archivos', function (Blueprint $table) {
            $table->foreignId('archivo_id')
                ->nullable()
                ->change();
            $table->foreign('archivo_id')->references('id')->on('costos_documentos');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('costos_solicitud_archivos', function (Blueprint $table) {
            $table->dropForeign(['archivo_id']);
        });

        Schema::table('costos_solicitud_archivos', function (Blueprint $table) {
            $table->foreignId('archivo_id')
                ->nullable(false)
                ->change();
            $table->foreign('archivo_id')->references('id')->on('costos_documentos');
        });
    }
};
