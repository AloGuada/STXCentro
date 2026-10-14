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
        Schema::table('sti_asignacion_activos', function (Blueprint $table) {
            $table->string('firma_token', 64)->nullable()->unique()->after('firma_empleado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sti_asignacion_activos', function (Blueprint $table) {
            $table->dropUnique(['firma_token']);
            $table->dropColumn('firma_token');
        });
    }
};
