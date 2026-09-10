<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Enums\Qal\MetodoPnd;
use App\Enums\Qal\ResultadoPnd;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Qal\PndPlanRequest;
use App\Http\Requests\Admin\Qal\PndReporteRequest;
use App\Models\Qal\Laboratorio;
use App\Models\Qal\Obra;
use App\Models\Qal\PndReporte;
use App\Models\Qal\Soldador;
use App\Services\Qal\RegistradorInformePnd;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pruebas no destructivas: el informe del laboratorio y el avance del contrato.
 *
 * PND corre en paralelo a la captura del inspector y no se cruza con ella. Ahí
 * se cuentan piezas revisadas a la vista; aquí, juntas soldadas evaluadas por un
 * laboratorio externo. Son universos con denominadores distintos, así que **no
 * se suman** y en el tablero van en bloques separados.
 *
 * El laboratorio no entra al sistema: entrega su informe y aquí se teclea.
 */
class PndController extends Controller
{
    public function __construct(private readonly RegistradorInformePnd $registrador) {}

    /**
     * La pantalla es de una obra a la vez.
     *
     * El avance de PND sólo significa algo contra el contrato de una obra —el
     * plan comprometido es por obra y por método—, así que una tabla de todas
     * las obras juntas mostraría una suma que nadie firmó.
     */
    public function index(Request $request): Response
    {
        $obras = Obra::query()
            ->conDatosDeLaObra()
            ->orderByDesc('obras.activa')
            ->orderBy('obras.no')
            ->get();

        $obra = $this->obraDelFiltro($request, $obras->pluck('id')->all());

        $reportes = PndReporte::query()
            ->when($obra, fn ($consulta) => $consulta->where('qal_obra_id', $obra->id))
            ->when($request->filled('metodo'), fn ($consulta) => $consulta->where('metodo', $request->string('metodo')))
            ->when($request->filled('anio'), fn ($consulta) => $consulta->where('anio', $request->integer('anio')))
            ->when($request->filled('search'), fn ($consulta) => $consulta->where('reporte_no', 'like', '%'.$request->string('search').'%'))
            ->with('laboratorio:id,nombre,siglas')
            ->withCount($this->conteosDeLaRejilla())
            ->orderByDesc('anio')
            ->orderByDesc('semana')
            ->orderByDesc('fecha_prueba')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('admin/calidad/pnd/index', [
            'obras' => $obras,
            'obraId' => $obra?->id,
            'nota' => $obra?->pnd_nota,
            'plan' => $obra ? $this->avance($obra) : [],
            'reportes' => $reportes,
            'filtros' => $request->only(['metodo', 'anio', 'search']),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('admin/calidad/pnd/form', [
            'reporte' => null,
            'obraId' => $request->integer('obra') ?: null,
        ] + $this->catalogos());
    }

    public function store(PndReporteRequest $request): RedirectResponse
    {
        $reporte = $this->registrador->guardar(
            new PndReporte(['capturista_id' => $request->user()?->getKey()]),
            $request->safe()->except(['juntas', 'parametros', 'pdf', 'fotos']),
            $request->input('juntas', []),
            $request->input('parametros', []),
            $request->file('pdf'),
            $request->file('fotos', []),
        );

        return redirect()
            ->route('admin.qal.pnd.edit', $reporte)
            ->with('success', "Informe {$reporte->reporte_no} capturado.");
    }

    public function edit(PndReporte $pnd): Response
    {
        $pnd->load([
            'juntas' => fn ($consulta) => $consulta->orderBy('id'),
            'juntas.pieza:id,marca',
            'parametros' => fn ($consulta) => $consulta->orderBy('id'),
            'fotos',
        ]);

        return Inertia::render('admin/calidad/pnd/form', [
            'reporte' => $pnd,
            'obraId' => $pnd->qal_obra_id,
        ] + $this->catalogos());
    }

    public function update(PndReporteRequest $request, PndReporte $pnd): RedirectResponse
    {
        $this->registrador->guardar(
            $pnd,
            $request->safe()->except(['juntas', 'parametros', 'pdf', 'fotos']),
            $request->input('juntas', []),
            $request->input('parametros', []),
            $request->file('pdf'),
            $request->file('fotos', []),
        );

        return back()->with('success', 'Informe actualizado.');
    }

    public function destroy(PndReporte $pnd): RedirectResponse
    {
        $obraId = $pnd->qal_obra_id;

        // Las juntas, los parámetros y las fotos se van en cascada; los
        // archivos no, y borrar la fila dejaría el disco creciendo con PDFs que
        // ya nadie referencia.
        $rutas = $pnd->fotos()->pluck('ruta')->all();

        if ($pnd->archivo_pdf) {
            $rutas[] = $pnd->archivo_pdf;
        }

        $pnd->delete();
        Storage::disk('public')->delete($rutas);

        return redirect()
            ->route('admin.qal.pnd.index', ['obra' => $obraId])
            ->with('success', 'Informe eliminado.');
    }

    /**
     * Vuelve a intentar el enlace marca → pieza.
     *
     * Se pide a mano porque el momento en que las piezas aparecen no lo decide
     * este módulo: el informe se captura antes y las marcas se dan de alta
     * después.
     */
    public function resolverMarcas(PndReporte $pnd): RedirectResponse
    {
        $enlazadas = $this->registrador->resolverMarcas($pnd);

        return back()->with('success', $enlazadas === 0
            ? 'Ninguna marca suelta del informe existe todavía como pieza de la obra.'
            : "Se enlazaron {$enlazadas} juntas con su pieza.");
    }

    /**
     * Guarda lo pactado con el cliente: cuántas pruebas por método y de dónde
     * sale ese número.
     *
     * Es el denominador del avance, así que pide `qal.obras.editar` y no un
     * permiso de PND: quien teclea informes no negocia el contrato.
     *
     * **Quitar la palomita de un método no es ponerlo en cero.** Sin fila, el
     * método no entra en este contrato; con fila en cero, se pactaron cero. Por
     * eso se borra la fila en lugar de guardar un cero.
     */
    public function guardarPlan(PndPlanRequest $request, Obra $obra): RedirectResponse
    {
        $obra->update(['pnd_nota' => $request->input('nota')]);

        $pactados = collect($request->input('plan', []))
            ->filter(fn (array $fila): bool => (bool) ($fila['pactado'] ?? false));

        $obra->pndPlan()
            ->whereNotIn('metodo', $pactados->pluck('metodo')->all())
            ->delete();

        foreach ($pactados as $fila) {
            $obra->pndPlan()->updateOrCreate(
                ['metodo' => $fila['metodo']],
                ['comprometidas' => (int) ($fila['comprometidas'] ?? 0)],
            );
        }

        return back()->with('success', 'Plan de PND actualizado.');
    }

    /**
     * Comprometidas contra ensayadas, método por método.
     *
     * `comprometidas` en nulo significa **«este método no entra en el
     * contrato»**, que no es lo mismo que un cero: un cero es «se pactaron
     * cero». La pantalla los pinta distinto y por eso el nulo llega hasta ella
     * en vez de convertirse en 0 aquí.
     *
     * El denominador es el spot, no la junta: cada renglón de la rejilla es un
     * punto examinado.
     *
     * @return list<array<string, mixed>>
     */
    private function avance(Obra $obra): array
    {
        $comprometidas = $obra->pndPlan()->pluck('comprometidas', 'metodo');

        $porMetodo = PndReporte::query()
            ->where('qal_obra_id', $obra->id)
            ->withCount($this->conteosDeLaRejilla())
            ->get(['id', 'metodo'])
            ->groupBy(fn (PndReporte $reporte): string => $reporte->metodo->value);

        return collect(MetodoPnd::cases())
            ->map(function (MetodoPnd $metodo) use ($comprometidas, $porMetodo): array {
                $informes = $porMetodo->get($metodo->value, collect());

                return [
                    'metodo' => $metodo->value,
                    'nombre' => $metodo->nombre(),
                    'detecta' => $metodo->detecta(),
                    'parametros' => $metodo->parametrosSugeridos(),
                    'comprometidas' => $comprometidas->get($metodo->value),
                    'spots' => (int) $informes->sum('spots'),
                    'rechazados' => (int) $informes->sum('rechazados'),
                    'reportes' => $informes->count(),
                ];
            })
            ->all();
    }

    /**
     * Los dos números que se cuentan de la rejilla en todas las consultas.
     *
     * @return array<int|string, mixed>
     */
    private function conteosDeLaRejilla(): array
    {
        return [
            'juntas as spots',
            'juntas as rechazados' => fn ($consulta) => $consulta->where('resultado', ResultadoPnd::Rechazada),
        ];
    }

    /**
     * @param  list<int>  $disponibles
     */
    private function obraDelFiltro(Request $request, array $disponibles): ?Obra
    {
        $id = $request->integer('obra');

        if ($id > 0 && in_array($id, $disponibles, true)) {
            return Obra::find($id);
        }

        // Sin filtro se entra a la primera obra activa: la pantalla vacía no
        // dice nada y obligar a elegir cada vez es un clic por visita.
        return Obra::query()->conDatosDeLaObra()->activas()->orderBy('obras.no')->first();
    }

    /**
     * @return array<string, mixed>
     */
    private function catalogos(): array
    {
        return [
            'obras' => Obra::query()->conDatosDeLaObra()->activas()->orderBy('obras.no')->get(),
            'laboratorios' => Laboratorio::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'siglas']),
            'soldadores' => Soldador::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'clave']),
            'metodos' => $this->metodos(),
        ];
    }

    /**
     * El enum, con lo que la pantalla necesita saber de cada método: qué
     * detecta y qué parámetros suele traer su informe.
     *
     * @return list<array<string, mixed>>
     */
    private function metodos(): array
    {
        return collect(MetodoPnd::cases())
            ->map(fn (MetodoPnd $metodo): array => [
                'valor' => $metodo->value,
                'nombre' => $metodo->nombre(),
                'detecta' => $metodo->detecta(),
                'parametros' => $metodo->parametrosSugeridos(),
            ])
            ->all();
    }
}
