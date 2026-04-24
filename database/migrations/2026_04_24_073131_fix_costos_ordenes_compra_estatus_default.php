<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('costos_ordenes_compra')
            ->where('estatus', 'borrador')
            ->update(['estatus' => 'pendiente_factura']);

        Schema::table('costos_ordenes_compra', function (Blueprint $table) {
            $table->string('estatus')->default('pendiente_factura')->change();
        });
    }

    public function down(): void
    {
        Schema::table('costos_ordenes_compra', function (Blueprint $table) {
            $table->string('estatus')->default('borrador')->change();
        });
    }
};
