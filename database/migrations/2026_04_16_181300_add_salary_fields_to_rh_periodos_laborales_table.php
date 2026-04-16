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
            $table->decimal('sueldo_normal', 12, 2)->nullable()->after('sueldo_mensual');
            $table->decimal('sueldo_real', 12, 2)->nullable()->after('sueldo_normal');
            $table->decimal('salario_diario_fiscal', 12, 2)->nullable()->after('sueldo_real');
            $table->string('periodicidad_pago')->nullable()->after('salario_diario_fiscal');
            $table->string('tipo_salario')->nullable()->after('periodicidad_pago');
        });
    }

    public function down(): void
    {
        Schema::table('rh_periodos_laborales', function (Blueprint $table) {
            $table->dropColumn(['sueldo_normal', 'sueldo_real', 'salario_diario_fiscal', 'periodicidad_pago', 'tipo_salario']);
        });
    }
};
