<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Las siete tablas de Almacén ganan `articulo_id` y lo llenan.
     *
     * **No se quita `producto_id`.** Las dos columnas conviven a propósito, y
     * ésa es toda la seguridad de este cambio: si el relleno falla en un
     * renglón, ese renglón queda con `articulo_id` nulo y su dato original
     * intacto al lado, a la vista. Quitar la vieja es otra migración, que se
     * corre días después y sólo cuando esta comprobación haya dado cero varias
     * veces seguidas en producción.
     *
     * Agregar y llenar van juntos porque tienen que confirmarse o revertirse
     * como una sola cosa: media migración es peor que ninguna. La comprobación
     * final revienta a propósito —Postgres y SQLite hacen transaccional el DDL,
     * así que la excepción revierte columnas, índices y datos de una vez—.
     */
    private const TABLAS = [
        'alm_existencias',
        'alm_movimientos',
        'alm_ajuste_detalle',
        'alm_pedido_detalle',
        'alm_salida_detalle',
        'alm_transferencia_detalle',
        'alm_activos',
    ];

    /** Índice => si es unique. Los crea `espejearIndices()` y los suelta `down()`. */
    private const INDICES = [
        'alm_existencias' => [
            'alm_existencias_almacen_articulo_unique' => true,
            'alm_existencias_articulo_id_index' => false,
        ],
        'alm_movimientos' => [
            'alm_movimientos_almacen_id_articulo_id_id_index' => false,
            'alm_movimientos_articulo_id_created_at_index' => false,
        ],
        'alm_activos' => [
            'alm_activos_articulo_serie_unique' => true,
        ],
        'alm_ajuste_detalle' => ['alm_ajuste_detalle_articulo_id_index' => false],
        'alm_pedido_detalle' => ['alm_pedido_detalle_articulo_id_index' => false],
        'alm_salida_detalle' => ['alm_salida_detalle_articulo_id_index' => false],
        'alm_transferencia_detalle' => ['alm_transferencia_detalle_articulo_id_index' => false],
    ];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->foreignId('articulo_id')->nullable()->after('producto_id')
                    ->constrained('alm_articulos')->restrictOnDelete();
            });
        }

        $this->rellenar();
        $this->espejearIndices();
        $this->comprobar();
    }

    /**
     * El artículo de cada renglón sale de su producto, que es el vínculo que la
     * migración anterior ya escribió. Un `UPDATE ... FROM` por tabla; en
     * producción son 148 renglones en total.
     */
    private function rellenar(): void
    {
        foreach (self::TABLAS as $tabla) {
            DB::table($tabla)
                ->whereNull('articulo_id')
                ->whereNotNull('producto_id')
                ->update([
                    'articulo_id' => DB::raw(
                        "(SELECT a.id FROM alm_articulos a WHERE a.producto_id = {$tabla}.producto_id)"
                    ),
                ]);
        }
    }

    /**
     * Los índices que ya existen sobre `producto_id`, replicados sobre la
     * columna nueva. Sin esto la pantalla de kardex de un artículo con miles de
     * movimientos pasa a leerse con un recorrido de tabla.
     *
     * Los dos unique se replican con su significado intacto: una existencia por
     * artículo y almacén, y una serie que no se repite dentro del mismo
     * artículo. Los renglones con `articulo_id` nulo no estorban porque en un
     * unique los nulos no chocan entre sí.
     */
    private function espejearIndices(): void
    {
        Schema::table('alm_existencias', function (Blueprint $table) {
            $table->unique(['almacen_id', 'articulo_id'], 'alm_existencias_almacen_articulo_unique');
            $table->index('articulo_id');
        });

        Schema::table('alm_movimientos', function (Blueprint $table) {
            $table->index(['almacen_id', 'articulo_id', 'id']);
            $table->index(['articulo_id', 'created_at']);
        });

        Schema::table('alm_activos', function (Blueprint $table) {
            $table->unique(['articulo_id', 'no_serie'], 'alm_activos_articulo_serie_unique');
        });

        foreach (['alm_ajuste_detalle', 'alm_pedido_detalle', 'alm_salida_detalle', 'alm_transferencia_detalle'] as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->index('articulo_id');
            });
        }
    }

    /**
     * La puerta. Ningún renglón que tenga producto puede quedarse sin artículo,
     * y el número de artículos distintos tiene que empatar con el de productos:
     * si no empatan, dos productos cayeron en el mismo artículo o alguno se
     * quedó fuera.
     *
     * Es aritmética, no criterio. Si no da, se revierte todo.
     */
    private function comprobar(): void
    {
        foreach (self::TABLAS as $tabla) {
            $sinLigar = DB::table($tabla)->whereNotNull('producto_id')->whereNull('articulo_id')->count();

            if ($sinLigar > 0) {
                throw new RuntimeException(
                    "{$tabla}: {$sinLigar} renglones se quedaron sin artículo. ".
                    'Falta correr la migración que puebla alm_articulos, o hay productos con material que no la pasaron.'
                );
            }

            $productos = DB::table($tabla)->whereNotNull('producto_id')->distinct()->count('producto_id');
            $articulos = DB::table($tabla)->whereNotNull('articulo_id')->distinct()->count('articulo_id');

            if ($productos !== $articulos) {
                throw new RuntimeException(
                    "{$tabla}: {$productos} productos distintos quedaron en {$articulos} artículos. ".
                    'El vínculo dejó de ser uno a uno.'
                );
            }
        }
    }

    /**
     * Los índices se sueltan **antes** que la columna y uno por uno: SQLite no
     * los arrastra al quitarla y deja la tabla con un índice que apunta a algo
     * que ya no existe, con lo que el rollback truena a la mitad. Se consulta si
     * cada uno sigue ahí porque un rollback anterior pudo haberse caído después
     * de soltar unos cuantos, y reintentarlo tiene que poder terminar.
     */
    public function down(): void
    {
        foreach (self::INDICES as $tabla => $indices) {
            foreach ($indices as $nombre => $esUnico) {
                if (! Schema::hasIndex($tabla, $nombre)) {
                    continue;
                }

                Schema::table($tabla, function (Blueprint $table) use ($nombre, $esUnico) {
                    $esUnico ? $table->dropUnique($nombre) : $table->dropIndex($nombre);
                });
            }
        }

        foreach (self::TABLAS as $tabla) {
            Schema::table($tabla, function (Blueprint $table) {
                $table->dropConstrainedForeignId('articulo_id');
            });
        }
    }
};
