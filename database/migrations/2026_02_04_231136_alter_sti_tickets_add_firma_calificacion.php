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
        Schema::table('sti_tickets', function (Blueprint $table) {
            $table->text('firma_completado')->nullable()->after('departamento_id');
            $table->unsignedTinyInteger('calificacion')->nullable()->after('firma_completado');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sti_tickets', function (Blueprint $table) {
            $table->dropColumn(['firma_completado', 'calificacion']);
        });
    }
};
