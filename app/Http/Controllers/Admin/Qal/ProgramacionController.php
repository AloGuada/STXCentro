<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Qal\ProgramacionRequest;
use App\Models\Qal\Obra;
use App\Models\Qal\Programacion;
use App\Services\Qal\AvanceProduccion;
use App\Services\Qal\LectorDeProgramacion;
use App\Services\Qal\ResolutorDeMarcas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Avance de producción: lo que producción programó contra lo que calidad vio.
 *
 * Lo único que se escribe es el plan de la semana —qué se piensa hacer, qué se
 * da de baja y por qué—; lo demás sale de las inspecciones y lo calcula
 * `AvanceProduccion`. La portada compara las obras sin sumarlas y, dentro de
 * una obra, está el plan de la semana por transformación. Semana, obra y
 * transformación viajan en la URL.
 */
class ProgramacionController extends Controller
{
    public function __construct(
        private readonly AvanceProduccion $avance,
        private readonly LectorDeProgramacion $lector,
        private readonly ResolutorDeMarcas $marcas,
    ) {}

    public function index(Request $request): Response
    {
        $pedida = (string) $request->query('semana', '');
        $semana = preg_match('/^\d{4}-S\d{2}$/', $pedida) === 1 ? $pedida : $this->avance->semanaActual();
        $semanas = $this->avance->semanasDisponibles();
        $obras = Obra::opcionesDeSelector();
        $obraId = $obras->contains('id', $request->integer('obra')) ? $request->integer('obra') : null;
        $fase = $request->query('fase') === '3' ? '3' : '2';

        return Inertia::render('admin/calidad/avance/index', [
            'semana' => $semana,
            'semanas' => in_array($semana, $semanas, true) ? $semanas : [$semana, ...$semanas],
            'obras' => $obras,
            'obraId' => $obraId,
            'fase' => $fase,
            'vista' => $obraId !== null ? $this->avance->deObra($obraId, $fase, $semana) : null,
            'comparativa' => $obraId === null ? $this->avance->comparativa($obras->all(), $semana) : null,
            'puedeCapturar' => $request->user()?->can('qal.programacion.capturar') ?? false,
        ]);
    }

    /**
     * El plan se reemplaza entero, porque se vuelve a pegar entero. Si queda
     * vacío se borra: «sin plan» es un estado que la pantalla sabe enseñar.
     */
    public function store(ProgramacionRequest $request): RedirectResponse
    {
        [$anio, $numero] = AvanceProduccion::partesDeSemana($request->string('semana')->value());
        $obraId = $request->integer('obra_id');
        $clave = [
            'obra_id' => $obraId,
            'fase' => AvanceProduccion::fase($request->string('fase')->value())->value,
            'anio' => $anio,
            'semana' => $numero,
        ];
        $marcas = $this->lector->marcas((string) $request->input('marcas', ''));
        $bajas = $this->lector->bajas((string) $request->input('bajas', ''));
        $notas = trim((string) $request->input('notas', '')) ?: null;

        if ($marcas === [] && $bajas === [] && $notas === null) {
            Programacion::query()->where($clave)->delete();

            return back()->with('success', "Semana {$numero}: no quedó nada escrito, así que no hay plan.");
        }

        $conceptos = $this->marcas->deLaObra($obraId);

        DB::transaction(function () use ($clave, $marcas, $bajas, $notas, $conceptos, $request): void {
            $programacion = Programacion::query()->updateOrCreate($clave, [
                'notas' => $notas,
                'capturista_id' => $request->user()?->getKey(),
            ]);

            $programacion->marcas()->delete();
            $programacion->marcas()->createMany([
                ...array_map(fn (array $marca): array => [
                    'marca' => $marca['marca'],
                    'cantidad' => $marca['cantidad'],
                    'es_baja' => false,
                    'concepto_id' => $this->marcas->conceptoDe($marca['marca'], $conceptos),
                ], $marcas),
                ...array_map(fn (array $baja): array => [
                    'marca' => $baja['marca'],
                    'cantidad' => 1,
                    'es_baja' => true,
                    'motivo_baja' => $baja['motivo'],
                    'concepto_id' => $this->marcas->conceptoDe($baja['marca'], $conceptos),
                ], $bajas),
            ]);
        });

        $piezas = array_sum(array_column($marcas, 'cantidad'));
        $resumen = "{$piezas} pieza(s) en ".count($marcas).' marca(s)'.($bajas !== [] ? ' y '.count($bajas).' baja(s)' : '');

        return back()->with('success', "Semana {$numero} guardada: {$resumen}.");
    }
}
