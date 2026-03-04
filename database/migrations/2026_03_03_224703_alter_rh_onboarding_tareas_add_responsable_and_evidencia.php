<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rh_onboarding_tareas', function (Blueprint $table) {
            $table->foreignId('responsable_periodo_id')->nullable()->after('onboarding_id')->constrained('rh_periodos_laborales')->nullOnDelete();
            $table->string('evidencia_ruta')->nullable()->after('fecha_completada');
        });
    }

    public function down(): void
    {
        Schema::table('rh_onboarding_tareas', function (Blueprint $table) {
            $table->dropConstrainedForeignId('responsable_periodo_id');
            $table->dropColumn('evidencia_ruta');
        });
    }
};
