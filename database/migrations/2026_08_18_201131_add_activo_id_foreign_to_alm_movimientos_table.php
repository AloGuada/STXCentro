<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * `alm_movimientos.activo_id` nació sin llave foránea porque `alm_activos`
     * llegó después. Ahora que existe, se amarra: un movimiento que dice haber
     * movido una pieza tiene que poder enseñar cuál.
     *
     * `nullOnDelete` y no `restrict` porque la pieza casi nunca se borra —se da
     * de baja—, y si alguna vez se depura una carga mal hecha, el asiento del
     * kardex no debe irse con ella.
     */
    public function up(): void
    {
        Schema::table('alm_movimientos', function (Blueprint $table) {
            $table->foreign('activo_id')->references('id')->on('alm_activos')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('alm_movimientos', function (Blueprint $table) {
            $table->dropForeign(['activo_id']);
        });
    }
};
