<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ObraStoreRequest;
use App\Http\Requests\Admin\ObraUpdateRequest;
use App\Models\Costos\Rubro;
use App\Models\Obra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ObraController extends Controller
{
    public function index(Request $request): Response
    {
        $obras = Obra::query()
            ->sinPlanta()
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('no', 'like', "%{$s}%")
                ->orWhere('descripcion', 'like', "%{$s}%")))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/obras/index', [
            'obras' => $obras,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/obras/create');
    }

    public function store(ObraStoreRequest $request): RedirectResponse
    {
        Obra::create($request->validated());

        return to_route('admin.obras.index');
    }

    public function edit(Obra $obra): Response
    {
        abort_if($obra->es_planta, 404);

        $obra->load([
            'conceptos' => fn ($q) => $q->orderBy('marca'),
            'obraRubros.rubro.tipoRubro',
        ]);

        return Inertia::render('admin/obras/edit', [
            'obra' => $obra,
            'rubros' => Rubro::query()->where('ambito', 'obra')->with('tipoRubro')->orderBy('codigo')->get(),
        ]);
    }

    public function update(ObraUpdateRequest $request, Obra $obra): RedirectResponse
    {
        abort_if($obra->es_planta, 404);

        $obra->update($request->validated());

        return to_route('admin.obras.index');
    }

    public function destroy(Obra $obra): RedirectResponse
    {
        abort_if($obra->es_planta, 404);

        $obra->delete();

        return to_route('admin.obras.index');
    }
}
