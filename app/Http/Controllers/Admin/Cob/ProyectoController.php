<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\ProyectoStoreRequest;
use App\Models\Cliente;
use App\Models\Proyecto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
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
            ->with(['cliente', 'obras'])
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
            'obras.subObras.partidas',
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
        $proyecto = Proyecto::create($request->validated());

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
