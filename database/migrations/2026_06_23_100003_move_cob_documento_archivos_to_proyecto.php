<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cob_documento_archivos', function (Blueprint $table) {
            $table->foreignId('proyecto_id')->nullable()->after('id')->constrained('proyectos')->cascadeOnDelete();
        });

        foreach (DB::table('cob_documento_archivos')->whereNotNull('obra_id')->get(['id', 'obra_id']) as $row) {
            DB::table('cob_documento_archivos')->where('id', $row->id)->update([
                'proyecto_id' => DB::table('obras')->where('id', $row->obra_id)->value('proyecto_id'),
            ]);
        }

        Schema::table('cob_documento_archivos', function (Blueprint $table) {
            $table->dropIndex(['obra_id', 'seccion_id', 'carpeta_id']);
        });
        Schema::table('cob_documento_archivos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('obra_id');
        });
        Schema::table('cob_documento_archivos', function (Blueprint $table) {
            $table->index(['proyecto_id', 'seccion_id', 'carpeta_id']);
        });
    }

    public function down(): void
    {
        Schema::table('cob_documento_archivos', function (Blueprint $table) {
            $table->dropIndex(['proyecto_id', 'seccion_id', 'carpeta_id']);
        });
        Schema::table('cob_documento_archivos', function (Blueprint $table) {
            $table->foreignId('obra_id')->nullable()->after('id')->constrained('obras')->cascadeOnDelete();
        });
        Schema::table('cob_documento_archivos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proyecto_id');
            $table->index(['obra_id', 'seccion_id', 'carpeta_id']);
        });
    }
};
