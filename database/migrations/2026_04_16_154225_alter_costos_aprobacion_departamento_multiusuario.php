<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_aprobacion_departamento', function (Blueprint $table) {
            $table->dropUnique(['departamento_id', 'permiso_id']);
            $table->unique(['departamento_id', 'permiso_id', 'aprobador_id'], 'costos_aprobacion_dept_permiso_aprobador_unique');
        });
    }

    public function down(): void
    {
        Schema::table('costos_aprobacion_departamento', function (Blueprint $table) {
            $table->dropUnique('costos_aprobacion_dept_permiso_aprobador_unique');
            $table->unique(['departamento_id', 'permiso_id']);
        });
    }
};
