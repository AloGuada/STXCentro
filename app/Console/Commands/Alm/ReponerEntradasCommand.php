<?php

namespace App\Console\Commands\Alm;

use App\Models\Costos\Entrega;
use App\Models\Costos\EntregaDetalle;
use App\Services\Alm\RegistradorEntradaAlmacen;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Carga al kardex los renglones de recepción que se quedaron sin asiento.
 *
 * Pasó por dos vías: productos con la bandera de inventario apagada (que ya no
 * existe como criterio) y partidas de orden sin producto que se resolvieron
 * después con `costos:asignar-producto-a-partidas`. Como `aplicar()` es
 * idempotente por documento, una recepción a medias no se rescataba sola.
 *
 * Sin `--force` sólo enseña qué repondría, renglón por renglón.
 */
class ReponerEntradasCommand extends Command
{
    protected $signature = 'alm:reponer-entradas
        {--force : Escribe los asientos; sin esta opción sólo se enseña el plan}';

    protected $description = 'Asienta en el kardex los renglones de recepciones con almacén que no llegaron al ledger';

    public function handle(RegistradorEntradaAlmacen $registrador): int
    {
        $entregas = Entrega::query()
            ->whereNotNull('almacen_id')
            ->whereNull('cancelada_at')
            ->with(['detalles.ordenCompraDetalle', 'almacen'])
            ->orderBy('id')
            ->get();

        $plan = $entregas
            ->map(fn (Entrega $entrega): array => ['entrega' => $entrega, 'renglones' => $registrador->renglonesSinAsiento($entrega)])
            ->filter(fn (array $fila): bool => $fila['renglones']->isNotEmpty())
            ->values();

        if ($plan->isEmpty()) {
            $this->info('Todas las recepciones con almacén tienen sus asientos; nada que reponer.');

            return self::SUCCESS;
        }

        $this->table(
            ['Recepción', 'Almacén', 'Producto', 'Descripción', 'Cantidad', 'Precio'],
            $plan->flatMap(fn (array $fila) => $fila['renglones']->map(fn (EntregaDetalle $d): array => [
                $fila['entrega']->folio,
                $fila['entrega']->almacen?->nombre,
                $d->producto_id ?? $d->ordenCompraDetalle?->producto_id,
                mb_strimwidth((string) ($d->descripcion ?? $d->ordenCompraDetalle?->descripcion), 0, 45, '…'),
                (float) $d->cantidad_recibida,
                (float) $d->precio_unitario_efectivo,
            ]))->all(),
        );

        $renglones = $plan->sum(fn (array $fila): int => $fila['renglones']->count());
        $this->info(sprintf('%d renglones sin asiento en %d recepciones.', $renglones, $plan->count()));

        if (! $this->option('force')) {
            $this->warn('Simulacro: no se escribió nada. Corre con --force para asentarlos.');

            return self::SUCCESS;
        }

        $repuestos = DB::transaction(fn (): int => $plan->sum(
            fn (array $fila): int => $registrador->reponer($fila['entrega'])
        ));

        $this->info("Listo: {$repuestos} renglones asentados.");

        return self::SUCCESS;
    }
}
