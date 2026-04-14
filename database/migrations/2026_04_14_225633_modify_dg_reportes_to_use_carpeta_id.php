<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // El módulo DG es nuevo; se permite truncar datos existentes al pivotar de departamento a carpeta
        DB::table('dg_reporte_archivos')->delete();
        DB::table('dg_reportes')->delete();

        if (Schema::hasColumn('dg_reportes', 'departamento_id')) {
            Schema::table('dg_reportes', function (Blueprint $table) {
                try {
                    $table->dropUnique(['departamento_id', 'anio', 'semana']);
                } catch (\Throwable $e) {
                    // índice ya eliminado
                }
                try {
                    $table->dropForeign(['departamento_id']);
                } catch (\Throwable $e) {
                    // FK ya eliminada
                }
                $table->dropColumn('departamento_id');
            });
        }

        Schema::table('dg_reportes', function (Blueprint $table) {
            $table->foreignId('carpeta_id')->after('id')->constrained('dg_carpetas')->cascadeOnDelete();
            $table->unique(['carpeta_id', 'anio', 'semana']);
        });
    }

    public function down(): void
    {
        Schema::table('dg_reportes', function (Blueprint $table) {
            $table->dropUnique(['carpeta_id', 'anio', 'semana']);
            $table->dropForeign(['carpeta_id']);
            $table->dropColumn('carpeta_id');
        });

        Schema::table('dg_reportes', function (Blueprint $table) {
            $table->foreignId('departamento_id')->after('id')->constrained('departamentos')->cascadeOnDelete();
            $table->unique(['departamento_id', 'anio', 'semana']);
        });
    }
};
