<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Enums\Costos\PresupuestoEstatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\PlantaStoreRequest;
use App\Models\Cob\Partida;
use App\Models\Costos\ObraRubro;
use App\Models\Costos\Presupuesto;
use App\Models\Costos\Rubro;
use App\Models\Costos\TipoRubro;
use App\Models\Obra;
use App\Models\Proyecto;
use App\Support\OrdenaColumnas;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PresupuestoController extends Controller
{
    use OrdenaColumnas;

    /** @var array<class-string, string> */
    private const TIPOS = [
        Proyecto::class => 'proyecto',
        Obra::class => 'obra',
        Partida::class => 'partida',
    ];

    public function index(Request $request): Response
    {
        $umbral = (int) config('costos.umbral_alerta_porcentaje', 90);

        $planta = Presupuesto::query()
            ->whereHasMorph('presupuestable', [Obra::class], fn ($q) => $q->where('es_planta', true))
            ->with('presupuestable')
            ->withSum('rubros', 'presupuestado')
            ->withSum('rubros', 'acumulado')
            ->withCount('rubros')
            ->first();

        $query = Presupuesto::query()
            ->with('presupuestable')
            ->withSum('rubros', 'presupuestado')
            ->withSum('rubros', 'acumulado')
            ->withCount('rubros')
            ->when($planta, fn ($q) => $q->whereKeyNot($planta->id))
            ->when($request->search, function ($q, $s) {
                $q->where(function ($q) use ($s) {
                    $q->where('nombre_interno', 'like', "%{$s}%")
                        ->orWhereHasMorph('presupuestable', [Obra::class, Proyecto::class], function ($q2) use ($s) {
                            $q2->where('no', 'like', "%{$s}%")->orWhere('descripcion', 'like', "%{$s}%");
                        })
                        ->orWhereHasMorph('presupuestable', [Partida::class], function ($q2) use ($s) {
                            $q2->where('descripcion', 'like', "%{$s}%");
                        });
                });
            });

        $orden = $this->aplicarOrden($query, $request, [
            'nombre_interno' => 'nombre_interno',
            'estatus' => 'estatus',
            'rubros_count' => 'rubros_count',
            'rubros_sum_presupuestado' => 'rubros_sum_presupuestado',
            'rubros_sum_acumulado' => 'rubros_sum_acumulado',
        ], 'created_at', 'desc');

        $presupuestos = $query->paginate(15)->withQueryString()
            ->through(fn (Presupuesto $p) => $this->presentar($p));

        $obraRubros = ObraRubro::query()
            ->select('id', 'presupuesto_id', 'presupuestado', 'acumulado')
            ->get();

        $statsObras = $this->calcularStats(
            $planta ? $obraRubros->where('presupuesto_id', '!=', $planta->id) : $obraRubros,
            $umbral,
        );
        $statsPlanta = $planta
            ? $this->calcularStats($obraRubros->where('presupuesto_id', $planta->id), $umbral)
            : null;

        return Inertia::render('admin/costos/presupuestos/index', [
            'presupuestos' => $presupuestos,
            'planta' => $planta ? $this->presentar($planta) : null,
            'disponibles' => $this->presupuestablesDisponibles(),
            'statsPlanta' => $statsPlanta,
            'filters' => $request->only('search'),
            'stats' => [
                ...$statsObras,
                'umbral_alerta' => $umbral,
                'bloquear_sobregiro' => (bool) config('costos.bloquear_sobregiro', false),
            ],
            'sortBy' => $orden['by'],
            'sortDir' => $orden['dir'],
        ]);
    }

    /**
     * Crea el presupuesto de un presupuestable y siembra todos sus centros de
     * costo (el botón "Agregar presupuesto").
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'presupuestable_type' => ['required', Rule::in(array_values(self::TIPOS))],
            'presupuestable_id' => ['required', 'integer'],
            'nombre_interno' => ['nullable', 'string', 'max:255'],
        ]);

        $class = array_search($validated['presupuestable_type'], self::TIPOS, true);
        $class::findOrFail($validated['presupuestable_id']);

        $presupuesto = Presupuesto::firstOrCreate(
            ['presupuestable_type' => $class, 'presupuestable_id' => $validated['presupuestable_id']],
            ['nombre_interno' => $validated['nombre_interno'] ?? null],
        );

        $presupuesto->sembrarRubrosFaltantes();

        return to_route('admin.costos.presupuestos.edit', $presupuesto)
            ->with('success', 'Presupuesto creado.');
    }

    public function storePlanta(PlantaStoreRequest $request): RedirectResponse
    {
        $planta = Obra::create([
            'no' => 'PLANTA',
            'descripcion' => $request->validated('descripcion'),
            'estatus' => 'abierta',
            'activa' => true,
            'es_planta' => true,
        ]);

        $presupuesto = $planta->presupuesto()->create();
        $presupuesto->sembrarRubrosFaltantes();

        return to_route('admin.costos.presupuestos.edit', $presupuesto)
            ->with('success', 'Proyecto de planta creado.');
    }

    public function edit(Presupuesto $presupuesto): Response
    {
        $presupuesto->load(['presupuestable', 'rubros.rubro.tipoRubro']);

        $rubros = Rubro::query()
            ->where('ambito', $presupuesto->ambitoRubros())
            ->with('tipoRubro')
            ->orderBy('codigo')
            ->get();

        return Inertia::render('admin/costos/presupuestos/edit', [
            'presupuesto' => $this->presentarDetalle($presupuesto),
            'rubros' => $rubros,
        ]);
    }

    public function update(Request $request, Presupuesto $presupuesto): RedirectResponse
    {
        $validated = $request->validate([
            'nombre_interno' => ['nullable', 'string', 'max:255'],
        ]);

        $presupuesto->update($validated);

        return back()->with('success', 'Presupuesto actualizado.');
    }

    /**
     * Cierra o reabre el presupuesto (sustituye el cierre de la obra en cobranza).
     */
    public function cambiarEstado(Presupuesto $presupuesto): RedirectResponse
    {
        $nuevo = $presupuesto->estaCerrado() ? PresupuestoEstatus::Activo : PresupuestoEstatus::Cerrado;

        $presupuesto->transitionTo($nuevo);

        return back()->with('success', $nuevo === PresupuestoEstatus::Cerrado
            ? 'Presupuesto cerrado.'
            : 'Presupuesto reabierto.');
    }

    /**
     * Presupuestables (proyectos, obras, partidas) que aún no tienen presupuesto.
     *
     * @return array<string, array<int, array{id: int, label: string}>>
     */
    private function presupuestablesDisponibles(): array
    {
        $obras = Obra::query()
            ->sinPlanta()
            ->whereDoesntHave('presupuesto')
            ->orderBy('no')
            ->get(['id', 'no', 'descripcion'])
            ->map(fn (Obra $o) => ['id' => $o->id, 'label' => trim(($o->descripcion ?? '')." · OP-{$o->no}", ' ·')]);

        $proyectos = Proyecto::query()
            ->whereDoesntHave('presupuesto')
            ->orderBy('no')
            ->get(['id', 'no', 'descripcion'])
            ->map(fn (Proyecto $p) => ['id' => $p->id, 'label' => trim(($p->descripcion ?? '')." · {$p->no}", ' ·')]);

        $partidas = Partida::query()
            ->whereDoesntHave('presupuesto')
            ->with('obra:id,no')
            ->orderBy('id')
            ->get(['id', 'obra_id', 'descripcion'])
            ->map(fn (Partida $p) => ['id' => $p->id, 'label' => trim(($p->descripcion ?? "Partida #{$p->id}").($p->obra ? " · OP-{$p->obra->no}" : ''))]);

        return [
            'obra' => $obras->values()->all(),
            'proyecto' => $proyectos->values()->all(),
            'partida' => $partidas->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentar(Presupuesto $p): array
    {
        $presupuestable = $p->presupuestable;
        $esPlanta = $presupuestable instanceof Obra && $presupuestable->es_planta;

        return [
            'id' => $p->id,
            'tipo' => self::TIPOS[$p->presupuestable_type] ?? 'obra',
            'nombre' => $p->nombreMostrar(),
            'nombre_interno' => $p->nombre_interno,
            'no' => $presupuestable->no ?? null,
            'descripcion' => $presupuestable->descripcion ?? null,
            'estatus' => $p->estatus->value,
            'es_planta' => $esPlanta,
            'rubros_count' => (int) ($p->rubros_count ?? 0),
            'sum_presupuestado' => (float) ($p->rubros_sum_presupuestado ?? 0),
            'sum_acumulado' => (float) ($p->rubros_sum_acumulado ?? 0),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function presentarDetalle(Presupuesto $p): array
    {
        return [
            ...$this->presentar($p),
            'obra_rubros' => $p->rubros->map(fn (ObraRubro $r) => [
                'id' => $r->id,
                'rubro_id' => $r->rubro_id,
                'presupuestado' => $r->presupuestado,
                'acumulado' => $r->acumulado,
                'rubro' => $r->rubro,
            ])->values(),
        ];
    }

    /**
     * @param  Collection<int, ObraRubro>  $obraRubros
     * @return array{total_rubros: int, sobregiros: int, criticos: int, total_presupuestado: float, total_acumulado: float}
     */
    private function calcularStats(Collection $obraRubros, int $umbral): array
    {
        $sobregiros = 0;
        $criticos = 0;
        $totalPresupuestado = 0.0;
        $totalAcumulado = 0.0;
        $umbralPct = (float) $umbral;

        foreach ($obraRubros as $r) {
            $presup = (float) $r->presupuestado;
            $acum = (float) $r->acumulado;
            $totalPresupuestado += $presup;
            $totalAcumulado += $acum;

            if ($presup <= 0.0) {
                if ($acum > 0.0) {
                    $sobregiros++;
                }

                continue;
            }

            $pct = ($acum / $presup) * 100.0;
            if ($pct > 100.0) {
                $sobregiros++;
            } elseif ($pct >= $umbralPct) {
                $criticos++;
            }
        }

        return [
            'total_rubros' => $obraRubros->count(),
            'sobregiros' => $sobregiros,
            'criticos' => $criticos,
            'total_presupuestado' => $totalPresupuestado,
            'total_acumulado' => $totalAcumulado,
        ];
    }

    public function generarReportePdf(): HttpResponse
    {
        $tipos = TipoRubro::with(['rubros' => fn ($q) => $q->where('ambito', 'obra')
            ->where('ocultar_en_reporte', false)
            ->orderBy('codigo')])
            ->orderBy('descripcion')
            ->get();

        $obras = Obra::sinPlanta()
            ->with('obraRubros')
            ->orderBy('no')
            ->get();

        $filas = $obras->map(function (Obra $obra) use ($tipos) {
            $obraRubrosPorRubro = $obra->obraRubros->keyBy('rubro_id');

            $ingresosPresup = (float) $obra->presupuesto_total;
            $ingresosReal = (float) ($obra->ingreso_real ?? 0);
            $ingresosDif = $ingresosPresup - $ingresosReal;

            $totalPresup = 0.0;
            $totalReal = 0.0;

            $tiposData = $tipos->map(function (TipoRubro $tipo) use ($obraRubrosPorRubro, &$totalPresup, &$totalReal) {
                $rubrosData = $tipo->rubros->map(function (Rubro $rubro) use ($obraRubrosPorRubro) {
                    $or = $obraRubrosPorRubro->get($rubro->id);
                    $presup = $or ? (float) $or->presupuestado : 0.0;
                    $real = $or ? (float) $or->acumulado : 0.0;

                    return [
                        'rubro' => $rubro,
                        'presup' => $presup,
                        'real' => $real,
                        'dif' => $presup - $real,
                    ];
                });

                $tipoPresup = $rubrosData->sum('presup');
                $tipoReal = $rubrosData->sum('real');
                $totalPresup += $tipoPresup;
                $totalReal += $tipoReal;

                return [
                    'tipo' => $tipo,
                    'rubros' => $rubrosData,
                    'total_presup' => $tipoPresup,
                    'total_real' => $tipoReal,
                    'total_dif' => $tipoPresup - $tipoReal,
                ];
            });

            // Calcular % por tipo sobre el total_real de la obra
            $tiposData = $tiposData->map(function ($td) use ($totalReal) {
                $td['porcentaje'] = $totalReal > 0 ? ($td['total_real'] / $totalReal) * 100 : 0;

                return $td;
            });

            $totalDif = $totalPresup - $totalReal;
            $utilidad = $ingresosReal - $totalReal;
            $utilVtsPct = $ingresosReal > 0 ? ($utilidad / $ingresosReal) * 100 : 0;
            $utilidadPct = $utilVtsPct - 5.5;

            return [
                'obra' => $obra,
                'ingresos_presup' => $ingresosPresup,
                'ingresos_real' => $ingresosReal,
                'ingresos_dif' => $ingresosDif,
                'tipos' => $tiposData,
                'total_presup' => $totalPresup,
                'total_real' => $totalReal,
                'total_dif' => $totalDif,
                'total_pct' => $totalReal > 0 ? 100 : 0,
                'utilidad' => $utilidad,
                'util_vts_pct' => $utilVtsPct,
                'utilidad_pct' => $utilidadPct,
            ];
        });

        $pdf = Pdf::loadView('pdf.costos.reporte-presupuestos', [
            'tipos' => $tipos,
            'filas' => $filas,
            'fechaGeneracion' => now(),
        ])->setPaper('tabloid', 'landscape')
            ->setOption('margin-top', 20)
            ->setOption('margin-bottom', 20)
            ->setOption('margin-left', 20)
            ->setOption('margin-right', 20);

        return $pdf->download('reporte-presupuestos-'.now()->format('Y-m-d').'.pdf');
    }
}
