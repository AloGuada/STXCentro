<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuántas piezas tiene el proyecto completo.
     *
     * Es un dato de contrato, no de captura: sale del alcance de la obra y no se
     * puede deducir de lo inspeccionado —lo inspeccionado es justo lo que ya
     * pasó—. Es el denominador de dos cosas del reporte semanal: el avance de
     * montaje («de las 1,240 piezas, van 380») y la referencia de la hoja de
     * PND.
     *
     * Se deja **nulo** a propósito mientras no se teclee. Un cero diría que el
     * proyecto no tiene piezas, y el reporte prefiere escribir «falta el dato»
     * antes que publicar un avance calculado sobre un número inventado.
     */
    public function up(): void
    {
        Schema::table('qal_obras', function (Blueprint $table) {
            $table->unsignedInteger('pz_total')->nullable()->after('pnd_nota');
        });
    }

    public function down(): void
    {
        Schema::table('qal_obras', function (Blueprint $table) {
            $table->dropColumn('pz_total');
        });
    }
};
