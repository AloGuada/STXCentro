<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\EtapaStoreRequest;
use App\Http\Requests\Admin\Cob\PlanCobroRequest;
use App\Http\Requests\Admin\Cob\PlaneacionRequest;
use App\Http\Requests\Admin\Cob\ProyectoStoreRequest;
use App\Http\Requests\Admin\Cob\ProyectoUpdateRequest;
use App\Models\Cliente;
use App\Models\Cob\ObraEtapa;
use App\Models\Proyecto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ProyectoController extends Controller
{
    public function index(Request $request): Response
    {
        $estatus = in_array($request->estatus, ['abierta', 'cerrada', 'todas'], true)
            ? $request->estatus
            : 'abierta';

        $proyectos = Proyecto::query()
            ->with([
                'cliente',
                'obras.partidas',
                'obras.anticipos',
                'obras.comparativos',
                'obras.deducciones',
                'obras.estimaciones.pagos',
                'obras.estimaciones.historial',
            ])
            ->when($estatus !== 'todas', fn ($q) => $q->where('estatus', $estatus))
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('no', 'like', "%{$s}%")
                ->orWhere('descripcion', 'like', "%{$s}%")))
            ->orderBy('no')
            ->get();

        return Inertia::render('admin/cob/proyectos/index', [
            'proyectos' => $proyectos,
            'filters' => [
                'search' => $request->search,
                'estatus' => $estatus,
            ],
        ]);
    }

    public function show(Proyecto $proyecto): Response
    {
        $proyecto->load([
            'cliente',
            // Lista de obras (base primero) con lo necesario para el resumen y las
            // tarjetas; el detalle de cada obra vive en su propia página.
            'obras' => fn ($q) => $q->orderByRaw("CASE WHEN tipo = 'base' THEN 0 ELSE 1 END")->orderBy('no'),
            'obras.partidas',
            'obras.comparativos',
            'obras.deducciones',
            'obras.anticipos',
            'obras.etapasPmo',
            // Estimaciones por obra: alimentan el rollup y la sección "Real" del Gantt.
            'obras.estimaciones.pagos',
            'obras.estimaciones.historial',
            // Todas las estimaciones del proyecto (cualquier nivel) para el tab Estimaciones.
            'estimaciones' => fn ($q) => $q->orderByDesc('numero_estimacion'),
            'estimaciones.obra:id,no',
            'estimaciones.pagos',
            'planCobro',
        ]);

        return Inertia::render('admin/cob/proyectos/show', [
            'proyecto' => $proyecto,
            'clientes' => $this->clientes(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/cob/proyectos/create', [
            'clientes' => $this->clientes(),
        ]);
    }

    public function store(ProyectoStoreRequest $request): RedirectResponse
    {
        // El proyecto nace vacío; las obras se agregan después desde su vista
        // (cada una con su propio `no`, descripción y datos de contrato).
        $proyecto = Proyecto::create($request->validated());

        return to_route('admin.cob.proyectos.show', $proyecto);
    }

    public function update(ProyectoUpdateRequest $request, Proyecto $proyecto): RedirectResponse
    {
        $proyecto->update($request->validated());

        return back();
    }

    /**
     * Regenera el cronograma planeado de cobro: N estimaciones, cada una con sus
     * días; las fechas se calculan secuencialmente desde la fecha de inicio del
     * plan (periodos contiguos). Reemplaza el plan anterior.
     */
    public function guardarPlanCobro(PlanCobroRequest $request, Proyecto $proyecto): RedirectResponse
    {
        $data = $request->validated();

        DB::transaction(function () use ($data, $proyecto): void {
            $proyecto->update(['fecha_inicio_plan' => $data['fecha_inicio_plan']]);
            $proyecto->planCobro()->delete();

            $dias = (int) $data['dias'];
            $cursor = Carbon::parse($data['fecha_inicio_plan'])->startOfDay();
            for ($orden = 1; $orden <= (int) $data['numero']; $orden++) {
                $inicio = $cursor->copy();
                $fin = $cursor->copy()->addDays($dias);

                $proyecto->planCobro()->create([
                    'orden' => $orden,
                    'dias' => $dias,
                    'fecha_inicio_plan' => $inicio->toDateString(),
                    'fecha_fin_plan' => $fin->toDateString(),
                ]);

                $cursor = $fin;
            }
        });

        return back();
    }

    /**
     * Guarda los ajustes hechos arrastrando en el Gantt: fechas de cada periodo
     * planeado (por orden) y de las etapas PMO por obra (upsert).
     */
    public function guardarPlaneacion(PlaneacionRequest $request, Proyecto $proyecto): RedirectResponse
    {
        $data = $request->validated();
        $obraIds = $proyecto->obras()->pluck('id');

        DB::transaction(function () use ($data, $proyecto, $obraIds): void {
            foreach ($data['plan'] ?? [] as $p) {
                $proyecto->planCobro()->where('orden', $p['orden'])->update([
                    'fecha_inicio_plan' => $p['fecha_inicio_plan'],
                    'fecha_fin_plan' => $p['fecha_fin_plan'],
                    'dias' => (int) Carbon::parse($p['fecha_inicio_plan'])->diffInDays(Carbon::parse($p['fecha_fin_plan'])),
                ]);
            }

            foreach ($data['etapas'] ?? [] as $e) {
                ObraEtapa::query()
                    ->where('id', $e['id'])
                    ->whereIn('obra_id', $obraIds)
                    ->update([
                        'fecha_inicio_plan' => $e['fecha_inicio_plan'],
                        'fecha_fin_plan' => $e['fecha_fin_plan'],
                    ]);
            }
        });

        return back();
    }

    /** Crea una etapa PMO (obra + descripción + fechas) desde el modal del Gantt. */
    public function storeEtapa(EtapaStoreRequest $request, Proyecto $proyecto): RedirectResponse
    {
        $data = $request->validated();
        abort_unless($proyecto->obras()->whereKey($data['obra_id'])->exists(), 404);

        ObraEtapa::create($data);

        return back();
    }

    public function destroyEtapa(Proyecto $proyecto, ObraEtapa $etapa): RedirectResponse
    {
        abort_unless($proyecto->obras()->whereKey($etapa->obra_id)->exists(), 404);

        $etapa->delete();

        return back();
    }

    /** @return Collection<int, Cliente> */
    private function clientes(): Collection
    {
        return Cliente::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);
    }
}
