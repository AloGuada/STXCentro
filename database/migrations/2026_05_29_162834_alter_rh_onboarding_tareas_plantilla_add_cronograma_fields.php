<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rh_onboarding_tareas_plantilla', function (Blueprint $table) {
            $table->string('etapa')->nullable()->after('descripcion');
            $table->string('responsable')->nullable()->after('etapa');
            $table->string('duracion_estimada')->nullable()->after('responsable');
        });
    }

    public function down(): void
    {
        Schema::table('rh_onboarding_tareas_plantilla', function (Blueprint $table) {
            $table->dropColumn(['etapa', 'responsable', 'duracion_estimada']);
        });
    }
};
