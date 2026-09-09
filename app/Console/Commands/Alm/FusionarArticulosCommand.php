<?php

namespace App\Console\Commands\Alm;

use App\Services\Alm\FusionadorArticulos;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/**
 * Junta los artículos duplicados: los que dice un CSV o, con `--auto`, los que
 * el maestro ve repetidos por descripción (se queda el código más bajo).
 *
 * Sin `--force` sólo enseña lo que haría, código por código y almacén por
 * almacén, para que se compare antes de la ventana en que se corre de verdad.
 *
 * Con `--force` todos los grupos van en **una** transacción: o se fusionan
 * todos o ninguno. Una fusión a medias es peor que no correrla.
 */
class FusionarArticulosCommand extends Command
{
    protected $signature = 'alm:fusionar-articulos
        {csv? : Ruta del CSV con columnas grupo, codigo, conservar (SI en el que se queda)}
        {--auto : Arma los grupos solo: items activos con la misma descripción, se queda el código más bajo}
        {--force : Ejecuta la fusión; sin esta opción sólo se enseña el plan}';

    protected $description = 'Fusiona artículos y productos duplicados, según un CSV o por descripción repetida (--auto)';

    public function handle(FusionadorArticulos $fusionador): int
    {
        $csv = $this->argument('csv');
        $auto = (bool) $this->option('auto');

        if (($csv === null) === (! $auto)) {
            $this->error('Indica la ruta del CSV o usa --auto, pero no ambos.');

            return self::FAILURE;
        }

        try {
            $grupos = $auto ? $fusionador->gruposAutomaticos() : $fusionador->gruposDesdeCsv((string) $csv);
            $planes = $fusionador->planear($grupos);
        } catch (InvalidArgumentException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if ($planes === []) {
            $this->info('No hay descripciones repetidas entre los items activos; nada que fusionar.');

            return self::SUCCESS;
        }

        $this->imprimirPlan($planes);

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('Simulacro: no se escribió nada. Corre con --force para ejecutar la fusión.');

            return self::SUCCESS;
        }

        try {
            $resumen = DB::transaction(fn (): array => $fusionador->ejecutar($planes));
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            $this->error('Se revirtió todo; la base quedó como estaba.');

            return self::FAILURE;
        }

        $this->newLine();
        $this->info(sprintf(
            'Listo: %d grupos fusionados, %d artículos y %d productos desactivados, %d existencias sumadas con otra del mismo almacén.',
            count($planes),
            $resumen['articulos_desactivados'],
            $resumen['productos_desactivados'],
            $resumen['existencias_sumadas'],
        ));

        return self::SUCCESS;
    }

    /**
     * @param  list<array<string, mixed>>  $planes
     */
    private function imprimirPlan(array $planes): void
    {
        $articulos = 0;
        $productos = 0;
        $sumadas = 0;

        foreach ($planes as $plan) {
            $s = $plan['sobreviviente'];

            $this->newLine();
            $this->line(sprintf('<fg=cyan>%s</>', $plan['grupo']));

            if ($s === null) {
                $this->line('  sólo productos de Compras, sin artículo de Almacén:');

                foreach ($plan['productos_sueltos'] as $p) {
                    $this->line(sprintf(
                        '  %s  %s  %s  (producto #%d)',
                        (int) $p->id === $plan['producto_id'] ? 'se queda ' : 'se retira',
                        $p->codigo,
                        $p->descripcion,
                        $p->id,
                    ));
                }

                $productos += count($plan['productos_a_desactivar']);

                continue;
            }

            $this->line(sprintf('  se queda   %s  %s', $s->codigo, $s->descripcion));

            foreach ($plan['sobrantes'] as $x) {
                $this->line(sprintf('  se retira  %s  %s', $x->codigo, $x->descripcion));
            }

            foreach ($plan['productos_sueltos'] as $p) {
                $this->line(sprintf(
                    '  %s  %s  %s  (sólo producto de Compras #%d)',
                    (int) $p->id === $plan['producto_id'] ? 'se adopta ' : 'se retira ',
                    $p->codigo,
                    $p->descripcion,
                    $p->id,
                ));
            }

            $this->line(sprintf(
                '  producto de Compras: %s%s',
                $plan['producto_id'] === null ? 'ninguno' : '#'.$plan['producto_id'],
                $plan['productos_a_desactivar'] === [] ? '' : '  (se desactivan #'.implode(', #', $plan['productos_a_desactivar']).')',
            ));

            if ($plan['almacenes'] !== []) {
                $this->table(
                    ['Almacén', 'Antes (por código)', 'Ubicación', 'Después', 'Valor'],
                    array_map(fn (array $a): array => [
                        $a['nombre'],
                        collect($a['antes'])->map(fn (float $c, string $codigo): string => sprintf('%s %s', $codigo, $this->numero($c)))->implode(' + '),
                        collect($a['ubicaciones'])->map(fn (?string $u, string $codigo): string => sprintf('%s: %s', $codigo, $u ?? '—'))->implode(' | '),
                        $this->numero($a['cantidad']).($a['fusiona'] ? '  (se suman)' : ''),
                        $this->numero($a['valor']),
                    ], $plan['almacenes']),
                );

                foreach ($plan['almacenes'] as $a) {
                    if ($a['ubicaciones_distintas']) {
                        $this->warn(sprintf(
                            '  ⚠ %s: los dos renglones están en lugares distintos y sólo queda uno: "%s". El otro hay que acomodarlo físicamente o dejarlo anotado.',
                            $a['nombre'],
                            $a['ubicacion_queda'] ?? '—',
                        ));
                    }
                }
            }

            $articulos += count($plan['sobrantes']);
            $productos += count($plan['productos_a_desactivar']);
            $sumadas += count(array_filter($plan['almacenes'], fn (array $a): bool => $a['fusiona']));
        }

        $this->newLine();
        $this->line(sprintf(
            'Total: %d grupos, %d artículos y %d productos a desactivar, %d existencias que se suman con otra del mismo almacén.',
            count($planes),
            $articulos,
            $productos,
            $sumadas,
        ));
    }

    private function numero(float $n): string
    {
        return rtrim(rtrim(number_format($n, 4, '.', ','), '0'), '.');
    }
}
