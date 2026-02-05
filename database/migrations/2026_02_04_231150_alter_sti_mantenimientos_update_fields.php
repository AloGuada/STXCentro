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
        Schema::table('sti_mantenimientos', function (Blueprint $table) {
            $table->dropColumn('tipo');
        });

        Schema::table('sti_mantenimientos', function (Blueprint $table) {
            $table->string('status')->default('pendiente')->after('tecnico_id');
            $table->date('fecha_realizado')->nullable()->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sti_mantenimientos', function (Blueprint $table) {
            $table->dropColumn(['status', 'fecha_realizado']);
        });

        Schema::table('sti_mantenimientos', function (Blueprint $table) {
            $table->string('tipo')->after('equipo_id');
        });
    }
};
