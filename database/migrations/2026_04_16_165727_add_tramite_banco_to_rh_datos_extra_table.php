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
            $table->boolean('tramite_banco')->default(false)->after('banco_op');
        });
    }

    public function down(): void
    {
        Schema::table('rh_datos_extras', function (Blueprint $table) {
            $table->dropColumn('tramite_banco');
        });
    }
};
