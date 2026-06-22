<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * El reporte semanal de cobranza se calcula en vivo desde los datos (obras
 * detonadas y estimaciones cobradas); el saldo es el acumulado real de cartera.
 * Lo único que se persiste son las notas/comentarios por semana.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cob_reporte_notas', function (Blueprint $table) {
            $table->id();
            $table->integer('anio');
            $table->integer('semana');
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->unique(['anio', 'semana']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cob_reporte_notas');
    }
};
