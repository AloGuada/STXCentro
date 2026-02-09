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
        Schema::table('sti_planes', function (Blueprint $table) {
            $table->dropForeign(['equipo_id']);
            $table->dropColumn('equipo_id');
        });
    }

    public function down(): void
    {
        Schema::table('sti_planes', function (Blueprint $table) {
            $table->foreignId('equipo_id')->constrained('sti_equipos')->cascadeOnDelete();
        });
    }
};
