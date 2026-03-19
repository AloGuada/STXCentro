<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Limpiar datos existentes que apuntan a obras (se repoblan con el seeder)
        DB::table('cal_flechas')->delete();
        DB::table('cal_reportes')->delete();
        DB::table('cal_piezas_planos')->delete();
        DB::table('cal_piezas')->delete();
        DB::table('cal_etapas')->delete();

        Schema::table('cal_etapas', function (Blueprint $table) {
            $table->dropForeign(['obra_id']);
            $table->foreign('obra_id')->references('id')->on('cal_obras')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cal_etapas', function (Blueprint $table) {
            $table->dropForeign(['obra_id']);
            $table->foreign('obra_id')->references('id')->on('obras')->cascadeOnDelete();
        });
    }
};
