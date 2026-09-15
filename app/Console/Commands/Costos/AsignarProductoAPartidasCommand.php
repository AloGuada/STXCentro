<?php

namespace App\Console\Commands\Costos;

use App\Services\Costos\AsignadorProductoAPartidas;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Pone producto a las partidas de requisición y de orden que nacieron sin él,
 * emparejando por descripción con el catálogo. Después de esto,
 * `alm:reponer-entradas` puede cargar al kardex las recepciones de esas
 * partidas.
 *
 * Sin `--force` sólo enseña qué asignaría y qué se queda sin producto.
 */
class AsignarProductoAPartidasCommand extends Command
{
    protected $signature = 'costos:asignar-producto-a-partidas
        {--force : Escribe los productos; sin esta opción sólo se enseña el plan}';

    protected $description = 'Asigna producto por descripción a las partidas de requisición y orden que no lo tienen';

    public function handle(AsignadorProductoAPartidas $asignador): int
    {
        $plan = $asignador->planear();

        if ($plan['ordenes']->isNotEmpty()) {
            $this->info('Partidas de orden que casan por nombre:');
            $this->table(
                ['OC', 'Partida', 'Descripción', 'Producto'],
                $plan['ordenes']->map(fn (array $fila): array => [
                    $fila['detalle']->ordenCompra?->folio,
                    $fila['detalle']->id,
                    mb_strimwidth((string) $fila['detalle']->descripcion, 0, 45, '…'),
                    $fila['item']->codigo.' '.mb_strimwidth((string) $fila['item']->descripcion, 0, 35, '…'),
                ])->all(),
            );
        }

        if ($plan['requisiciones']->isNotEmpty()) {
            $this->info('Partidas de requisición que casan por nombre:');
            $this->table(
                ['Requisición', 'Partida', 'Descripción', 'Producto'],
                $plan['requisiciones']->map(fn (array $fila): array => [
                    $fila['detalle']->requisicion?->folio,
                    $fila['detalle']->id,
                    mb_strimwidth((string) $fila['detalle']->descripcion, 0, 45, '…'),
                    $fila['item']->codigo.' '.mb_strimwidth((string) $fila['item']->descripcion, 0, 35, '…'),
                ])->all(),
            );
        }

        if ($plan['sin_producto']->isNotEmpty()) {
            $this->warn('Sin producto con ese nombre (fletes se quedan así; lo demás es alta en Almacén > Artículos y volver a correr):');
            $this->table(['Origen', 'Folio', 'Descripción'], $plan['sin_producto']->map(fn (array $f): array => [$f['origen'], $f['folio'], $f['descripcion']])->all());
        }

        if ($plan['ordenes']->isEmpty() && $plan['requisiciones']->isEmpty()) {
            $this->info('No hay partidas que casen por nombre; nada que asignar.');

            return self::SUCCESS;
        }

        if (! $this->option('force')) {
            $this->warn('Simulacro: no se escribió nada. Corre con --force para asignar.');

            return self::SUCCESS;
        }

        $resumen = DB::transaction(fn (): array => $asignador->ejecutar($plan));

        $this->info(sprintf(
            'Listo: %d partidas de orden, %d de requisición y %d renglones de recepción con producto.',
            $resumen['ordenes'],
            $resumen['requisiciones'],
            $resumen['recepciones'],
        ));

        return self::SUCCESS;
    }
}
