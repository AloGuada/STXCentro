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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
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
        Gate::authorize('costos.obra-rubros.ver');

        $umbral = (int) config('costos.umbral_alerta_porcentaje', 90);

        $planta = Presupuesto::query()
            ->whereHasMorph('presupuestable', [Obra::class], fn ($q) => $q->where('es_planta', true))
            ->with('presupuestable')
            ->withSum('rubros', 'presupuestado')
            ->withSum('rubros', 'acumulado')
            ->withSum('rubros', 'apartado')
            ->withCount('rubros')
            ->first();

        $query = Presupuesto::query()
            ->with('presupuestable')
            ->withSum('rubros', 'presupuestado')
            ->withSum('rubros', 'acumulado')
            ->withSum('rubros', 'apartado')
            ->withCount('rubros')
            ->when($planta, fn ($q) => $q->whereKeyNot($planta->id));

        $this->aplicarBusqueda($query, $request->search);

        $orden = $this->aplicarOrden($query, $request, $this->columnasOrden(), 'created_at', 'desc');

        $presupuestos = $query->paginate(15)->withQueryString()
            ->through(fn (Presupuesto $p) => $this->presentar($p));

        $obraRubros = ObraRubro::query()
            ->select('id', 'presupuesto_id', 'presupuestado', 'acumulado', 'apartado')
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
        Gate::authorize('costos.obra-rubros.crear');

        $validated = $request->validate([
            'presupuestable_type' => ['required', Rule::in(array_values(self::TIPOS))],
            'presupuestable_id' => ['required', 'integer'],
            'nombre_interno' => ['nullable', 'string', 'max:255'],
            'op_interno' => ['nullable', 'string', 'max:255'],
        ]);

        $class = array_search($validated['presupuestable_type'], self::TIPOS, true);
        $class::findOrFail($validated['presupuestable_id']);

        $presupuesto = Presupuesto::firstOrCreate(
            ['presupuestable_type' => $class, 'presupuestable_id' => $validated['presupuestable_id']],
            [
                'nombre_interno' => $validated['nombre_interno'] ?? null,
                'op_interno' => $validated['op_interno'] ?? null,
            ],
        );

        $presupuesto->sembrarRubrosFaltantes();

        return to_route('admin.costos.presupuestos.edit', $presupuesto)
            ->with('success', 'Presupuesto creado.');
    }

    public function storePlanta(PlantaStoreRequest $request): RedirectResponse
    {
        Gate::authorize('costos.obra-rubros.crear');

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
        Gate::authorize('costos.obra-rubros.editar');

        $presupuesto->load(['presupuestable', 'rubros.rubro.tipoRubro']);
        if ($presupuesto->presupuestable instanceof Partida) {
            $presupuesto->presupuestable->loadMissing('obra:id,no');
        }

        $rubros = Rubro::query()
            ->where('ambito', $presupuesto->ambitoRubros())
            ->with('tipoRubro')
            ->orderBy('codigo')
            ->get();

        // Opciones del selector: los presupuestables libres + el actual (para
        // mostrarlo seleccionado y poder mantenerlo o cambiarlo).
        $disponibles = $this->presupuestablesDisponibles();
        $opciones = collect(['obra', 'proyecto', 'partida'])->flatMap(fn (string $tipo) => collect($disponibles[$tipo])
            ->map(fn (array $o) => ['value' => "{$tipo}:{$o['id']}", 'label' => ucfirst($tipo).' · '.$o['label']]));

        $tipoActual = self::TIPOS[$presupuesto->presupuestable_type];
        $opciones->prepend([
            'value' => "{$tipoActual}:{$presupuesto->presupuestable_id}",
            'label' => ucfirst($tipoActual).' · '.$this->etiquetaPresupuestable($presupuesto->presupuestable),
        ]);

        return Inertia::render('admin/costos/presupuestos/edit', [
            'presupuesto' => $this->presentarDetalle($presupuesto),
            'rubros' => $rubros,
            'presupuestables' => $opciones->values()->all(),
        ]);
    }

    public function update(Request $request, Presupuesto $presupuesto): RedirectResponse
    {
        Gate::authorize('costos.obra-rubros.editar');

        $validated = $request->validate([
            'nombre_interno' => ['nullable', 'string', 'max:255'],
            'op_interno' => ['nullable', 'string', 'max:255'],
            'presupuestable_type' => ['required', Rule::in(array_values(self::TIPOS))],
            'presupuestable_id' => ['required', 'integer'],
        ]);

        $class = array_search($validated['presupuestable_type'], self::TIPOS, true);
        $class::findOrFail($validated['presupuestable_id']);

        $cambia = $presupuesto->presupuestable_type !== $class
            || $presupuesto->presupuestable_id !== (int) $validated['presupuestable_id'];

        if ($cambia && Presupuesto::query()
            ->where('presupuestable_type', $class)
            ->where('presupuestable_id', $validated['presupuestable_id'])
            ->whereKeyNot($presupuesto->id)
            ->exists()) {
            return back()->withErrors(['presupuestable_id' => 'Ese proyecto/obra/partida ya tiene un presupuesto.']);
        }

        $presupuesto->update([
            'nombre_interno' => $validated['nombre_interno'] ?? null,
            'op_interno' => $validated['op_interno'] ?? null,
            'presupuestable_type' => $class,
            'presupuestable_id' => (int) $validated['presupuestable_id'],
        ]);

        // Mantiene la columna compat obra_id de los rubros sincronizada con el
        // nuevo presupuestable (obra directa, o null para proyecto/partida).
        if ($cambia) {
            $presupuesto->rubros()->update([
                'obra_id' => $class === Obra::class ? (int) $validated['presupuestable_id'] : null,
            ]);
        }

        return back()->with('success', 'Presupuesto actualizado.');
    }

    /**
     * Cierra o reabre el presupuesto (sustituye el cierre de la obra en cobranza).
     */
    public function cambiarEstado(Presupuesto $presupuesto): RedirectResponse
    {
        Gate::authorize('costos.obra-rubros.editar');

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
            ->map(fn (Obra $o) => ['id' => $o->id, 'label' => $this->etiquetaPresupuestable($o)]);

        $proyectos = Proyecto::query()
            ->whereDoesntHave('presupuesto')
            ->orderBy('no')
            ->get(['id', 'no', 'descripcion'])
            ->map(fn (Proyecto $p) => ['id' => $p->id, 'label' => $this->etiquetaPresupuestable($p)]);

        $partidas = Partida::query()
            ->whereDoesntHave('presupuesto')
            ->with('obra:id,no')
            ->orderBy('id')
            ->get(['id', 'obra_id', 'descripcion'])
            ->map(fn (Partida $p) => ['id' => $p->id, 'label' => $this->etiquetaPresupuestable($p)]);

        return [
            'obra' => $obras->values()->all(),
            'proyecto' => $proyectos->values()->all(),
            'partida' => $partidas->values()->all(),
        ];
    }

    /**
     * Etiqueta legible de un presupuestable (descripción · OP), usada tanto en el
     * selector de creación como en el de edición.
     */
    private function etiquetaPresupuestable(Obra|Proyecto|Partida $p): string
    {
        return match (true) {
            $p instanceof Obra => trim(($p->descripcion ?? '')." · OP-{$p->no}", ' ·'),
            $p instanceof Proyecto => trim(($p->descripcion ?? '')." · {$p->no}", ' ·'),
            $p instanceof Partida => trim(($p->descripcion ?? "Partida #{$p->id}").($p->obra ? " · OP-{$p->obra->no}" : '')),
        };
    }

    /**
     * Página "Obras activas": tabla de presupuestos (obra/proyecto/partida)
     * filtrada por estatus, con pestañas Activas / Cerradas. Solo la tabla.
     */
    public function obrasActivas(Request $request): Response
    {
        // Pantalla de consulta transversal: basta con pertenecer al modulo.
        Gate::authorize('costos.acceso');

        $estatus = $request->string('estatus')->toString() === PresupuestoEstatus::Cerrado->value
            ? PresupuestoEstatus::Cerrado->value
            : PresupuestoEstatus::Activo->value;

        $query = Presupuesto::query()
            ->with('presupuestable')
            ->withSum('rubros', 'presupuestado')
            ->withSum('rubros', 'acumulado')
            ->withSum('rubros', 'apartado')
            ->withCount('rubros')
            ->where('estatus', $estatus);

        $this->aplicarBusqueda($query, $request->search);

        $orden = $this->aplicarOrden($query, $request, $this->columnasOrden(), 'created_at', 'desc');

        $presupuestos = $query->paginate(15)->withQueryString()
            ->through(fn (Presupuesto $p) => $this->presentar($p));

        return Inertia::render('admin/costos/obras-activas/index', [
            'presupuestos' => $presupuestos,
            'estatus' => $estatus,
            'conteos' => [
                'activo' => Presupuesto::where('estatus', PresupuestoEstatus::Activo->value)->count(),
                'cerrado' => Presupuesto::where('estatus', PresupuestoEstatus::Cerrado->value)->count(),
            ],
            'filters' => $request->only('search', 'estatus'),
            'sortBy' => $orden['by'],
            'sortDir' => $orden['dir'],
        ]);
    }

    /**
     * Búsqueda case-insensitive (LOWER LIKE, portable a PostgreSQL) por nombre
     * interno del presupuesto y por número/descripción del presupuestable.
     *
     * @param  Builder<Presupuesto>  $query
     */
    private function aplicarBusqueda(Builder $query, ?string $search): void
    {
        if (! $search) {
            return;
        }

        $needle = '%'.mb_strtolower($search).'%';

        $query->where(function (Builder $q) use ($needle) {
            $q->whereRaw('lower(nombre_interno) like ?', [$needle])
                ->orWhereHasMorph('presupuestable', [Obra::class, Proyecto::class], function ($q2) use ($needle) {
                    $q2->whereRaw('lower(no) like ?', [$needle])->orWhereRaw('lower(descripcion) like ?', [$needle]);
                })
                ->orWhereHasMorph('presupuestable', [Partida::class], function ($q2) use ($needle) {
                    $q2->whereRaw('lower(descripcion) like ?', [$needle]);
                });
        });
    }

    /**
     * Whitelist de columnas ordenables. `descripcion` viene del presupuestable
     * polimórfico: se ordena con un COALESCE de subconsultas por tipo.
     *
     * @return array<string, string|\Closure>
     */
    private function columnasOrden(): array
    {
        return [
            'nombre_interno' => 'nombre_interno',
            'estatus' => 'estatus',
            'descripcion' => fn (Builder $q, string $dir) => $q->orderByRaw(
                'coalesce('.
                '(select descripcion from obras where obras.id = costos_presupuestos.presupuestable_id and costos_presupuestos.presupuestable_type = ?),'.
                '(select descripcion from proyectos where proyectos.id = costos_presupuestos.presupuestable_id and costos_presupuestos.presupuestable_type = ?),'.
                '(select descripcion from cob_partidas where cob_partidas.id = costos_presupuestos.presupuestable_id and costos_presupuestos.presupuestable_type = ?)'.
                ') '.$dir,
                [Obra::class, Proyecto::class, Partida::class],
            ),
            'rubros_count' => 'rubros_count',
            'rubros_sum_presupuestado' => 'rubros_sum_presupuestado',
            'rubros_sum_acumulado' => 'rubros_sum_acumulado',
            'rubros_sum_apartado' => 'rubros_sum_apartado',
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
            'presupuestable_id' => $p->presupuestable_id,
            'nombre' => $p->nombreMostrar(),
            'nombre_interno' => $p->nombre_interno,
            'op' => $p->opMostrar(),
            'op_interno' => $p->op_interno,
            'no' => $presupuestable->no ?? null,
            'descripcion' => $presupuestable->descripcion ?? null,
            'estatus' => $p->estatus->value,
            'es_planta' => $esPlanta,
            'rubros_count' => (int) ($p->rubros_count ?? 0),
            'sum_presupuestado' => (float) ($p->rubros_sum_presupuestado ?? 0),
            'sum_acumulado' => (float) ($p->rubros_sum_acumulado ?? 0),
            'sum_apartado' => (float) ($p->rubros_sum_apartado ?? 0),
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
                'apartado' => $r->apartado,
                'comprometido' => $r->comprometido,
                'disponible' => $r->disponible,
                'rubro' => $r->rubro,
            ])->values(),
        ];
    }

    /**
     * @param  Collection<int, ObraRubro>  $obraRubros
     * @return array{total_rubros: int, sobregiros: int, criticos: int, total_presupuestado: float, total_acumulado: float, total_apartado: float, total_comprometido: float}
     */
    private function calcularStats(Collection $obraRubros, int $umbral): array
    {
        $sobregiros = 0;
        $criticos = 0;
        $totalPresupuestado = 0.0;
        $totalAcumulado = 0.0;
        $totalApartado = 0.0;
        $umbralPct = (float) $umbral;

        foreach ($obraRubros as $r) {
            $presup = (float) $r->presupuestado;
            $acum = (float) $r->acumulado;
            // El sobregiro/critico pesa contra lo comprometido (ejercido + apartado).
            $comprometido = (float) $r->comprometido;
            $totalPresupuestado += $presup;
            $totalAcumulado += $acum;
            $totalApartado += (float) $r->apartado;

            if ($presup <= 0.0) {
                if ($comprometido > 0.0) {
                    $sobregiros++;
                }

                continue;
            }

            $pct = ($comprometido / $presup) * 100.0;
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
            'total_apartado' => $totalApartado,
            'total_comprometido' => $totalAcumulado + $totalApartado,
        ];
    }

    public function generarReportePdf(): HttpResponse
    {
        Gate::authorize('costos.obra-rubros.ver');

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
