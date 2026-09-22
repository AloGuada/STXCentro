<?php

namespace App\Console\Commands\Prod;

use App\Models\Concepto;
use App\Models\Prod\Catalogo;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\Pieza;
use App\Models\Prod\Registro;
use App\Models\Qal\Dossier;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\LoteAccesorio;
use App\Models\Qal\Modelo;
use App\Models\Qal\Obra as ObraDeCalidad;
use App\Models\Qal\ObraIncidencia;
use App\Models\Qal\ObraMontaje;
use App\Models\Qal\ObraPndPlan;
use App\Models\Qal\PndReporte;
use App\Models\Qal\Programacion;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deja un catálogo vacío: borra sus marcas, sus piezas (QS) y los precios que
 * tuvieran asignados, pero conserva el catálogo para volver a subirle el layout.
 *
 * Con --eliminar borra además el catálogo mismo, y si era el último de la obra
 * también la saca de Calidad: la obra entra ahí al crear el catálogo (ver
 * AppServiceProvider), así que sin catálogo no tiene por qué seguir en los
 * selectores.
 *
 * La producción capturada bloquea el borrado salvo que se pida con
 * --con-produccion: cada registro es trabajo que alguien reportó y borrarlo
 * cambia el avance de la obra. Las liquidaciones no se tocan nunca; guardan su
 * propio snapshot de la pieza, así que lo ya pagado sobrevive aunque la marca
 * desaparezca.
 *
 * Lo que Calidad ya trabajó no tiene bandera que lo salte: si hay inspecciones,
 * lotes, dosier o PND, el comando se cancela entero y no borra nada. Una
 * inspección sin catálogo no se puede reconstruir, y esa decisión no es de un
 * comando.
 *
 * Por defecto corre en modo dry-run (sólo reporta). Requiere --force para borrar.
 */
class VaciarCatalogoCommand extends Command
{
    protected $signature = 'prod:vaciar-catalogo
        {catalogo : Id del catálogo}
        {--con-produccion : Borra también la producción capturada de sus piezas}
        {--eliminar : Borra además el catálogo, y la obra en Calidad si era el último}
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
        $eliminar = (bool) $this->option('eliminar');
        $esElUltimo = $eliminar && ! Catalogo::where('obra_id', $catalogo->obra_id)
            ->whereKeyNot($catalogo->id)
            ->exists();
        $obraDeCalidad = $esElUltimo ? ObraDeCalidad::where('obra_id', $catalogo->obra_id)->first() : null;

        $plan = array_filter([
            'Marcas' => $conceptos->count(),
            'Piezas (QS)' => $piezas->count(),
            'Precios asignados' => GrupoPrecioConcepto::whereIn('concepto_id', $conceptos)->count(),
            'Registros de producción' => $this->option('con-produccion') ? $registros : 0,
            'Catálogo' => $eliminar ? 1 : 0,
            'Obra en Calidad' => $obraDeCalidad !== null ? 1 : 0,
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

        if ($eliminar) {
            $rastro = $esElUltimo
                ? $this->rastroDeLaObra($catalogo->obra_id, $obraDeCalidad)
                : $this->rastroDelCatalogo($catalogo);

            if ($rastro !== []) {
                $this->newLine();
                $this->error('Calidad ya trabajó esta obra: '.$this->enumerar($rastro).'.');
                $this->line('Se cancela el comando y no se borró nada. Sin --eliminar el catálogo se puede vaciar igual.');

                return self::FAILURE;
            }

            $this->avisosDeVersiones($catalogo);
        }

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('DRY-RUN: no se borró nada. Se eliminarían '.array_sum($plan).' registros.');
            $this->line('Vuelve a ejecutar con --force para borrar definitivamente.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($catalogo, $conceptos, $piezas, $eliminar, $obraDeCalidad): void {
            // Explícito aunque las llaves cascadeen: así el conteo del plan es lo
            // que realmente se borra y no depende del motor de base de datos.
            Registro::whereIn('pieza_id', $piezas)->delete();
            Pieza::where('catalogo_id', $catalogo->id)->delete();
            GrupoPrecioConcepto::whereIn('concepto_id', $conceptos)->delete();
            Concepto::where('catalogo_id', $catalogo->id)->delete();

            if ($eliminar) {
                $obraDeCalidad?->delete();
                $catalogo->delete();
            }
        });

        $this->newLine();
        $this->info(sprintf(
            'Catálogo %s v%d %s: %d registros eliminados.',
            $catalogo->nombre,
            $catalogo->version,
            $eliminar ? 'eliminado' : 'vaciado',
            array_sum($plan),
        ));

        if ($obraDeCalidad !== null) {
            $this->line("La obra {$catalogo->obra?->no} salió de Calidad: se quedó sin catálogos.");
        }

        return self::SUCCESS;
    }

    /**
     * Lo que Calidad colgó de este catálogo en concreto. Se revisa cuando la
     * obra conserva otras versiones: lo suyo sigue en pie, pero lo que apunta
     * a esta versión quedaría sin saber de dónde vino.
     *
     * @return array<string, int>
     */
    private function rastroDelCatalogo(Catalogo $catalogo): array
    {
        return array_filter([
            'inspecciones' => Inspeccion::where('catalogo_id', $catalogo->id)->count(),
            'modelos' => Modelo::where('catalogo_id', $catalogo->id)->count(),
        ]);
    }

    /**
     * Todo lo que Calidad tiene de la obra, cuelgue o no del catálogo. Se
     * revisa cuando éste es el último: al irse, la obra sale de Calidad entera.
     *
     * @return array<string, int>
     */
    private function rastroDeLaObra(?int $obraId, ?ObraDeCalidad $obraDeCalidad): array
    {
        $deLaObra = [
            'inspecciones' => Inspeccion::where('obra_id', $obraId)->count(),
            'modelos' => Modelo::where('obra_id', $obraId)->count(),
            'lotes de accesorios' => LoteAccesorio::where('obra_id', $obraId)->count(),
            'dosier' => Dossier::where('obra_id', $obraId)->count(),
            'planes de avance' => Programacion::where('obra_id', $obraId)->count(),
        ];

        if ($obraDeCalidad !== null) {
            $deLaObra += [
                'plan de PND' => ObraPndPlan::where('qal_obra_id', $obraDeCalidad->id)->count(),
                'reportes de PND' => PndReporte::where('qal_obra_id', $obraDeCalidad->id)->count(),
                'semanas de montaje' => ObraMontaje::where('qal_obra_id', $obraDeCalidad->id)->count(),
                'incidencias' => ObraIncidencia::where('qal_obra_id', $obraDeCalidad->id)->count(),
            ];
        }

        return array_filter($deLaObra);
    }

    /**
     * Lo que le pasa al resto de las versiones. No impide el borrado, pero se
     * dice antes de hacerlo.
     */
    private function avisosDeVersiones(Catalogo $catalogo): void
    {
        $derivadas = Catalogo::where('catalogo_origen_id', $catalogo->id)->count();

        if ($derivadas > 0) {
            $this->newLine();
            $this->warn("{$derivadas} versión(es) se copiaron de ésta y se quedarán sin origen.");
        }

        if ($catalogo->vigente && Catalogo::where('obra_id', $catalogo->obra_id)->whereKeyNot($catalogo->id)->exists()) {
            $this->newLine();
            $this->warn('Es la versión vigente: la obra se queda sin catálogo vigente hasta que marques otra.');
        }
    }

    /**
     * @param  array<string, int>  $rastro
     */
    private function enumerar(array $rastro): string
    {
        return collect($rastro)
            ->map(fn (int $n, string $que): string => "{$n} {$que}")
            ->implode(', ');
    }
}
