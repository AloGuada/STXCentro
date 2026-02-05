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
            $table->string('factor_criticidad')->default('medio')->after('marca');
            $table->unsignedInteger('periodicidad_mantenimiento')->nullable()->after('factor_criticidad');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sti_equipos', function (Blueprint $table) {
            $table->dropColumn(['factor_criticidad', 'periodicidad_mantenimiento']);
        });
    }
};
