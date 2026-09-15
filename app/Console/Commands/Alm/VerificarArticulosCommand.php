<?php

namespace App\Console\Commands\Alm;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * La puerta entre las fases de la mudanza del catálogo a `items`.
 *
 * Mientras `item_id` conviva con `producto_id` y `articulo_id` en las trece
 * tablas hijas, esto se corre a diario: si algún flujo se quedó sin escribir la
 * llave nueva, sus renglones aparecen aquí y se ve cuál flujo los produjo, en
 * vez de descubrirlo el día que se quiten las viejas.
 *
 * No arregla nada ni escribe nada. Sólo cuenta, y devuelve fallo si algo no
 * cuadra, para que un cron o un deploy puedan colgarse de su código de salida.
 */
class VerificarArticulosCommand extends Command
{
    protected $signature = 'alm:verificar-articulos';

    protected $description = 'Comprueba que ningún renglón hijo del catálogo se haya quedado sin item mientras conviven las llaves';

    /**
     * tabla => llaves viejas que tiene.
     *
     * @var array<string, list<string>>
     */
    private const TABLAS = [
        'costos_producto_precios' => ['producto_id'],
        'costos_requisicion_detalle' => ['producto_id'],
        'costos_ordenes_compra_detalle' => ['producto_id'],
        'costos_entrega_detalle' => ['producto_id'],
        'alm_existencias' => ['producto_id', 'articulo_id'],
        'alm_movimientos' => ['producto_id', 'articulo_id'],
        'alm_ajuste_detalle' => ['producto_id', 'articulo_id'],
        'alm_pedido_detalle' => ['producto_id', 'articulo_id'],
        'alm_salida_detalle' => ['producto_id', 'articulo_id'],
        'alm_transferencia_detalle' => ['producto_id', 'articulo_id'],
        'alm_activos' => ['producto_id', 'articulo_id'],
        'alm_conteo_detalle' => ['articulo_id'],
        'alm_prestamo_detalle' => ['articulo_id'],
    ];

    public function handle(): int
    {
        $filas = [];
        $problemas = 0;

        foreach (self::TABLAS as $tabla => $llaves) {
            $total = DB::table($tabla)->count();
            $sinItem = DB::table($tabla)->whereNull('item_id')->count();

            // Un renglón con alguna llave vieja y sin item es un flujo que no
            // pasó por el trait. Un renglón sin ninguna llave (una partida de
            // flete) es legítimo.
            $conLlaveVieja = DB::table($tabla)->where(function ($q) use ($llaves): void {
                foreach ($llaves as $llave) {
                    $q->orWhereNotNull($llave);
                }
            });
            $huerfanos = (clone $conLlaveVieja)->whereNull('item_id')->count();

            // Y al revés: el item tiene que coincidir con el de la cara.
            $desacuerdos = 0;
            foreach ($llaves as $llave) {
                $cara = $llave === 'articulo_id' ? 'alm_articulos' : 'costos_productos';
                $desacuerdos += DB::table("{$tabla} as h")
                    ->join("{$cara} as c", 'c.id', '=', "h.{$llave}")
                    ->whereColumn('c.item_id', '<>', 'h.item_id')
                    ->count();
            }

            $cuadra = $huerfanos === 0 && $desacuerdos === 0;
            $problemas += $cuadra ? 0 : 1;

            $filas[] = [$tabla, $total, $sinItem, $huerfanos, $desacuerdos, $cuadra ? 'ok' : 'REVISAR'];
        }

        $this->table(['Tabla', 'Renglones', 'Sin item', 'Con llave vieja y sin item', 'Item distinto al de la cara', ''], $filas);

        $sinArticulo = DB::table('costos_productos as p')
            ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('alm_articulos as a')->whereColumn('a.item_id', 'p.item_id'))
            ->count();

        if ($sinArticulo > 0) {
            $problemas++;
            $this->error("{$sinArticulo} producto(s) sin artículo: todo item debe tener sus dos caras.");
        }

        if ($problemas > 0) {
            $this->error("{$problemas} cosa(s) no cuadran. No se pueden quitar las llaves viejas todavía.");

            return self::FAILURE;
        }

        $this->info('Las trece cuadran: ningún renglón se quedó sin item y todo producto tiene artículo.');

        return self::SUCCESS;
    }
}
