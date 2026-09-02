<?php

namespace App\Console\Commands\Alm;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * La puerta entre las dos fases de la mudanza del catálogo.
 *
 * Mientras `producto_id` y `articulo_id` convivan en las siete tablas, esto se
 * corre a diario: si algún flujo se quedó sin escribir la columna nueva, sus
 * renglones aparecen aquí y se ve cuál flujo los produjo, en vez de descubrirlo
 * el día que se quite la columna vieja.
 *
 * No arregla nada ni escribe nada. Sólo cuenta, y devuelve fallo si algo no
 * cuadra, para que un cron o un deploy puedan colgarse de su código de salida.
 */
class VerificarArticulosCommand extends Command
{
    protected $signature = 'alm:verificar-articulos';

    protected $description = 'Comprueba que ningún renglón de Almacén se haya quedado sin artículo mientras conviven las dos columnas';

    /**
     * @var list<string>
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

    public function handle(): int
    {
        $filas = [];
        $problemas = 0;

        foreach (self::TABLAS as $tabla) {
            $sinLigar = DB::table($tabla)->whereNotNull('producto_id')->whereNull('articulo_id')->count();

            // El uno a uno se mide **solo entre los renglones que tienen
            // producto**. Los demas son material sin identidad de compra, y
            // compararlos contra un producto que no existe daria siempre
            // desigual: en un almacen recien abierto, todos.
            $conProducto = DB::table($tabla)->whereNotNull('producto_id');
            $productos = (clone $conProducto)->distinct()->count('producto_id');
            $articulos = (clone $conProducto)->whereNotNull('articulo_id')->distinct()->count('articulo_id');

            $cuadra = $sinLigar === 0 && $productos === $articulos;
            $problemas += $cuadra ? 0 : 1;

            $filas[] = [
                $tabla,
                DB::table($tabla)->count(),
                DB::table($tabla)->whereNull('producto_id')->count(),
                $sinLigar,
                $productos,
                $articulos,
                $cuadra ? 'ok' : 'REVISAR',
            ];
        }

        $this->table(
            ['Tabla', 'Renglones', 'Sueltos', 'Sin ligar', 'Productos', 'Artículos', ''],
            $filas,
        );

        if ($problemas > 0) {
            $this->error("{$problemas} tabla(s) no cuadran. No se puede quitar producto_id todavía.");

            return self::FAILURE;
        }

        $sueltos = DB::table('alm_articulos')->whereNull('producto_id')->count();

        $this->info('Las siete cuadran: ningún renglón se quedó sin artículo.');

        if ($sueltos > 0) {
            // No es un problema: es material real sin identidad de compra
            // todavía. Se informa porque es la bandeja de la pantalla de ligado.
            $this->line("  {$sueltos} artículo(s) sin ligar a un producto de Compras, esperando emparejarse.");
        }

        return self::SUCCESS;
    }
}
