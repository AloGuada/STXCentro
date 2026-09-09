<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cuánto del renglón anda afuera en resguardo.
     *
     * Es caché de `SUM(alm_prestamo_detalle.cantidad − cantidad_devuelta)` de
     * los renglones por cantidad de ese almacén y artículo. Se guarda porque la
     * pantalla de existencias la lee en cada fila, y va en la existencia y no
     * en el kardex porque prestar no es un movimiento: el saldo no cambia, lo
     * que cambia es cuánto de ese saldo se puede prometer.
     *
     * La escribe **sólo** `App\Services\Alm\RegistradorPrestamos`, y está fuera
     * del `$fillable` a propósito. Las piezas con serie no suman aquí: su
     * «afuera» ya lo dice el estatus de la pieza.
     */
    public function up(): void
    {
        Schema::table('alm_existencias', function (Blueprint $table) {
            $table->decimal('prestado', 16, 4)->default(0)->after('valor');
        });
    }

    public function down(): void
    {
        Schema::table('alm_existencias', function (Blueprint $table) {
            $table->dropColumn('prestado');
        });
    }
};
