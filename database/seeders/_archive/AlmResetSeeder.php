<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

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
 * Borra con `delete()` y con las llaves foráneas VIVAS. Las dos cosas a
 * propósito:
 *   - `truncate()` no significa lo mismo en todos los motores. En PostgreSQL
 *     Laravel lo compila como `TRUNCATE ... RESTART IDENTITY CASCADE`, y ese
 *     CASCADE vacía toda tabla que referencie a la truncada, en cadena y sin
 *     importar si su llave era `restrict`, `nullOnDelete` o `cascade`. Truncar
 *     `alm_areas` —6 renglones— se llevaba `costos_productos` completo y con él
 *     los renglones de requisiciones y órdenes de compra. En SQLite el mismo
 *     código se compila como `delete from`, así que en dev no se veía.
 *   - Con las FK deshabilitadas ningún `restrict` bloquea ni ningún `SET NULL`
 *     se ejecuta: el borrado deja huérfanos en silencio. Vivas, si algo no se
 *     contempló el reset falla —dentro de la transacción, sin dejar nada a
 *     medias— en vez de corromper.
 *
 * Dos enlaces de Costos apuntan hacia acá con `restrictOnDelete` y hay que
 * apagarlos antes o bloquean el borrado:
 *   - `costos_entregas.almacen_id`
 *   - `costos_entrega_detalle.producto_id`
 * `costos_ordenes_compra_detalle.producto_id` y `costos_requisicion_detalle.producto_id`
 * son `nullOnDelete` y ya se apagarían solos, pero se hacen a mano para poder
 * contar cuántos renglones quedaron sin producto. El renglón sobrevive: guarda
 * descripción y unidad propias para poder imprimirse solo.
 *
 * Efecto colateral conocido: borrar `alm_areas` dispara el `nullOnDelete` de
 * `costos_productos.area_id` y deja sin clasificación también a los artículos
 * que este reset NO borra. Es correcto —no quedan ids colgando— pero se pierde
 * el dato. Se cierra el día que el área deje de ser columna del producto.
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
     * Las tablas del módulo, en orden hijo → padre. El orden es obligatorio: el
     * borrado corre con las llaves foráneas vivas, así que sacar una tabla de su
     * lugar hace que el `restrict` del hijo bloquee el borrado del padre.
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

        DB::transaction(function () use ($articulos): void {
            foreach ($this->tablas as $tabla) {
                DB::table($tabla)->delete();
            }

            DB::table('costos_producto_precios')->whereIn('producto_id', $articulos)->delete();
            DB::table('costos_productos')->whereIn('id', $articulos)->delete();
        });

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
