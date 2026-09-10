<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Protege la clave de los almacenes centrales.
     *
     * El unique original es `(obra_id, clave)`, y `obra_id` es nullable: en
     * Postgres dos NULL nunca son iguales, así que ese índice deja pasar dos
     * almacenes centrales con la misma clave. Con un solo almacén central no se
     * notaba; la planta tiene cuatro y todos van sin obra.
     *
     * La solución es un índice parcial sobre los que no tienen obra. La sintaxis
     * `WHERE` en un unique la entienden igual SQLite (dev) y Postgres (prod).
     */
    public function up(): void
    {
        DB::statement(
            'CREATE UNIQUE INDEX alm_almacenes_clave_central_unique ON alm_almacenes (clave) WHERE obra_id IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS alm_almacenes_clave_central_unique');
    }
};
