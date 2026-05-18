<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('rh_personas', function (Blueprint $table) {
            $table->string('cv_estado_temp', 50)->nullable();
        });

        DB::table('rh_personas')->whereNotNull('cv_estado')->update([
            'cv_estado_temp' => DB::raw(<<<'SQL'
                CASE cv_estado
                    WHEN 'procesado' THEN 'completado'
                    ELSE cv_estado
                END
                SQL),
        ]);

        Schema::table('rh_personas', function (Blueprint $table) {
            $table->dropColumn('cv_estado');
        });

        Schema::table('rh_personas', function (Blueprint $table) {
            $table->renameColumn('cv_estado_temp', 'cv_estado');
        });
    }

    public function down(): void
    {
        Schema::table('rh_personas', function (Blueprint $table) {
            $table->string('cv_estado_old', 50)->nullable();
        });

        DB::table('rh_personas')->whereNotNull('cv_estado')->update([
            'cv_estado_old' => DB::raw(<<<'SQL'
                CASE cv_estado
                    WHEN 'completado' THEN 'procesado'
                    WHEN 'texto_extraido' THEN 'procesando'
                    WHEN 'datos_extraidos' THEN 'procesando'
                    WHEN 'requisitos_procesados' THEN 'procesando'
                    ELSE cv_estado
                END
                SQL),
        ]);

        Schema::table('rh_personas', function (Blueprint $table) {
            $table->dropColumn('cv_estado');
        });

        Schema::table('rh_personas', function (Blueprint $table) {
            $table->enum('cv_estado', ['pendiente', 'procesando', 'procesado', 'error'])->nullable();
        });

        DB::table('rh_personas')->whereNotNull('cv_estado_old')->update([
            'cv_estado' => DB::raw('cv_estado_old'),
        ]);

        Schema::table('rh_personas', function (Blueprint $table) {
            $table->dropColumn('cv_estado_old');
        });
    }
};
