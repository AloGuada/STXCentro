<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Todo lleva kardex (decisión 2026-09-15). La bandera deja de decidir nada
 * desde hoy; la columna se retira en la fase 1 del reapunte a `items`.
 *
 * Es lo que permite que `alm:reponer-entradas` cargue las recepciones que se
 * quedaron sin asiento porque el producto nació con la bandera apagada.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('costos_productos')->where('controla_inventario', false)->update(['controla_inventario' => true]);
    }

    public function down(): void {}
};
