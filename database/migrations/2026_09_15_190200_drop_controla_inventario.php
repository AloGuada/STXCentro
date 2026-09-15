<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Todo lleva kardex: la bandera ya no decide nada y se retira para que nadie
 * la vuelva a consultar. Tener artículo es llevar kardex, y desde la migración
 * anterior todo item lo tiene.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('costos_productos', function (Blueprint $table) {
            $table->dropColumn('controla_inventario');
        });
    }

    public function down(): void
    {
        Schema::table('costos_productos', function (Blueprint $table) {
            $table->boolean('controla_inventario')->default(true);
        });
    }
};
