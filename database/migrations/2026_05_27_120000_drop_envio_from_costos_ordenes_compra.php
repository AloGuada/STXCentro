<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_ordenes_compra', function (Blueprint $table) {
            $table->dropColumn('envio');
        });
    }

    public function down(): void
    {
        Schema::table('costos_ordenes_compra', function (Blueprint $table) {
            $table->decimal('envio', 14, 2)->default(0)->after('total');
        });
    }
};
