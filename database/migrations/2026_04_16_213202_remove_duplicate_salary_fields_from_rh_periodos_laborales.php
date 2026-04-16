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
            $table->dropColumn(['salario_diario_fiscal', 'sueldo_normal']);
        });
    }

    public function down(): void
    {
        Schema::table('rh_periodos_laborales', function (Blueprint $table) {
            $table->decimal('sueldo_normal', 12, 2)->nullable()->after('sueldo_mensual');
            $table->decimal('salario_diario_fiscal', 12, 2)->nullable()->after('sueldo_real');
        });
    }
};
