<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Se eliminan del flujo los estados 'pendiente', 'generada' y 'revisada'.
        // Las estimaciones que estén en alguno de ellos pasan al nuevo primer
        // estado del flujo: 'ingresada'.
        DB::table('cob_estimaciones')
            ->whereIn('estado', ['pendiente', 'generada', 'revisada'])
            ->update(['estado' => 'ingresada']);

        Schema::table('cob_estimaciones', function (Blueprint $table) {
            $table->string('estado')->default('ingresada')->change();
        });
    }

    public function down(): void
    {
        Schema::table('cob_estimaciones', function (Blueprint $table) {
            $table->string('estado')->default('pendiente')->change();
        });
        // El estado original de las estimaciones migradas no se puede restaurar.
    }
};
