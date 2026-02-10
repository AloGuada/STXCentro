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
        Schema::table('prod_pagos_extra', function (Blueprint $table) {
            $table->dropColumn('monto');
            $table->decimal('precio', 10, 2)->default(0)->after('descripcion');
            $table->integer('dias')->default(1)->after('precio');
            $table->integer('personas')->default(1)->after('dias');
        });
    }

    public function down(): void
    {
        Schema::table('prod_pagos_extra', function (Blueprint $table) {
            $table->dropColumn(['precio', 'dias', 'personas']);
            $table->decimal('monto', 10, 2)->default(0)->after('descripcion');
        });
    }
};
