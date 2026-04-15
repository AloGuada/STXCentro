<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dg_reporte_archivos', function (Blueprint $table) {
            $table->timestamp('visto_por_dg_en')->nullable()->after('notas_actualizado_en');
        });
    }

    public function down(): void
    {
        Schema::table('dg_reporte_archivos', function (Blueprint $table) {
            $table->dropColumn('visto_por_dg_en');
        });
    }
};
