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
        Schema::table('sti_equipos', function (Blueprint $table) {
            $table->dropColumn('periodicidad_mantenimiento');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sti_equipos', function (Blueprint $table) {
            $table->unsignedInteger('periodicidad_mantenimiento')->nullable()->after('factor_criticidad');
        });
    }
};
