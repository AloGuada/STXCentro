<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // First, add a temporary column for the conversion
        Schema::table('sti_equipos', function (Blueprint $table) {
            $table->unsignedTinyInteger('factor_criticidad_new')->default(1)->after('factor_criticidad');
        });

        // Convert existing string values to integers
        // bajo=1, medio=2, alto=3, critico=4
        DB::table('sti_equipos')->where('factor_criticidad', 'bajo')->update(['factor_criticidad_new' => 1]);
        DB::table('sti_equipos')->where('factor_criticidad', 'medio')->update(['factor_criticidad_new' => 2]);
        DB::table('sti_equipos')->where('factor_criticidad', 'alto')->update(['factor_criticidad_new' => 3]);
        DB::table('sti_equipos')->where('factor_criticidad', 'critico')->update(['factor_criticidad_new' => 4]);

        // Drop old column and rename new one
        Schema::table('sti_equipos', function (Blueprint $table) {
            $table->dropColumn('factor_criticidad');
        });

        Schema::table('sti_equipos', function (Blueprint $table) {
            $table->renameColumn('factor_criticidad_new', 'factor_criticidad');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Add temporary string column
        Schema::table('sti_equipos', function (Blueprint $table) {
            $table->string('factor_criticidad_old', 20)->default('bajo')->after('factor_criticidad');
        });

        // Convert back to strings
        DB::table('sti_equipos')->where('factor_criticidad', 1)->update(['factor_criticidad_old' => 'bajo']);
        DB::table('sti_equipos')->where('factor_criticidad', 2)->update(['factor_criticidad_old' => 'medio']);
        DB::table('sti_equipos')->where('factor_criticidad', 3)->update(['factor_criticidad_old' => 'alto']);
        DB::table('sti_equipos')->where('factor_criticidad', 4)->update(['factor_criticidad_old' => 'critico']);

        // Drop integer column and rename string column
        Schema::table('sti_equipos', function (Blueprint $table) {
            $table->dropColumn('factor_criticidad');
        });

        Schema::table('sti_equipos', function (Blueprint $table) {
            $table->renameColumn('factor_criticidad_old', 'factor_criticidad');
        });
    }
};
