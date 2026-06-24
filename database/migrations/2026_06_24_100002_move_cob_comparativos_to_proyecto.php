<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cob_comparativos', function (Blueprint $table) {
            $table->foreignId('proyecto_id')->nullable()->after('id')->constrained('proyectos')->cascadeOnDelete();
        });

        foreach (DB::table('cob_comparativos')->whereNotNull('obra_id')->get(['id', 'obra_id']) as $row) {
            DB::table('cob_comparativos')->where('id', $row->id)->update([
                'proyecto_id' => DB::table('obras')->where('id', $row->obra_id)->value('proyecto_id'),
            ]);
        }

        Schema::table('cob_comparativos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('obra_id');
        });
    }

    public function down(): void
    {
        Schema::table('cob_comparativos', function (Blueprint $table) {
            $table->foreignId('obra_id')->nullable()->after('id')->constrained('obras')->cascadeOnDelete();
        });

        Schema::table('cob_comparativos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proyecto_id');
        });
    }
};
