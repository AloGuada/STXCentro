<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prod_tipos', function (Blueprint $table) {
            $table->boolean('es_descuento')->default(false)->after('desgloce');
        });
    }

    public function down(): void
    {
        Schema::table('prod_tipos', function (Blueprint $table) {
            $table->dropColumn('es_descuento');
        });
    }
};
