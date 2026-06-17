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
        Schema::table('cob_partidas', function (Blueprint $table) {
            $table->dropColumn(['es_adicional', 'numero_adicional', 'es_subobra']);
        });
    }

    public function down(): void
    {
        Schema::table('cob_partidas', function (Blueprint $table) {
            $table->boolean('es_adicional')->default(false);
            $table->unsignedInteger('numero_adicional')->nullable();
            $table->boolean('es_subobra')->default(false);
        });
    }
};
