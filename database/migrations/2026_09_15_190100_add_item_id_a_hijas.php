<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Las trece tablas hijas del catálogo ganan `item_id` y lo llenan.
 *
 * Es la fase 1 del reapunte a `items`: la llave nueva convive con las viejas
 * (`producto_id` en Costos, `articulo_id` y `producto_id` en Almacén) y nada
 * cambia de comportamiento todavía. Si el relleno falla en un renglón, ese
 * renglón queda con `item_id` nulo y su dato original intacto al lado. Quitar
 * las viejas es la fase 4, cuando `alm:verificar-articulos` lleve días dando
 * cero.
 *
 * Agregar, llenar y comprobar van juntos: la comprobación revienta a propósito
 * y el DDL transaccional de Postgres y SQLite revierte todo de una vez.
 */
return new class extends Migration
{
    /** tabla => llave vieja desde la que se rellena. */
    private const TABLAS = [
        'costos_producto_precios' => 'producto_id',
        'costos_requisicion_detalle' => 'producto_id',
        'costos_ordenes_compra_detalle' => 'producto_id',
        'costos_entrega_detalle' => 'producto_id',
        'alm_existencias' => 'articulo_id',
        'alm_movimientos' => 'articulo_id',
        'alm_ajuste_detalle' => 'articulo_id',
        'alm_pedido_detalle' => 'articulo_id',
        'alm_salida_detalle' => 'articulo_id',
        'alm_transferencia_detalle' => 'articulo_id',
        'alm_activos' => 'articulo_id',
        'alm_conteo_detalle' => 'articulo_id',
        'alm_prestamo_detalle' => 'articulo_id',
    ];

    /** Índice => si es unique. Los crea `espejearIndices()` y los suelta `down()`. */
    private const INDICES = [
        'alm_existencias' => ['alm_existencias_almacen_item_unique' => true],
        'alm_movimientos' => [
            'alm_movimientos_almacen_id_item_id_id_index' => false,
            'alm_movimientos_item_id_created_at_index' => false,
        ],
        'alm_activos' => ['alm_activos_item_serie_unique' => true],
        'alm_conteo_detalle' => ['alm_conteo_detalle_conteo_item_unique' => true],
    ];

    public function up(): void
    {
        foreach (self::TABLAS as $tabla => $llave) {
            Schema::table($tabla, function (Blueprint $table) use ($llave) {
                $table->foreignId('item_id')->nullable()->after($llave)
                    ->constrained('items')->restrictOnDelete();
                $table->index('item_id');
            });
        }

        $this->rellenar();
        $this->espejearIndices();
        $this->comprobar();
    }

    /**
     * El item de cada renglón sale de su cara: del artículo en Almacén, del
     * producto en Costos. Un renglón de Almacén sin artículo pero con producto
     * (no debería haber) se rellena desde el producto.
     */
    private function rellenar(): void
    {
        foreach (self::TABLAS as $tabla => $llave) {
            $cara = $llave === 'articulo_id' ? 'alm_articulos' : 'costos_productos';

            DB::table($tabla)
                ->whereNull('item_id')
                ->whereNotNull($llave)
                ->update(['item_id' => DB::raw("(SELECT c.item_id FROM {$cara} c WHERE c.id = {$tabla}.{$llave})")]);

            if ($llave === 'articulo_id' && Schema::hasColumn($tabla, 'producto_id')) {
                DB::table($tabla)
                    ->whereNull('item_id')
                    ->whereNotNull('producto_id')
                    ->update(['item_id' => DB::raw("(SELECT p.item_id FROM costos_productos p WHERE p.id = {$tabla}.producto_id)")]);
            }
        }
    }

    /**
     * Los unique que hoy viven sobre `articulo_id`, con su significado intacto:
     * una existencia por item y almacén, una serie que no se repite dentro del
     * mismo item, un item por conteo. Los nulos no chocan entre sí.
     */
    private function espejearIndices(): void
    {
        Schema::table('alm_existencias', function (Blueprint $table) {
            $table->unique(['almacen_id', 'item_id'], 'alm_existencias_almacen_item_unique');
        });

        Schema::table('alm_movimientos', function (Blueprint $table) {
            $table->index(['almacen_id', 'item_id', 'id']);
            $table->index(['item_id', 'created_at']);
        });

        Schema::table('alm_activos', function (Blueprint $table) {
            $table->unique(['item_id', 'no_serie'], 'alm_activos_item_serie_unique');
        });

        Schema::table('alm_conteo_detalle', function (Blueprint $table) {
            $table->unique(['conteo_id', 'item_id'], 'alm_conteo_detalle_conteo_item_unique');
        });
    }

    /**
     * Ningún renglón con llave vieja puede quedarse sin item, y el número de
     * items distintos tiene que empatar con el de caras distintas: si no, dos
     * caras cayeron en el mismo item. Es aritmética; si no da, se revierte todo.
     */
    private function comprobar(): void
    {
        foreach (self::TABLAS as $tabla => $llave) {
            $sinItem = DB::table($tabla)->whereNotNull($llave)->whereNull('item_id')->count();

            if ($sinItem > 0) {
                throw new RuntimeException("{$tabla}: {$sinItem} renglones se quedaron sin item.");
            }

            $caras = DB::table($tabla)->whereNotNull($llave)->distinct()->count($llave);
            $items = DB::table($tabla)->whereNotNull($llave)->distinct()->count('item_id');

            if ($caras !== $items) {
                throw new RuntimeException("{$tabla}: {$caras} {$llave} distintos quedaron en {$items} items. El vínculo dejó de ser uno a uno.");
            }
        }
    }

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

        foreach (array_keys(self::TABLAS) as $tabla) {
            Schema::table($tabla, function (Blueprint $table) use ($tabla) {
                $table->dropIndex("{$tabla}_item_id_index");
                $table->dropConstrainedForeignId('item_id');
            });
        }
    }
};
