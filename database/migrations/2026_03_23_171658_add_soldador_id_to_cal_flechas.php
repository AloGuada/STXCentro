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
        Schema::table('cal_flechas', function (Blueprint $table) {
            $table->foreignId('soldador_id')->nullable()->after('pagina')->constrained('cal_soldadores')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('cal_flechas', function (Blueprint $table) {
            $table->dropForeign(['soldador_id']);
            $table->dropColumn('soldador_id');
        });
    }
};
