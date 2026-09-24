<?php

namespace App\Console\Commands\Prod;

use App\Models\Concepto;
use App\Models\Prod\Catalogo;
use App\Models\Prod\GrupoPrecioConcepto;
use App\Models\Prod\Pieza;
use App\Models\Prod\Registro;
use App\Models\Qal\Dossier;
use App\Models\Qal\DossierArchivo;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\LoteAccesorio;
use App\Models\Qal\Modelo;
use App\Models\Qal\ModeloCordon;
use App\Models\Qal\Obra as ObraDeCalidad;
use App\Models\Qal\ObraIncidencia;
use App\Models\Qal\ObraMontaje;
use App\Models\Qal\ObraPndPlan;
use App\Models\Qal\PndReporte;
use App\Models\Qal\Programacion;
use App\Models\Qal\ProgramacionMarca;
use App\Models\Qal\Sublote;
use App\Services\Qal\RegistradorInspeccion;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

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
 * Lo que Calidad ya trabajó también bloquea, y sólo --con-calidad lo borra: las
 * inspecciones, modelos IFC, lotes de accesorios, marcas del plan de avance e
 * informes de PND que cuelgan del catálogo o de sus marcas. Si con --eliminar era
 * el último catálogo, la obra sale de Calidad entera y el rastro es todo lo de
 * la obra: además dosier, informes PND, montaje, incidencias y plan de PND. Los
 * archivos (fotos, PDF, IFC) se borran del disco cuando la transacción confirma.
 *
 * Por defecto corre en modo dry-run (sólo reporta). Requiere --force para borrar.
 */
class VaciarCatalogoCommand extends Command
{
    protected $signature = 'prod:vaciar-catalogo
        {catalogo : Id del catálogo}
        {--con-produccion : Borra también la producción capturada de sus piezas}
        {--con-calidad : Borra también lo que Calidad capturó sobre el catálogo (o sobre la obra, si con --eliminar era el último)}
        {--eliminar : Borra además el catálogo, y la obra en Calidad si era el último}
        {--force : Ejecuta el borrado; sin esta bandera sólo muestra el dry-run}';

    protected $description = 'Vacía un catálogo de producción: borra sus marcas, piezas y precios asignados, y lo deja listo para volver a importar el layout';

    public function handle(RegistradorInspeccion $registrador): int
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
        $conCalidad = (bool) $this->option('con-calidad');
        $esElUltimo = $eliminar && ! Catalogo::where('obra_id', $catalogo->obra_id)
            ->whereKeyNot($catalogo->id)
            ->exists();
        $obraDeCalidad = $esElUltimo ? ObraDeCalidad::where('obra_id', $catalogo->obra_id)->first() : null;

        $calidad = $esElUltimo
            ? $this->calidadDeLaObra($catalogo->obra_id, $obraDeCalidad)
            : $this->calidadDelCatalogo($catalogo, $conceptos, $piezas);
        $rastro = array_filter(array_map(fn (Builder $consulta): int => $consulta->count(), $calidad));

        $plan = array_filter([
            'Marcas' => $conceptos->count(),
            'Piezas (QS)' => $piezas->count(),
            'Precios asignados' => GrupoPrecioConcepto::whereIn('concepto_id', $conceptos)->count(),
            'Registros de producción' => $this->option('con-produccion') ? $registros : 0,
            ...($conCalidad ? collect($rastro)->mapWithKeys(fn (int $n, string $que): array => ["Calidad: {$que}" => $n])->all() : []),
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

        if ($rastro !== [] && ! $conCalidad) {
            $this->newLine();
            $this->error('Calidad ya trabajó esta obra: '.$this->enumerar($rastro).'.');
            $this->line('Se cancela el comando y no se borró nada. Usa --con-calidad para borrarlo también.');

            return self::FAILURE;
        }

        if ($eliminar) {
            $this->avisosDeVersiones($catalogo);
        }

        if (! $this->option('force')) {
            $this->newLine();
            $this->warn('DRY-RUN: no se borró nada. Se eliminarían '.array_sum($plan).' registros.');
            $this->line('Vuelve a ejecutar con --force para borrar definitivamente.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($catalogo, $conceptos, $piezas, $eliminar, $obraDeCalidad, $conCalidad, $calidad, $registrador): void {
            if ($conCalidad) {
                foreach ($calidad as $consulta) {
                    $this->borrarDeCalidad($consulta, $registrador);
                }
            }

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
     * Lo que Calidad colgó de este catálogo en concreto, por su id o por sus
     * marcas y piezas. Se revisa cuando la obra conserva otras versiones o el
     * catálogo sólo se vacía: lo demás de la obra sigue en pie.
     *
     * Un informe de PND se va entero en cuanto trae una junta de sus marcas,
     * aunque también traiga juntas de otras: el informe es un solo documento
     * del laboratorio y no se deja a medias.
     *
     * El orden es el del borrado: las inspecciones van antes que los modelos
     * porque sus juntas apuntan a los cordones sin cascada.
     *
     * @param  Collection<int, int>  $conceptos
     * @param  Collection<int, int>  $piezas
     * @return array<string, Builder>
     */
    private function calidadDelCatalogo(Catalogo $catalogo, Collection $conceptos, Collection $piezas): array
    {
        $cordones = ModeloCordon::query()
            ->whereHas('marca.modelo', fn (Builder $modelo) => $modelo->where('catalogo_id', $catalogo->id))
            ->select('id');

        return [
            'inspecciones' => Inspeccion::query()->where(fn (Builder $inspeccion) => $inspeccion
                ->where('catalogo_id', $catalogo->id)
                ->orWhereIn('concepto_id', $conceptos)
                ->orWhereIn('prod_pieza_id', $piezas)
                ->orWhereHas('juntas', fn (Builder $junta) => $junta->whereIn('cordon_id', $cordones))),
            'modelos' => Modelo::query()->where('catalogo_id', $catalogo->id),
            'lotes de accesorios' => LoteAccesorio::query()->whereIn('concepto_id', $conceptos),
            'marcas en planes de avance' => ProgramacionMarca::query()->whereIn('concepto_id', $conceptos),
            'reportes de PND' => PndReporte::query()
                ->whereHas('juntas', fn (Builder $junta) => $junta->whereIn('concepto_id', $conceptos)),
        ];
    }

    /**
     * Todo lo que Calidad tiene de la obra, cuelgue o no del catálogo. Se
     * revisa cuando éste es el último: al irse, la obra sale de Calidad entera.
     *
     * @return array<string, Builder>
     */
    private function calidadDeLaObra(?int $obraId, ?ObraDeCalidad $obraDeCalidad): array
    {
        $deLaObra = [
            'inspecciones' => Inspeccion::query()->where('obra_id', $obraId),
            'modelos' => Modelo::query()->where('obra_id', $obraId),
            'lotes de accesorios' => LoteAccesorio::query()->where('obra_id', $obraId),
            'dosier' => Dossier::query()->where('obra_id', $obraId),
            'planes de avance' => Programacion::query()->where('obra_id', $obraId),
        ];

        if ($obraDeCalidad !== null) {
            $deLaObra += [
                'plan de PND' => ObraPndPlan::query()->where('qal_obra_id', $obraDeCalidad->id),
                'reportes de PND' => PndReporte::query()->where('qal_obra_id', $obraDeCalidad->id),
                'semanas de montaje' => ObraMontaje::query()->where('qal_obra_id', $obraDeCalidad->id),
                'incidencias' => ObraIncidencia::query()->where('qal_obra_id', $obraDeCalidad->id),
            ];
        }

        return $deLaObra;
    }

    /**
     * Borra lo que trae la consulta junto con lo que la base no alcanza a
     * cascadear: archivos en disco y reinspecciones de sublotes. Los archivos
     * se van al confirmar la transacción, como en los controladores de Calidad.
     */
    private function borrarDeCalidad(Builder $consulta, RegistradorInspeccion $registrador): void
    {
        $modelo = $consulta->getModel();

        if ($modelo instanceof Inspeccion) {
            $consulta->lazyById()->each(fn (Inspeccion $inspeccion) => $registrador->borrar($inspeccion));

            return;
        }

        if ($modelo instanceof Modelo) {
            $consulta->lazyById()->each(function (Modelo $ifc): void {
                $carpeta = $ifc->carpeta();
                $ifc->delete();

                DB::afterCommit(function () use ($carpeta): void {
                    Storage::disk('local')->deleteDirectory($carpeta);
                    Storage::disk('public')->deleteDirectory($carpeta);
                });
            });

            return;
        }

        if ($modelo instanceof LoteAccesorio) {
            $lotes = $consulta->pluck('id');

            // El sublote original no se borra mientras tenga reinspecciones.
            Sublote::whereIn('lote_id', $lotes)->whereNotNull('sublote_origen_id')->delete();
            Sublote::whereIn('lote_id', $lotes)->delete();
            LoteAccesorio::whereKey($lotes)->delete();

            return;
        }

        if ($modelo instanceof PndReporte) {
            $consulta->with('fotos:id,qal_pnd_reporte_id,ruta')->lazyById()->each(function (PndReporte $pnd): void {
                $rutas = $pnd->fotos->pluck('ruta')->push($pnd->archivo_pdf)->filter()->all();
                $pnd->delete();

                DB::afterCommit(fn () => Storage::disk('public')->delete($rutas));
            });

            return;
        }

        if ($modelo instanceof Dossier) {
            $consulta->lazyById()->each(function (Dossier $dossier): void {
                $carpeta = $dossier->carpeta();
                $dossier->delete();

                DB::afterCommit(fn () => Storage::disk(DossierArchivo::DISCO)->deleteDirectory($carpeta));
            });

            return;
        }

        $consulta->delete();
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
