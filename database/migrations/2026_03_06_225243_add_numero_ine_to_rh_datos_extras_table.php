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
        Schema::table('rh_datos_extras', function (Blueprint $table) {
            $table->string('numero_ine')->nullable()->after('rfc');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rh_datos_extras', function (Blueprint $table) {
            $table->dropColumn('numero_ine');
        });
    }
};
