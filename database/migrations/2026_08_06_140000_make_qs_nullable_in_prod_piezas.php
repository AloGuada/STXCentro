<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El QS deja de ser obligatorio.
     *
     * Desde que el QR identifica la pieza, el QS es sólo un dato de planta y el
     * layout puede traerlo vacío. La columna se quedó como `not null` al mudar
     * el identificador, así que el import reventaba con 500 en el primer renglón
     * con QR pero sin QS.
     */
    public function up(): void
    {
        Schema::table('prod_piezas', function (Blueprint $table) {
            $table->string('qs', 50)->nullable()->change();
        });
    }

    /**
     * Sin vuelta atrás: para volver a `not null` habría que inventarle un QS a
     * las piezas que se cargaron sin él.
     */
    public function down(): void {}
};
