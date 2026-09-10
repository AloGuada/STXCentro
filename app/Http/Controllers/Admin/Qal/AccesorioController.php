<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Qal\SubloteRequest;
use App\Models\Qal\LoteAccesorio;
use App\Models\Qal\Obra;
use App\Models\Qal\Sublote;
use App\Models\Qal\SubloteDefecto;
use App\Services\Qal\PantallaDeCaptura;
use App\Services\Qal\PrecargaDeFormulario;
use App\Services\Qal\RegistradorSublote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Los lotes de accesorios: cuánto de cada marca llegó, cuánto se liberó y qué
 * está detenido.
 *
 * Aquí se consulta y se decide; la captura de cada entrega es la de
 * Formularios en modo «lote de accesorios», que es donde está el inspector.
 * Nueva entrega, reinspección y edición abren esa misma pantalla ya llenada.
 */
class AccesorioController extends Controller
{
    public function __construct(
        private readonly RegistradorSublote $registrador,
        private readonly PantallaDeCaptura $pantalla,
        private readonly PrecargaDeFormulario $precarga,
    ) {}

    public function index(Request $request): Response
    {
        $obraId = $request->integer('obra') ?: null;

        return Inertia::render('admin/calidad/accesorios/index', [
            'obras' => fn () => Obra::opcionesDeSelector(soloActivas: false),
            'obraId' => $obraId,
            'lotes' => fn () => LoteAccesorio::query()
                ->with([
                    'obra:id,no',
                    'sublotes' => fn ($consulta) => $consulta->orderBy('fecha')->orderBy('id'),
                    'sublotes.inspector.usuario:id,name',
                    'sublotes.defectos.defecto',
                ])
                ->when($obraId, fn ($consulta) => $consulta->where('obra_id', $obraId))
                ->orderBy('marca')
                ->get()
                ->map(fn (LoteAccesorio $lote): array => $this->lote($lote)),
        ]);
    }

    public function store(SubloteRequest $request): RedirectResponse
    {
        $sublote = $this->registrador->registrar($request->validated(), $request->user());

        return redirect()
            ->route('admin.qal.formularios', ['obra' => $sublote->lote->obra_id])
            ->with('success', "Sublote de {$sublote->lote->marca} guardado · {$sublote->unidades} unidades.");
    }

    public function nuevoSublote(LoteAccesorio $lote): Response
    {
        return $this->pantalla->mostrar($lote->obra_id, $this->precarga->deLote($lote));
    }

    public function reinspeccionar(Sublote $sublote): Response
    {
        return $this->pantalla->mostrar($sublote->lote->obra_id, $this->precarga->deSublote($sublote, reinspeccion: true));
    }

    public function edit(Sublote $sublote): Response
    {
        return $this->pantalla->mostrar($sublote->lote->obra_id, $this->precarga->deSublote($sublote, reinspeccion: false));
    }

    public function update(SubloteRequest $request, Sublote $sublote): RedirectResponse
    {
        $this->registrador->registrar($request->validated(), $request->user(), $sublote);

        return redirect()
            ->route('admin.qal.accesorios.index', ['obra' => $sublote->lote->obra_id])
            ->with('success', "Inspección del sublote de {$sublote->lote->marca} actualizada.");
    }

    /**
     * La primera inspección de un sublote no se borra mientras tenga
     * reinspecciones: el grupo perdería la que dice que hubo un rechazo.
     */
    public function destroy(Sublote $sublote): RedirectResponse
    {
        if ($sublote->reinspecciones()->exists()) {
            return back()->withErrors([
                'sublote' => 'Este sublote tiene reinspecciones: bórralas primero, de la más reciente a la original.',
            ]);
        }

        $marca = $sublote->lote->marca;
        $sublote->delete();

        return back()->with('success', "Inspección del sublote de {$marca} eliminada.");
    }

    /**
     * @return array<string, mixed>
     */
    private function lote(LoteAccesorio $lote): array
    {
        return [
            'id' => $lote->id,
            'marca' => $lote->marca,
            'descripcion' => $lote->descripcion,
            'obra' => $lote->obra?->no,
            'total_unidades' => $lote->total_unidades,
            'kg_unitario' => $lote->kg_unitario,
            'elementos_unitarios' => $lote->elementos_unitarios,
            'avance' => $lote->avance(),
            // Cada sublote físico con todas sus inspecciones, de la primera a
            // la última; la última es la que manda.
            'grupos' => $lote->sublotes
                ->groupBy(fn (Sublote $sublote): int => $sublote->grupoId())
                ->map(fn ($grupo): array => $grupo
                    ->sortBy('numero_inspeccion')
                    ->map(fn (Sublote $sublote): array => $this->sublote($sublote))
                    ->values()
                    ->all())
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sublote(Sublote $sublote): array
    {
        return [
            'id' => $sublote->id,
            'numero_inspeccion' => $sublote->numero_inspeccion,
            'fecha' => $sublote->fecha->toDateString(),
            'unidades' => $sublote->unidades,
            'nivel' => $sublote->nivel->value,
            'muestra' => $sublote->muestra,
            'conformes' => $sublote->conformes,
            'rechazadas' => $sublote->rechazadas,
            'veredicto' => $sublote->veredicto?->value,
            'disposicion' => $sublote->disposicion,
            'liberado' => $sublote->liberado(),
            'sin_disposicion' => $sublote->sinDisposicion(),
            'inspector' => $sublote->inspector?->usuario?->name,
            'modulo' => $sublote->modulo,
            'linea' => $sublote->linea,
            // «Soldadura: Grieta (2), Traslape · Barrenos: Posición incorrecta»
            'defectos' => $sublote->defectos
                ->groupBy(fn (SubloteDefecto $defecto): string => $defecto->defecto->ambito->etiqueta())
                ->map(fn ($defectos, string $familia): string => $familia.': '.$defectos
                    ->countBy(fn (SubloteDefecto $defecto): string => $defecto->defecto->nombre)
                    ->map(fn (int $veces, string $nombre): string => $veces > 1 ? "{$nombre} ({$veces})" : $nombre)
                    ->implode(', '))
                ->implode(' · '),
        ];
    }
}
