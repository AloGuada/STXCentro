<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cal_etapas', function (Blueprint $table) {
            $table->dropForeign(['obra_id']);
            $table->foreign('obra_id')->references('id')->on('cal_obras')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cal_etapas', function (Blueprint $table) {
            $table->dropForeign(['obra_id']);
            $table->foreign('obra_id')->references('id')->on('obras')->cascadeOnDelete();
        });
    }
};
