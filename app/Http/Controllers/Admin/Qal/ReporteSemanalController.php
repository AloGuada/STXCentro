<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Enums\Qal\MetodoPnd;
use App\Enums\Qal\ResultadoPnd;
use App\Http\Controllers\Controller;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\Obra;
use App\Models\Qal\ObraIncidencia;
use App\Models\Qal\ObraMontaje;
use App\Models\Qal\PndJunta;
use App\Models\Qal\PndReporte;
use App\Services\Qal\EstadisticaIncidencias;
use App\Services\Qal\EstadisticaInspecciones;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El reporte semanal de calidad: el F-STX-CA-31 que se manda a dirección.
 *
 * No es el tablero con otro peinado. El tablero es la herramienta del área —se
 * filtra, se explora, se mira todos los días—; esto es un **documento** con
 * folio de formato, seis hojas fijas y una semana de corte, que sale igual
 * todas las semanas para que se pueda comparar con las anteriores.
 *
 * Las seis hojas se calculan de la base:
 *
 *  - **Hojas 0, 1 y 3 · inspección visual** salen de `qal_inspecciones`, vía
 *    `EstadisticaInspecciones`.
 *  - **Hoja 2 · PND** sale entera de `qal_pnd_reportes` y del plan de la obra.
 *  - **Hojas 4 y 5 · montaje e incidencias** salen de `qal_obra_montaje` y
 *    `qal_obra_incidencias`, que llena el módulo de incidencias en obra.
 *
 * Cada hoja dice de dónde salen sus números: un reporte que se manda fuera no
 * puede dejar la duda.
 */
class ReporteSemanalController extends Controller
{
    public function __construct(
        private readonly EstadisticaIncidencias $incidencias,
        private readonly EstadisticaInspecciones $inspecciones,
    ) {}

    public function index(Request $request): Response
    {
        $anios = $this->aniosConDatos();
        $anio = $this->elegido($request->integer('anio'), $anios);

        $semanas = $this->semanasConDatos($anio);
        $semana = $this->elegido($request->integer('semana'), $semanas);

        return Inertia::render('admin/calidad/reporte-semanal/index', [
            'anio' => $anio,
            'semana' => $semana,
            'anios' => $anios,
            'semanas' => $semanas,
            // Hojas 0, 1 y 3: lo liberado en la semana, con su rechazo previo,
            // y la misma cuenta a lo largo del año.
            ...$this->inspecciones->hojasDelReporteSemanal($anio, $semana),
            'pnd' => $this->hojaPnd(),
            // Las hojas 4 y 5 son el mismo cálculo con dos cortes distintos, y
            // la regla que los parte vive en el servicio para que el documento
            // que sale de la empresa no pueda desviarse de la pantalla.
            ...$this->incidencias->hojasDelReporteSemanal($anio, $semana),
        ]);
    }

    /**
     * La hoja de PND, obra por obra.
     *
     * Va en **acumulado del proyecto y no de la semana**, igual que el formato
     * en Excel, y no por descuido: lo que se pactó con el cliente es el total
     * del contrato, así que el avance sólo significa algo contra todo lo
     * ensayado hasta la fecha. Un «12 % de avance esta semana» no responde a
     * ninguna pregunta.
     *
     * La unidad es siempre el **spot**: un punto examinado. Una junta puede
     * llevar varios, así que contar juntas subestimaría lo ensayado y movería el
     * porcentaje de rechazo, que es el número que mira el cliente.
     *
     * @return list<array<string, mixed>>
     */
    private function hojaPnd(): array
    {
        $porObra = PndReporte::query()
            ->withCount([
                'juntas as spots',
                'juntas as rechazados' => fn ($consulta) => $consulta->where('resultado', ResultadoPnd::Rechazada),
            ])
            ->get(['id', 'qal_obra_id', 'metodo'])
            ->groupBy('qal_obra_id');

        if ($porObra->isEmpty()) {
            return [];
        }

        $piezas = $this->piezasPorObra();

        return Obra::query()
            ->conDatosDeLaObra()
            ->whereIn('qal_obras.id', $porObra->keys())
            ->with('pndPlan:id,qal_obra_id,metodo,comprometidas')
            ->orderBy('obras.no')
            ->get()
            ->map(function (Obra $obra) use ($porObra, $piezas): array {
                $informes = $porObra->get($obra->id, collect());
                $spots = (int) $informes->sum('spots');
                $rechazados = (int) $informes->sum('rechazados');

                // Sin una sola fila de plan, la obra no tiene compromiso
                // firmado: la hoja escribe «falta el dato» en vez de calcular un
                // avance sobre un denominador inventado.
                $plan = $obra->pndPlan;
                $comprometidos = $plan->isEmpty() ? null : (int) $plan->sum('comprometidas');

                $marcas = $piezas->get($obra->id, collect());

                return [
                    'obra_id' => $obra->id,
                    'obra' => $obra->no,
                    'descripcion' => $obra->descripcion,
                    'spots' => $spots,
                    'rechazados' => $rechazados,
                    // El numerador del avance son los spots ACEPTADOS: un punto
                    // rechazado se ensayó, pero no cumple, así que no puede
                    // contar como parte de lo entregado.
                    'aceptados' => $spots - $rechazados,
                    'metodos' => $this->porMetodo($informes),
                    'pz_total' => $obra->pz_total,
                    'comprometidos' => $comprometidos,
                    // «Piezas con PND OK» significa dos cosas distintas en el
                    // formato en Excel —a veces ensayos aceptados, a veces
                    // piezas distintas—. Aquí se separan: esto son marcas.
                    'piezas_con_pnd' => $marcas->count(),
                    'piezas_sin_rechazo' => $marcas->filter(fn (int $rechazos): bool => $rechazos === 0)->count(),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * Spots aceptados y rechazados de cada método.
     *
     * Los métodos sin un solo ensayo salen en cero y la hoja los pinta con una
     * raya: un método que no se usó no tiene tasa de rechazo, y escribir «0 %»
     * ahí se lee como «salió todo bien».
     *
     * @param  Collection<int, PndReporte>  $informes
     * @return array<string, array{spots: int, aceptados: int, rechazados: int}>
     */
    private function porMetodo(Collection $informes): array
    {
        $agrupados = $informes->groupBy(fn (PndReporte $reporte): string => $reporte->metodo->value);

        return collect(MetodoPnd::cases())
            ->mapWithKeys(function (MetodoPnd $metodo) use ($agrupados): array {
                $suyos = $agrupados->get($metodo->value, collect());
                $spots = (int) $suyos->sum('spots');
                $rechazados = (int) $suyos->sum('rechazados');

                return [$metodo->value => [
                    'spots' => $spots,
                    'aceptados' => $spots - $rechazados,
                    'rechazados' => $rechazados,
                ]];
            })
            ->all();
    }

    /**
     * Cuántos spots rechazados acumula cada marca, por obra.
     *
     * Se agrupa en la base y no en PHP a propósito: la rejilla de PND crece con
     * cada informe, y traerse todos los renglones para contarlos aquí sería
     * cargar la obra entera en memoria para devolver dos números.
     *
     * @return Collection<int, Collection<string, int>>
     */
    private function piezasPorObra(): Collection
    {
        return PndJunta::query()
            ->join('qal_pnd_reportes', 'qal_pnd_reportes.id', '=', 'qal_pnd_juntas.qal_pnd_reporte_id')
            ->groupBy('qal_pnd_reportes.qal_obra_id', 'qal_pnd_juntas.marca')
            ->selectRaw(
                'qal_pnd_reportes.qal_obra_id as obra_id, qal_pnd_juntas.marca as marca, '.
                'sum(case when qal_pnd_juntas.resultado = ? then 1 else 0 end) as rechazos',
                [ResultadoPnd::Rechazada->value],
            )
            ->get()
            ->groupBy('obra_id')
            ->map(fn (Collection $marcas): Collection => $marcas->mapWithKeys(
                fn ($fila): array => [(string) $fila->marca => (int) $fila->rechazos],
            ));
    }

    /**
     * Los años con algo que reportar.
     *
     * Salen de la inspección visual, de PND, del montaje y de las incidencias:
     * el año del reporte no lo decide un módulo, lo decide que haya trabajo
     * registrado.
     *
     * @return list<int>
     */
    private function aniosConDatos(): array
    {
        return $this->anios(Inspeccion::query())
            ->merge($this->anios(PndReporte::query()))
            ->merge($this->anios(ObraMontaje::query()))
            ->merge($this->anios(ObraIncidencia::query()))
            ->push(Carbon::now()->isoFormat('GGGG'))
            ->map(fn ($anio): int => (int) $anio)
            ->unique()
            ->sortDesc()
            ->values()
            ->all();
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Builder<*>  $consulta
     * @return Collection<int, mixed>
     */
    private function anios($consulta): Collection
    {
        return $consulta->select('anio')->distinct()->pluck('anio');
    }

    /**
     * Las semanas de ese año, de la más reciente a la más vieja.
     *
     * El año en curso siempre ofrece la semana actual aunque no tenga nada
     * capturado: el reporte se abre para emitir el de esta semana, y no poder
     * seleccionarla porque todavía no hay nada registrado sería absurdo.
     *
     * @return list<int>
     */
    private function semanasConDatos(int $anio): array
    {
        $de = fn (string $modelo) => $modelo::query()
            ->where('anio', $anio)
            ->select('semana')
            ->distinct()
            ->pluck('semana');

        $semanas = $de(Inspeccion::class)
            ->merge($de(PndReporte::class))
            ->merge($de(ObraMontaje::class))
            ->merge($de(ObraIncidencia::class))
            ->map(fn ($semana): int => (int) $semana);

        if ($anio === (int) Carbon::now()->isoFormat('GGGG')) {
            $semanas->push((int) Carbon::now()->isoFormat('W'));
        }

        return $semanas->unique()->sortDesc()->values()->all();
    }

    /**
     * @param  list<int>  $disponibles
     */
    private function elegido(int $pedido, array $disponibles): int
    {
        return in_array($pedido, $disponibles, true) ? $pedido : (int) ($disponibles[0] ?? 0);
    }
}
