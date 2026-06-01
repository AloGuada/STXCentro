<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rh_onboarding_tareas', function (Blueprint $table) {
            $table->string('etapa')->nullable()->after('descripcion');
            $table->string('responsable_sugerido')->nullable()->after('etapa');
            $table->string('duracion_estimada')->nullable()->after('responsable_sugerido');
        });
    }

    public function down(): void
    {
        Schema::table('rh_onboarding_tareas', function (Blueprint $table) {
            $table->dropColumn(['etapa', 'responsable_sugerido', 'duracion_estimada']);
        });
    }
};
