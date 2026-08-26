<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reset del módulo de almacén: lo deja en cero absoluto. Vacía las 15 tablas
 * `alm_` —el catálogo incluido, no sólo los movimientos— y borra los artículos
 * `ART-` que la carga inicial dio de alta.
 *
 * DESTRUCTIVO. Ejecutar manualmente:
 *   php artisan db:seed --class=AlmResetSeeder
 *
 * Después de correrlo hay que volver a dar de alta los almacenes con su clave
 * exacta antes de cargar nada: el seeder de carga inicial no los inventa.
 *
 * Los artículos se borran por código y no por tabla: `ART-#####` es el
 * consecutivo que pone el almacén, así que borrarlos lo devuelve a `ART-00001`.
 * Lo que Compras tecleó al vuelo con su propio código —y los que nacieron sin
 * código, fuera del inventario— no es del almacén y se queda.
 *
 * Tres enlaces salen de Costos hacia aquí y se apagan antes de borrar, porque
 * son `restrictOnDelete` y si no bloquean:
 *   - `costos_entregas.almacen_id`
 *   - `costos_entrega_detalle.producto_id`
 *   - `costos_ordenes_compra_detalle.producto_id` (es `nullOnDelete`, pero se
 *     apaga igual: el borrado corre con las FK deshabilitadas y ahí nadie
 *     ejecuta el `SET NULL` por nosotros)
 * El renglón sobrevive sin su producto: guarda descripción y unidad propias
 * para poder imprimirse solo.
 *
 * No toca `media` —el módulo no tiene adjuntos— ni los archivos en storage.
 */
class AlmResetSeeder extends Seeder
{
    /**
     * Prefijo del consecutivo que genera el almacén.
     *
     * @see \App\Services\Alm\GeneradorCodigoArticulo
     */
    private const PREFIJO_ARTICULO = 'ART-';

    /**
     * Las tablas del módulo, en orden hijo → padre. Con las FK deshabilitadas
     * da igual, pero el orden documenta de qué cuelga cada cosa.
     *
     * @var list<string>
     */
    private array $tablas = [
        // Movimientos y su detalle
        'alm_ajuste_detalle',
        'alm_ajustes',
        'alm_salida_detalle',
        'alm_salidas',
        'alm_transferencia_detalle',
        'alm_transferencias',
        'alm_pedido_detalle',
        'alm_pedidos',
        // Piezas con identidad propia
        'alm_activos',
        // Saldo y kardex
        'alm_movimientos',
        'alm_existencias',
        // Catálogo
        'alm_almacen_usuarios',
        'alm_ubicaciones',
        'alm_areas',
        'alm_almacenes',
    ];

    public function run(): void
    {
        if (app()->environment('production') && ! env('RESET_ALM_FORCE')) {
            throw new \RuntimeException(
                'AlmResetSeeder está bloqueado en producción. Define RESET_ALM_FORCE=true para permitirlo.'
            );
        }

        $articulos = $this->articulos();
        $huerfanos = $this->apagarEnlacesDeCostos($articulos);

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($this->tablas as $tabla) {
                DB::table($tabla)->truncate();
            }

            DB::table('costos_producto_precios')->whereIn('producto_id', $articulos)->delete();
            DB::table('costos_productos')->whereIn('id', $articulos)->delete();
        } finally {
            Schema::enableForeignKeyConstraints();
        }

        $this->command?->info(sprintf(
            'Almacén reseteado: %d tablas vaciadas y %d artículos %s borrados.',
            count($this->tablas),
            count($articulos),
            self::PREFIJO_ARTICULO,
        ));

        if ($huerfanos > 0) {
            $this->command?->warn("{$huerfanos} renglones de Costos se quedaron sin producto; conservan su descripción.");
        }

        $this->command?->warn('Da de alta los almacenes con su clave antes de volver a cargar: la carga inicial no los inventa.');
    }

    /**
     * Los artículos que dio de alta el almacén.
     *
     * @return list<int>
     */
    private function articulos(): array
    {
        return DB::table('costos_productos')
            ->where('codigo', 'like', self::PREFIJO_ARTICULO.'%')
            ->pluck('id')
            ->all();
    }

    /**
     * Apaga lo que Costos apunta hacia acá y devuelve cuántos renglones se
     * quedaron sin producto.
     *
     * @param  list<int>  $articulos
     */
    private function apagarEnlacesDeCostos(array $articulos): int
    {
        DB::table('costos_entregas')->whereNotNull('almacen_id')->update(['almacen_id' => null]);

        if ($articulos === []) {
            return 0;
        }

        return DB::table('costos_entrega_detalle')->whereIn('producto_id', $articulos)->update(['producto_id' => null])
            + DB::table('costos_ordenes_compra_detalle')->whereIn('producto_id', $articulos)->update(['producto_id' => null])
            + DB::table('costos_requisicion_detalle')->whereIn('producto_id', $articulos)->update(['producto_id' => null]);
    }
}
