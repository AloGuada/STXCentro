<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\ProyectoStoreRequest;
use App\Models\Cliente;
use App\Models\Proyecto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
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
                'estimaciones.pagos',
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
            'obras.partidas',
            'obras.anticipos',
            'obras.comparativos',
            'obras.deducciones',
            'obras.subObras.partidas',
            'estimaciones.pagos',
            'estimaciones.historial',
        ]);

        return Inertia::render('admin/cob/proyectos/show', [
            'proyecto' => $proyecto,
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
        $proyecto = DB::transaction(function () use ($request): Proyecto {
            $proyecto = Proyecto::create($request->validated());

            // Al crear el proyecto se crea su obra base (centro de costo). Sus
            // partidas quedan pendientes: el usuario las carga después. El
            // presupuesto (obra_rubros) se auto-crea vía Obra::booted().
            $proyecto->obras()->create([
                'no' => $proyecto->no,
                'descripcion' => $proyecto->descripcion,
                'cliente_id' => $proyecto->cliente_id,
                'tipo_contrato' => $proyecto->tipo_contrato,
                'tipo' => 'base',
                'estatus' => 'abierta',
                'activa' => true,
            ]);

            return $proyecto;
        });

        return to_route('admin.cob.proyectos.show', $proyecto);
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
