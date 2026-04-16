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
        Schema::table('rh_periodos_laborales', function (Blueprint $table) {
            $table->text('motivo_baja')->nullable()->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('rh_periodos_laborales', function (Blueprint $table) {
            $table->dropColumn('motivo_baja');
        });
    }
};
