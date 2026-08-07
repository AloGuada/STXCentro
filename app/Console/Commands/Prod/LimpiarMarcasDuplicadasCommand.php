<?php

namespace App\Console\Commands\Prod;

use App\Models\Concepto;
use App\Models\Prod\Catalogo;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\LiquidacionDetalle;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Borra las marcas gemelas que dejó el import viejo.
 *
 * Cuando un catálogo se cargó primero con un layout sin lote y después con uno
 * que sí lo trae, la misma marca quedaba dos veces: la nueva con sus piezas y la
 * vieja sin ninguna, pero conservando su `cantidad` —que es el tope de pago— y
 * los precios que tuviera asignados. El import ya no las duplica; esto limpia
 * las que quedaron.
 *
 * Sólo se borra la gemela que está vacía: sin piezas, sin producción capturada y
 * sin liquidación. Sus precios se pasan a la hermana que sí tiene piezas, para
 * no perder el grupo al que pertenecía.
 *
 * Por defecto corre en modo dry-run (sólo reporta). Requiere --force para borrar.
 */
class LimpiarMarcasDuplicadasCommand extends Command
{
    protected $signature = 'prod:limpiar-marcas-duplicadas
        {catalogo : Id del catálogo}
        {--force : Ejecuta el borrado; sin esta bandera sólo muestra el dry-run}';

    protected $description = 'Borra las marcas duplicadas (misma marca, sin piezas) que dejó cargar un layout sin lote sobre uno con lote';

    public function handle(): int
    {
        $catalogo = Catalogo::with('obra:id,no,descripcion')->find((int) $this->argument('catalogo'));

        if ($catalogo === null) {
            $this->error("No existe ningún catálogo con id '{$this->argument('catalogo')}'.");

            return self::FAILURE;
        }

        $this->info(sprintf(
            'Catálogo %s v%d (id %d, %s%s)',
            $catalogo->nombre,
            $catalogo->version,
            $catalogo->id,
            $catalogo->obra ? "obra {$catalogo->obra->no}, " : '',
            $catalogo->vigente ? 'vigente' : 'HISTÓRICO',
        ));
        $this->newLine();

        ['sobran' => $sobran, 'ocupadas' => $ocupadas] = $this->gemelas($catalogo);

        if ($ocupadas->isNotEmpty()) {
            $this->warn('Marcas repetidas que NO se tocan porque las dos tienen piezas o producción:');
            $this->line('  '.$ocupadas->implode(', '));
            $this->newLine();
        }

        if ($sobran->isEmpty()) {
            $this->line('No hay marcas duplicadas vacías que borrar.');

            return self::SUCCESS;
        }

        $this->table(
            ['Id', 'Marca', 'Lote', 'Cantidad', 'Precios asignados', 'Se queda'],
            $sobran->map(fn (array $fila): array => [
                $fila['concepto']->id,
                $fila['concepto']->marca,
                $fila['concepto']->lote ?? '—',
                (string) $fila['concepto']->cantidad,
                (string) $fila['precios'],
                "#{$fila['hermana']->id} lote ".($fila['hermana']->lote ?? '—')." ({$fila['piezas_hermana']} piezas)",
            ])->all(),
        );

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('DRY-RUN: no se borró nada. Se eliminarían '.$sobran->count().' marca(s).');
            $this->line('Vuelve a ejecutar con --force para borrar definitivamente.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($sobran): void {
            foreach ($sobran as $fila) {
                $this->traspasarPrecios($fila['concepto'], $fila['hermana']);
                $fila['concepto']->delete();
            }
        });

        $this->newLine();
        $this->info($sobran->count().' marca(s) duplicada(s) eliminada(s).');

        return self::SUCCESS;
    }

    /**
     * Reparte las marcas repetidas del catálogo en las que sobran (vacías, con
     * una hermana que sí tiene piezas) y las que no se pueden tocar.
     *
     * @return array{sobran: Collection<int, array{concepto: Concepto, hermana: Concepto, piezas_hermana: int, precios: int}>, ocupadas: Collection<int, string>}
     */
    private function gemelas(Catalogo $catalogo): array
    {
        $conceptos = Concepto::query()
            ->where('catalogo_id', $catalogo->id)
            ->withCount('piezas')
            ->orderBy('marca')
            ->get();

        $sobran = collect();
        $ocupadas = collect();

        foreach ($conceptos->groupBy('marca') as $marca => $grupo) {
            if ($grupo->count() < 2) {
                continue;
            }

            $conPiezas = $grupo->filter(fn (Concepto $c): bool => $c->piezas_count > 0);
            $vacias = $grupo->filter(fn (Concepto $c): bool => $c->piezas_count === 0 && $this->estaLibre($c));

            // Sin una hermana viva a la que apuntar no hay nada que consolidar, y
            // si más de una tiene piezas son modelos de verdad distintos.
            if ($conPiezas->count() !== 1 || $vacias->isEmpty()) {
                $ocupadas->push((string) $marca);

                continue;
            }

            $hermana = $conPiezas->first();

            foreach ($vacias as $vacia) {
                $sobran->push([
                    'concepto' => $vacia,
                    'hermana' => $hermana,
                    'piezas_hermana' => $hermana->piezas_count,
                    'precios' => GrupoPrecioConcepto::where('concepto_id', $vacia->id)->count(),
                ]);
            }
        }

        return ['sobran' => $sobran, 'ocupadas' => $ocupadas];
    }

    /** Una marca sin piezas todavía puede estar amarrada a algo pagado. */
    private function estaLibre(Concepto $concepto): bool
    {
        return ! LiquidacionDetalle::where('concepto_id', $concepto->id)->exists();
    }

    /**
     * Los grupos de precio de la marca vacía pasan a la que se queda; si ya
     * estaba en ese grupo, el renglón sobrante se borra.
     */
    private function traspasarPrecios(Concepto $vacia, Concepto $hermana): void
    {
        foreach (GrupoPrecioConcepto::where('concepto_id', $vacia->id)->get() as $precio) {
            $yaEsta = GrupoPrecioConcepto::where('concepto_id', $hermana->id)
                ->where('grupo_precio_id', $precio->grupo_precio_id)
                ->exists();

            $yaEsta
                ? $precio->delete()
                : $precio->update(['concepto_id' => $hermana->id]);
        }
    }
}
