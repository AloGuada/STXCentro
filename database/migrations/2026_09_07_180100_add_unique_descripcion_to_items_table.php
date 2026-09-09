<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * La regla del maestro: entre los activos no hay dos que se llamen igual.
     *
     * Va aparte de la creación de la tabla a propósito. Esta migración se niega
     * a correr mientras queden repetidos, y dice cuáles: la respuesta es correr
     * `alm:fusionar-articulos` con esos grupos y volver a intentar. Poner el
     * índice a ciegas fallaría igual, pero con un error de base de datos que no
     * dice qué fusionar.
     *
     * Es índice parcial (sólo activos) porque los sobrantes de una fusión se
     * desactivan, no se borran, y siguen llamándose como el que sobrevivió.
     */
    public function up(): void
    {
        $repetidos = DB::table('items')
            ->select('descripcion_normalizada')
            ->where('activo', true)
            ->groupBy('descripcion_normalizada')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('descripcion_normalizada');

        if ($repetidos->isNotEmpty()) {
            throw new RuntimeException(
                "items: {$repetidos->count()} descripciones repetidas entre los activos. "
                ."Fusiónalas con alm:fusionar-articulos y vuelve a migrar:\n  - ".$repetidos->implode("\n  - ")
            );
        }

        $condicion = DB::getDriverName() === 'pgsql' ? 'activo' : 'activo = 1';

        DB::statement("CREATE UNIQUE INDEX items_descripcion_activo_unique ON items (descripcion_normalizada) WHERE {$condicion}");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS items_descripcion_activo_unique');
    }
};
