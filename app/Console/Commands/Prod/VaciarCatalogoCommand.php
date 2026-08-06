<?php

namespace App\Console\Commands\Prod;

use App\Models\Concepto;
use App\Models\Prod\Catalogo;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\Pieza;
use App\Models\Prod\Registro;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deja un catálogo vacío: borra sus marcas, sus piezas (QS) y los precios que
 * tuvieran asignados, pero conserva el catálogo para volver a subirle el layout.
 *
 * La producción capturada bloquea el borrado salvo que se pida con
 * --con-produccion: cada registro es trabajo que alguien reportó y borrarlo
 * cambia el avance de la obra. Las liquidaciones no se tocan nunca; guardan su
 * propio snapshot de la pieza, así que lo ya pagado sobrevive aunque la marca
 * desaparezca.
 *
 * Por defecto corre en modo dry-run (sólo reporta). Requiere --force para borrar.
 */
class VaciarCatalogoCommand extends Command
{
    protected $signature = 'prod:vaciar-catalogo
        {catalogo : Id del catálogo}
        {--con-produccion : Borra también la producción capturada de sus piezas}
        {--force : Ejecuta el borrado; sin esta bandera sólo muestra el dry-run}';

    protected $description = 'Vacía un catálogo de producción: borra sus marcas, piezas y precios asignados, y lo deja listo para volver a importar el layout';

    public function handle(): int
    {
        $catalogo = Catalogo::with('obra:id,no,descripcion')->find((int) $this->argument('catalogo'));

        if ($catalogo === null) {
            $this->error("No existe ningún catálogo con id '{$this->argument('catalogo')}'.");

            return self::FAILURE;
        }

        $conceptos = Concepto::where('catalogo_id', $catalogo->id)->pluck('id');
        $piezas = Pieza::where('catalogo_id', $catalogo->id)->pluck('id');
        $registros = Registro::whereIn('pieza_id', $piezas)->count();

        $plan = array_filter([
            'Marcas' => $conceptos->count(),
            'Piezas (QS)' => $piezas->count(),
            'Precios asignados' => GrupoPrecioConcepto::whereIn('concepto_id', $conceptos)->count(),
            'Registros de producción' => $this->option('con-produccion') ? $registros : 0,
        ]);

        $this->info(sprintf(
            'Catálogo %s v%d (id %d, %s%s)',
            $catalogo->nombre,
            $catalogo->version,
            $catalogo->id,
            $catalogo->obra ? "obra {$catalogo->obra->no}, " : '',
            $catalogo->vigente ? 'vigente' : 'HISTÓRICO',
        ));
        $this->newLine();

        if ($plan === []) {
            $this->line('Ya está vacío: no tiene marcas ni piezas.');

            return self::SUCCESS;
        }

        $this->table(
            ['Tabla / entidad', 'Registros'],
            collect($plan)->map(fn (int $n, string $t): array => [$t, (string) $n])->values()->all(),
        );

        if ($registros > 0 && ! $this->option('con-produccion')) {
            $this->newLine();
            $this->error("El catálogo tiene {$registros} registro(s) de producción capturada sobre sus piezas.");
            $this->line('Usa --con-produccion para borrarlos también, o borra primero los destajos con prod:borrar-destajo.');

            return self::FAILURE;
        }

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('DRY-RUN: no se borró nada. Se eliminarían '.array_sum($plan).' registros.');
            $this->line('Vuelve a ejecutar con --force para borrar definitivamente.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($catalogo, $conceptos, $piezas): void {
            // Explícito aunque las llaves cascadeen: así el conteo del plan es lo
            // que realmente se borra y no depende del motor de base de datos.
            Registro::whereIn('pieza_id', $piezas)->delete();
            Pieza::where('catalogo_id', $catalogo->id)->delete();
            GrupoPrecioConcepto::whereIn('concepto_id', $conceptos)->delete();
            Concepto::where('catalogo_id', $catalogo->id)->delete();
        });

        $this->newLine();
        $this->info("Catálogo {$catalogo->nombre} v{$catalogo->version} vaciado: ".array_sum($plan).' registros eliminados.');

        return self::SUCCESS;
    }
}
