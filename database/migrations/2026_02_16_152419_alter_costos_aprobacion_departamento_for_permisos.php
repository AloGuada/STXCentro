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
        Schema::table('costos_aprobacion_departamento', function (Blueprint $table) {
            $table->foreignId('permiso_id')->after('departamento_id')->constrained('costos_permisos')->cascadeOnDelete();
            $table->dropForeign(['aprobador_id']);
            $table->dropColumn(['nivel', 'nombre_nivel', 'activo']);
            $table->foreignUuid('aprobador_id')->nullable()->change();
            $table->foreign('aprobador_id')->references('id')->on('usuarios')->nullOnDelete();
            $table->unique(['departamento_id', 'permiso_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('costos_aprobacion_departamento', function (Blueprint $table) {
            $table->dropUnique(['departamento_id', 'permiso_id']);
            $table->dropForeign(['permiso_id']);
            $table->dropColumn('permiso_id');
            $table->integer('nivel')->default(1);
            $table->string('nombre_nivel')->default('');
            $table->boolean('activo')->default(true);
        });
    }
};
