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
        Schema::table('obras', function (Blueprint $table) {
            $table->foreignId('proyecto_id')->nullable()->after('id')->constrained('proyectos')->nullOnDelete();
            $table->foreignId('obra_padre_id')->nullable()->after('proyecto_id')->constrained('obras')->nullOnDelete();
            $table->string('tipo')->default('base')->after('obra_padre_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('obras', function (Blueprint $table) {
            $table->dropConstrainedForeignId('proyecto_id');
            $table->dropConstrainedForeignId('obra_padre_id');
            $table->dropColumn('tipo');
        });
    }
};
