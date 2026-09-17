<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Enums\Qal\AreaIncidencia;
use App\Enums\Qal\DepartamentoIncidencia;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Qal\ObraIncidenciaRequest;
use App\Http\Requests\Admin\Qal\ObraMontajeRequest;
use App\Models\Qal\Obra;
use App\Models\Qal\ObraIncidencia;
use App\Models\Qal\ObraMontaje;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Incidencias en obra: lo que falla durante el montaje.
 *
 * Es un circuito aparte del taller. La captura del inspector mide lo que se
 * rechaza en planta; esto mide lo que aparece en sitio, ya con la pieza
 * montada, y se atribuye a un departamento responsable para saber a quién
 * mandar la acción correctiva.
 *
 * Sustituye al Excel «ESTADISTICAS INCIDENCIAS EN OBRA» y corrige su defecto de
 * raíz: allá el avance de montaje y las incidencias comparten fila aunque sean
 * hechos de grano distinto, y basta que la cifra de montaje caiga en la fila
 * equivocada para que el porcentaje se dispare —en el archivo de este año hay
 * una obra con 1 pieza montada y 23 defectos—. Aquí el denominador vive en
 * `qal_obra_montaje`, uno por obra y semana, y las incidencias cuelgan aparte:
 * añadir tres es pulsar «Añadir» tres veces sin tocar nada de lo anterior.
 *
 * La pantalla son dos: la portada compara obras entre sí y la de la obra es
 * donde se captura. Se separan porque responden preguntas distintas —«¿cuál va
 * peor?» y «¿qué pasó esta semana aquí?»— y porque la de captura es la que se
 * abre veinte veces por semana.
 */
class IncidenciasController extends Controller
{
    /**
     * La portada: todas las obras del año, de la peor a la mejor.
     *
     * Ordena por porcentaje y no por nombre a propósito. La lista existe para
     * decir dónde hay que mirar, y alfabético esconde eso.
     */
    public function index(Request $request): Response
    {
        $anios = $this->aniosConDatos();
        $anio = $this->elegido($request->integer('anio'), $anios);
        $semanaActual = $this->semanaDeHoy($anio);

        $montaje = $this->montajePorObra($anio, $semanaActual);
        $incidencias = $this->incidenciasPorObra($anio, $semanaActual);

        $obras = Obra::query()
            ->conDatosDeLaObra()
            ->whereIn('qal_obras.id', $montaje->keys()->merge($incidencias->keys())->unique())
            ->orderBy('obras.no')
            ->get()
            ->map(function (Obra $obra) use ($montaje, $incidencias): array {
                $m = $montaje->get($obra->id, ['pz_montadas' => 0, 'pz_montadas_semana' => 0, 'semanas' => 0]);
                $i = $incidencias->get($obra->id, ['incidencias' => 0, 'pz_defecto' => 0, 'abiertas' => 0, 'incidencias_semana' => 0]);

                return [
                    'id' => $obra->id,
                    'no' => $obra->no,
                    'descripcion' => $obra->descripcion,
                    'pz_total' => $obra->pz_total,
                    ...$m,
                    ...$i,
                    'tasa' => $this->tasa($i['pz_defecto'], $m['pz_montadas']),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('admin/calidad/incidencias/index', [
            'anio' => $anio,
            'anios' => $anios,
            'semanaActual' => $semanaActual,
            'obras' => $obras,
            'sinCapturar' => $this->obrasSinCapturar($obras),
            'graficas' => [
                'porSemana' => $this->porSemana($anio),
                'porDepartamento' => $this->porDepartamento($anio),
                'porMes' => $this->porMes($anio),
            ],
        ]);
    }

    /**
     * La obra: la semana que se está capturando y todo su año.
     */
    public function show(Request $request, Obra $obra): Response
    {
        $anios = $this->aniosConDatos($obra);
        $anio = $this->elegido($request->integer('anio'), $anios);

        $semanas = $this->semanasDelAnio($anio);
        $semana = in_array($request->integer('semana'), $semanas, true)
            ? $request->integer('semana')
            : $this->semanaDeHoy($anio);

        $incidencias = $obra->incidencias()
            ->where('anio', $anio)
            ->with('capturista:id,name')
            ->orderByDesc('semana')
            ->orderByDesc('id')
            ->get();

        $montaje = $obra->montaje()
            ->where('anio', $anio)
            ->orderBy('semana')
            ->get();

        return Inertia::render('admin/calidad/incidencias/show', [
            'obra' => [
                'id' => $obra->id,
                'no' => $obra->no,
                'descripcion' => $obra->descripcion,
                'pz_total' => $obra->pz_total,
            ],
            'anio' => $anio,
            'anios' => $anios,
            'semana' => $semana,
            'semanaActual' => $this->semanaDeHoy($anio),
            'semanas' => array_map(fn (int $numero): array => [
                'numero' => $numero,
                'rango' => $this->rango($anio, $numero),
            ], $semanas),
            'montajeSemana' => $montaje->firstWhere('semana', $semana),
            'incidencias' => $incidencias,
            'historial' => $this->historial($anio, $montaje, $incidencias),
            'areas' => AreaIncidencia::opciones(),
            'departamentos' => DepartamentoIncidencia::opciones(),
            'graficas' => [
                'porSemana' => $this->porSemana($anio, $obra),
                'porDepartamento' => $this->porDepartamento($anio, $obra),
            ],
        ]);
    }

    /**
     * Guarda el avance de la semana. Es una cifra por obra y semana, así que se
     * sobreescribe en vez de acumular filas.
     */
    public function guardarMontaje(ObraMontajeRequest $request, Obra $obra): RedirectResponse
    {
        $obra->montaje()->updateOrCreate(
            ['anio' => $request->integer('anio'), 'semana' => $request->integer('semana')],
            $request->safe()->except(['anio', 'semana']) + ['capturista_id' => $request->user()?->getKey()],
        );

        return back()->with('success', 'Avance de montaje guardado.');
    }

    /**
     * Deja constancia de que la semana se revisó y no hubo hallazgos.
     *
     * No es lo mismo que una semana en blanco, y por eso es un dato y no un
     * hueco: en blanco puede significar que nadie la revisó.
     */
    public function sinIncidencias(Request $request, Obra $obra): RedirectResponse
    {
        $datos = $request->validate([
            'anio' => ['required', 'integer', 'min:2000', 'max:2100'],
            'semana' => ['required', 'integer', 'min:1', 'max:53'],
            'sin_incidencias' => ['required', 'boolean'],
        ]);

        $obra->montaje()->updateOrCreate(
            ['anio' => $datos['anio'], 'semana' => $datos['semana']],
            ['sin_incidencias' => $datos['sin_incidencias'], 'capturista_id' => $request->user()?->getKey()],
        );

        return back()->with('success', $datos['sin_incidencias']
            ? 'Semana registrada como revisada sin incidencias.'
            : 'Se quitó la marca de «sin incidencias».');
    }

    /**
     * Borrar el avance NO se lleva las incidencias de esa semana.
     *
     * Son dos hechos distintos y perder uno no debería arrastrar al otro. Lo
     * que sí pasa es que esas incidencias se quedan sin denominador, y la tabla
     * las marca «sin base» en lugar de calcular un porcentaje.
     */
    public function borrarMontaje(Obra $obra, ObraMontaje $montaje): RedirectResponse
    {
        abort_unless($montaje->qal_obra_id === $obra->id, 404);

        $montaje->delete();

        return back()->with('success', 'Avance de montaje borrado. Las incidencias de esa semana se conservan.');
    }

    public function store(ObraIncidenciaRequest $request, Obra $obra): RedirectResponse
    {
        $obra->incidencias()->create(
            $request->validated() + ['capturista_id' => $request->user()?->getKey()],
        );

        // Registrar un hallazgo contradice haber declarado la semana limpia.
        $obra->montaje()
            ->where('anio', $request->integer('anio'))
            ->where('semana', $request->integer('semana'))
            ->update(['sin_incidencias' => false]);

        return back()->with('success', 'Incidencia añadida.');
    }

    /** Cerrar y reabrir son el mismo botón: el estado sale de la fecha. */
    public function cambiarEstado(Obra $obra, ObraIncidencia $incidencia): RedirectResponse
    {
        abort_unless($incidencia->qal_obra_id === $obra->id, 404);

        $cerrar = $incidencia->cerrada_en === null;
        $incidencia->update(['cerrada_en' => $cerrar ? now() : null]);

        return back()->with('success', $cerrar ? 'Incidencia cerrada.' : 'Incidencia reabierta.');
    }

    public function destroy(Obra $obra, ObraIncidencia $incidencia): RedirectResponse
    {
        abort_unless($incidencia->qal_obra_id === $obra->id, 404);

        $incidencia->delete();

        return back()->with('success', 'Incidencia eliminada.');
    }

    /**
     * La tabla semana a semana del año, con las semanas que tienen algo.
     *
     * Sólo se listan las semanas con avance o con incidencias: cincuenta y dos
     * renglones vacíos no dicen nada y esconden los tres que importan.
     *
     * @param  Collection<int, ObraMontaje>  $montaje
     * @param  Collection<int, ObraIncidencia>  $incidencias
     * @return list<array<string, mixed>>
     */
    private function historial(int $anio, Collection $montaje, Collection $incidencias): array
    {
        $porSemana = $incidencias->groupBy('semana');

        return $montaje->pluck('semana')
            ->merge($porSemana->keys())
            ->unique()
            ->sortDesc()
            ->map(function (int $semana) use ($anio, $montaje, $porSemana): array {
                $fila = $montaje->firstWhere('semana', $semana);
                $suyas = $porSemana->get($semana, collect());
                $defecto = (int) $suyas->sum('pz_defecto');

                return [
                    'semana' => $semana,
                    'rango' => $this->rango($anio, $semana),
                    'montaje_id' => $fila?->id,
                    'pz_montadas' => $fila?->pz_montadas,
                    'sin_incidencias' => (bool) $fila?->sin_incidencias,
                    'notas' => $fila?->notas,
                    'incidencias' => $suyas->count(),
                    'pz_defecto' => $defecto,
                    // «Sin base» y «0 %» son cosas distintas: la primera dice
                    // que falta el denominador, la segunda que salió limpio.
                    'tasa' => $this->tasa($defecto, $fila?->pz_montadas),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Piezas montadas por obra en el año, y las de la semana en curso.
     *
     * @return Collection<int, array{pz_montadas: int, pz_montadas_semana: int, semanas: int}>
     */
    private function montajePorObra(int $anio, int $semanaActual): Collection
    {
        return ObraMontaje::query()
            ->where('anio', $anio)
            ->groupBy('qal_obra_id')
            ->selectRaw('qal_obra_id, sum(pz_montadas) as total, sum(case when semana = ? then pz_montadas else 0 end) as de_la_semana, count(pz_montadas) as semanas', [$semanaActual])
            ->get()
            ->mapWithKeys(fn ($fila): array => [
                (int) $fila->qal_obra_id => [
                    'pz_montadas' => (int) $fila->total,
                    'pz_montadas_semana' => (int) $fila->de_la_semana,
                    'semanas' => (int) $fila->semanas,
                ],
            ]);
    }

    /**
     * @return Collection<int, array{incidencias: int, pz_defecto: int, abiertas: int, incidencias_semana: int}>
     */
    private function incidenciasPorObra(int $anio, int $semanaActual): Collection
    {
        return ObraIncidencia::query()
            ->where('anio', $anio)
            ->groupBy('qal_obra_id')
            ->selectRaw('qal_obra_id, count(*) as total, sum(pz_defecto) as defecto, sum(case when cerrada_en is null then 1 else 0 end) as abiertas, sum(case when semana = ? then 1 else 0 end) as de_la_semana', [$semanaActual])
            ->get()
            ->mapWithKeys(fn ($fila): array => [
                (int) $fila->qal_obra_id => [
                    'incidencias' => (int) $fila->total,
                    'pz_defecto' => (int) $fila->defecto,
                    'abiertas' => (int) $fila->abiertas,
                    'incidencias_semana' => (int) $fila->de_la_semana,
                ],
            ]);
    }

    /**
     * Las obras activas que no aparecen en la portada porque no tienen nada
     * capturado en el año.
     *
     * Salen aparte y no como filas en cero: una obra sin captura no es una obra
     * sin incidencias, y mezclarlas volvería a confundir «revisado» con «vacío».
     *
     * @param  list<array<string, mixed>>  $conDatos
     * @return list<array{id: int, no: string}>
     */
    private function obrasSinCapturar(array $conDatos): array
    {
        return Obra::query()
            ->conDatosDeLaObra()
            ->activas()
            ->whereNotIn('qal_obras.id', array_column($conDatos, 'id'))
            ->orderBy('obras.no')
            ->get()
            ->map(fn (Obra $obra): array => ['id' => $obra->id, 'no' => $obra->no])
            ->all();
    }

    /**
     * Piezas con defecto semana a semana.
     *
     * @return list<array{semana: int, pz: int}>
     */
    private function porSemana(int $anio, ?Obra $obra = null): array
    {
        return ObraIncidencia::query()
            ->where('anio', $anio)
            ->when($obra, fn ($consulta) => $consulta->where('qal_obra_id', $obra->id))
            ->groupBy('semana')
            ->orderBy('semana')
            ->selectRaw('semana, sum(pz_defecto) as pz')
            ->get()
            ->map(fn ($fila): array => ['semana' => (int) $fila->semana, 'pz' => (int) $fila->pz])
            ->all();
    }

    /**
     * Piezas con defecto por departamento responsable.
     *
     * Es el corte que decide la acción correctiva, así que se devuelven sólo
     * los departamentos con algo: una lista de siete ceros no ordena nada.
     *
     * @return list<array{clave: string, etiqueta: string, pz: int}>
     */
    private function porDepartamento(int $anio, ?Obra $obra = null): array
    {
        $totales = ObraIncidencia::query()
            ->where('anio', $anio)
            ->when($obra, fn ($consulta) => $consulta->where('qal_obra_id', $obra->id))
            ->groupBy('departamento')
            ->selectRaw('departamento, sum(pz_defecto) as pz')
            ->pluck('pz', 'departamento');

        return collect(DepartamentoIncidencia::cases())
            ->map(fn (DepartamentoIncidencia $departamento): array => [
                'clave' => $departamento->value,
                'etiqueta' => $departamento->etiqueta(),
                'pz' => (int) $totales->get($departamento->value, 0),
            ])
            ->filter(fn (array $fila): bool => $fila['pz'] > 0)
            ->sortByDesc('pz')
            ->values()
            ->all();
    }

    /**
     * Piezas con defecto por mes.
     *
     * El mes de una semana es el de su **jueves**: es la regla ISO, y evita que
     * una semana a caballo entre dos meses se cuente en los dos.
     *
     * @return list<array{mes: int, etiqueta: string, pz: int}>
     */
    private function porMes(int $anio): array
    {
        $porSemana = ObraIncidencia::query()
            ->where('anio', $anio)
            ->groupBy('semana')
            ->selectRaw('semana, sum(pz_defecto) as pz')
            ->pluck('pz', 'semana');

        $meses = [];

        foreach ($porSemana as $semana => $pz) {
            $mes = Carbon::now()->setISODate($anio, (int) $semana, 4)->month;
            $meses[$mes] = ($meses[$mes] ?? 0) + (int) $pz;
        }

        ksort($meses);

        return collect($meses)
            ->map(fn (int $pz, int $mes): array => [
                'mes' => $mes,
                'etiqueta' => Carbon::create($anio, $mes, 1)->locale('es')->monthName,
                'pz' => $pz,
            ])
            ->values()
            ->all();
    }

    /** Sin denominador no hay porcentaje: nulo, no cero. */
    private function tasa(int $defecto, ?int $montadas): ?float
    {
        return $montadas > 0 ? round($defecto * 100 / $montadas, 2) : null;
    }

    /** Lunes y domingo de la semana ISO, en palabras. */
    private function rango(int $anio, int $semana): string
    {
        $lunes = Carbon::now()->setISODate($anio, $semana)->locale('es');

        return $lunes->isoFormat('D MMM').' – '.$lunes->copy()->addDays(6)->isoFormat('D MMM');
    }

    /**
     * Todas las semanas del año.
     *
     * El 28 de diciembre cae siempre en la última semana ISO, así que su número
     * dice si el año tiene 52 o 53.
     *
     * @return list<int>
     */
    private function semanasDelAnio(int $anio): array
    {
        return range(1, (int) Carbon::create($anio, 12, 28)->isoWeek());
    }

    /**
     * La semana con la que se abre la obra.
     *
     * En el año en curso es la de hoy; en uno pasado, la última, porque abrir
     * un año cerrado en la semana 34 de septiembre no tiene sentido.
     */
    private function semanaDeHoy(int $anio): int
    {
        $hoy = Carbon::now();

        return (int) $hoy->isoFormat('GGGG') === $anio
            ? (int) $hoy->isoFormat('W')
            : (int) Carbon::create($anio, 12, 28)->isoWeek();
    }

    /**
     * Los años con algo capturado, más el actual: el módulo se abre para
     * capturar la semana en curso aunque todavía no haya nada.
     *
     * @return list<int>
     */
    private function aniosConDatos(?Obra $obra = null): array
    {
        $de = fn (string $modelo): Collection => $modelo::query()
            ->when($obra, fn ($consulta) => $consulta->where('qal_obra_id', $obra->id))
            ->select('anio')
            ->distinct()
            ->pluck('anio');

        return $de(ObraMontaje::class)
            ->merge($de(ObraIncidencia::class))
            ->push(Carbon::now()->isoFormat('GGGG'))
            ->map(fn ($anio): int => (int) $anio)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $disponibles
     */
    private function elegido(int $pedido, array $disponibles): int
    {
        return in_array($pedido, $disponibles, true) ? $pedido : (int) ($disponibles[0] ?? 0);
    }
}
