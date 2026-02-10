<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prod_fabricados', function (Blueprint $table) {
            $table->foreignId('dest_grupo_id')->nullable()->after('destajo_id')->constrained('prod_grupos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prod_fabricados', function (Blueprint $table) {
            $table->dropForeign(['dest_grupo_id']);
            $table->dropColumn('dest_grupo_id');
        });
    }
};
