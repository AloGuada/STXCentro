<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('prod_cortes', 'prod_destajos');

        Schema::table('prod_destajos', function (Blueprint $table) {
            $table->integer('anio')->nullable()->after('id');
        });

        foreach (DB::table('prod_destajos')->whereNull('anio')->get(['id', 'fecha_inicio']) as $destajo) {
            DB::table('prod_destajos')
                ->where('id', $destajo->id)
                ->update(['anio' => (int) date('Y', strtotime((string) $destajo->fecha_inicio))]);
        }

        Schema::table('prod_destajos', function (Blueprint $table) {
            $table->unique(['anio', 'semana']);
        });

        Schema::table('prod_liquidaciones', function (Blueprint $table) {
            $table->renameColumn('corte_id', 'destajo_id');
        });

        Schema::table('prod_pagos_extra', function (Blueprint $table) {
            $table->renameColumn('corte_id', 'destajo_id');
        });

        // Infra huerfana: prod_extras nunca tuvo controlador ni ruta.
        // Los extras reales viven en prod_pagos_extra.
        Schema::dropIfExists('prod_extras');
    }

    public function down(): void
    {
        Schema::create('prod_extras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('liquidacion_id')->constrained('prod_liquidaciones')->cascadeOnDelete();
            $table->string('descripcion');
            $table->decimal('monto', 14, 2);
            $table->timestamps();
        });

        Schema::table('prod_pagos_extra', function (Blueprint $table) {
            $table->renameColumn('destajo_id', 'corte_id');
        });

        Schema::table('prod_liquidaciones', function (Blueprint $table) {
            $table->renameColumn('destajo_id', 'corte_id');
        });

        Schema::table('prod_destajos', function (Blueprint $table) {
            $table->dropUnique(['anio', 'semana']);
            $table->dropColumn('anio');
        });

        Schema::rename('prod_destajos', 'prod_cortes');
    }
};
