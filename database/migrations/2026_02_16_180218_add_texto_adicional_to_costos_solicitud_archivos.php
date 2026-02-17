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
        Schema::table('costos_solicitud_archivos', function (Blueprint $table) {
            $table->string('texto_adicional')->nullable()->after('nombre_original');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('costos_solicitud_archivos', function (Blueprint $table) {
            $table->dropColumn('texto_adicional');
        });
    }
};
