<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Qal\ProgramacionRequest;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Qal\Obra;
use App\Models\Qal\Programacion;
use App\Services\Qal\AvanceProduccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Avance de producción: lo que producción programó contra lo que calidad vio.
 *
 * Lo único que se escribe es el plan de la semana —qué piezas se piensan
 * hacer, con qué grupo y en qué módulo—; lo demás sale de las inspecciones y
 * lo calcula `AvanceProduccion`. Las piezas del plan las lleva
 * `ProgramacionPiezaController`; aquí quedan las notas y el cierre, que es lo
 * que hace que el plan cuente y que Calidad lo vea. La portada compara las obras sin sumarlas y, dentro de
 * una obra, está el plan de la semana por transformación. Semana, obra y
 * transformación viajan en la URL.
 */
class ProgramacionController extends Controller
{
    public function __construct(private readonly AvanceProduccion $avance) {}

    public function index(Request $request): Response
    {
        $pedida = (string) $request->query('semana', '');
        $semana = preg_match('/^\d{4}-S\d{2}$/', $pedida) === 1 ? $pedida : $this->avance->semanaActual();
        $semanas = $this->avance->semanasDisponibles();
        $obras = Obra::opcionesDeSelector();
        $obraId = $obras->contains('id', $request->integer('obra')) ? $request->integer('obra') : null;
        $fase = $request->query('fase') === '3' ? '3' : '2';
        $puedeCapturar = $request->user()?->can('qal.programacion.capturar') ?? false;

        return Inertia::render('admin/calidad/avance/index', [
            'semana' => $semana,
            'semanas' => in_array($semana, $semanas, true) ? $semanas : [$semana, ...$semanas],
            'obras' => $obras,
            'obraId' => $obraId,
            'fase' => $fase,
            'vista' => $obraId !== null ? $this->avance->deObra($obraId, $fase, $semana, $puedeCapturar) : null,
            'comparativa' => $obraId === null ? $this->avance->comparativa($obras->all(), $semana) : null,
            'grupos' => $obraId !== null ? $this->grupos() : [],
            'puedeCapturar' => $puedeCapturar,
            'puedeCerrar' => $request->user()?->can('qal.programacion.cerrar') ?? false,
        ]);
    }

    /**
     * Los grupos de trabajo de Producción, para decir quién hace cada pieza.
     *
     * @return list<array{id: int, descripcion: string}>
     */
    private function grupos(): array
    {
        return GrupoTrabajo::query()
            ->where('activo', true)
            ->orderBy('descripcion')
            ->get(['id', 'descripcion'])
            ->map(fn (GrupoTrabajo $grupo): array => ['id' => $grupo->id, 'descripcion' => $grupo->descripcion])
            ->all();
    }

    /**
     * Las notas de la semana. Si no queda nota ni pieza, el plan se borra:
     * «sin plan» es un estado que la pantalla sabe enseñar.
     */
    public function store(ProgramacionRequest $request): RedirectResponse
    {
        [$anio, $numero] = AvanceProduccion::partesDeSemana($request->string('semana')->value());
        $notas = trim((string) $request->input('notas', '')) ?: null;

        $programacion = Programacion::query()->updateOrCreate([
            'obra_id' => $request->integer('obra_id'),
            'fase' => AvanceProduccion::fase($request->string('fase')->value())->value,
            'anio' => $anio,
            'semana' => $numero,
        ], ['notas' => $notas]);

        if ($programacion->wasRecentlyCreated) {
            $programacion->update(['capturista_id' => $request->user()?->getKey()]);
        }

        if ($notas === null && ! $programacion->cerrada() && ! $programacion->piezas()->exists()) {
            $programacion->delete();
        }

        return back()->with('success', "Semana {$numero}: notas guardadas.");
    }

    /**
     * Cerrar el plan lo compromete: empieza a contar, Calidad lo ve y ya no se
     * toca. Un plan sin piezas no se cierra, y uno cerrado no se reabre.
     */
    public function cerrar(ProgramacionRequest $request): RedirectResponse
    {
        $semana = $request->string('semana')->value();
        $plan = $this->avance->planDeLaSemana($request->integer('obra_id'), $request->string('fase')->value(), $semana);

        if ($plan === null || $plan->piezas->isEmpty()) {
            return back()->withErrors(['plan' => 'No hay piezas en el plan de esa semana: no hay nada que cerrar.']);
        }

        if ($plan->cerrada()) {
            return back()->withErrors(['plan' => 'El plan de esa semana ya está cerrado.']);
        }

        $plan->update(['cerrada_at' => now(), 'cerrada_por_id' => $request->user()?->getKey()]);

        return back()->with('success', "Semana {$plan->semana}: plan cerrado con {$plan->piezas->count()} pieza(s). Ya cuenta y Calidad lo ve.");
    }
}
