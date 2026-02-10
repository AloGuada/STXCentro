<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prod_tipos', function (Blueprint $table) {
            $table->integer('orden')->default(0)->after('descripcion');
            $table->boolean('desgloce')->default(false)->after('orden');
        });
    }

    public function down(): void
    {
        Schema::table('prod_tipos', function (Blueprint $table) {
            $table->dropColumn(['orden', 'desgloce']);
        });
    }
};
