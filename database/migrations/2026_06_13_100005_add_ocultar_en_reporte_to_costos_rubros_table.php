<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_rubros', function (Blueprint $table) {
            $table->boolean('ocultar_en_reporte')->default(false)->after('ambito');
        });
    }

    public function down(): void
    {
        Schema::table('costos_rubros', function (Blueprint $table) {
            $table->dropColumn('ocultar_en_reporte');
        });
    }
};
