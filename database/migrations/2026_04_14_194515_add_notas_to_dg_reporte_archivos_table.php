<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dg_reporte_archivos', function (Blueprint $table) {
            $table->longText('notas')->nullable();
            $table->uuid('notas_editado_por_id')->nullable();
            $table->timestamp('notas_actualizado_en')->nullable();

            $table->foreign('notas_editado_por_id')->references('id')->on('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dg_reporte_archivos', function (Blueprint $table) {
            $table->dropForeign(['notas_editado_por_id']);
            $table->dropColumn(['notas', 'notas_editado_por_id', 'notas_actualizado_en']);
        });
    }
};
