<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\RubroStoreRequest;
use App\Http\Requests\Admin\Costos\RubroUpdateRequest;
use App\Models\Costos\Rubro;
use App\Models\Costos\TipoRubro;
use App\Models\Departamento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RubroController extends Controller
{
    public function index(Request $request): Response
    {
        $rubros = Rubro::query()
            ->with(['tipoRubro', 'departamento'])
            ->when($request->search, fn ($q, $s) => $q->where('descripcion', 'like', "%{$s}%")
                ->orWhere('codigo', 'like', "%{$s}%"))
            ->orderBy('codigo')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/costos/rubros/index', [
            'rubros' => $rubros,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/costos/rubros/create', [
            'tipoRubros' => TipoRubro::query()->orderBy('descripcion')->get(),
            'departamentos' => Departamento::query()->orderBy('descripcion')->get(),
        ]);
    }

    public function store(RubroStoreRequest $request): RedirectResponse
    {
        Rubro::create($request->validated());

        return to_route('admin.costos.rubros.index');
    }

    public function edit(Rubro $rubro): Response
    {
        return Inertia::render('admin/costos/rubros/edit', [
            'rubro' => $rubro->load(['tipoRubro', 'departamento']),
            'tipoRubros' => TipoRubro::query()->orderBy('descripcion')->get(),
            'departamentos' => Departamento::query()->orderBy('descripcion')->get(),
        ]);
    }

    public function update(RubroUpdateRequest $request, Rubro $rubro): RedirectResponse
    {
        $rubro->update($request->validated());

        return to_route('admin.costos.rubros.index');
    }

    public function destroy(Rubro $rubro): RedirectResponse
    {
        $rubro->delete();

        return to_route('admin.costos.rubros.index');
    }
}
