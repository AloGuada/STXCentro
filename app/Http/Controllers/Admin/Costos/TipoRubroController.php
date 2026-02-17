<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\TipoRubroStoreRequest;
use App\Http\Requests\Admin\Costos\TipoRubroUpdateRequest;
use App\Models\Costos\TipoRubro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TipoRubroController extends Controller
{
    public function index(Request $request): Response
    {
        $tipoRubros = TipoRubro::query()
            ->withCount('rubros')
            ->when($request->search, fn ($q, $s) => $q->where('descripcion', 'like', "%{$s}%"))
            ->orderBy('descripcion')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/costos/tipo-rubros/index', [
            'tipoRubros' => $tipoRubros,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/costos/tipo-rubros/create');
    }

    public function store(TipoRubroStoreRequest $request): RedirectResponse
    {
        TipoRubro::create($request->validated());

        return to_route('admin.costos.tipo-rubros.index');
    }

    public function edit(TipoRubro $tipoRubro): Response
    {
        return Inertia::render('admin/costos/tipo-rubros/edit', [
            'tipoRubro' => $tipoRubro,
        ]);
    }

    public function update(TipoRubroUpdateRequest $request, TipoRubro $tipoRubro): RedirectResponse
    {
        $tipoRubro->update($request->validated());

        return to_route('admin.costos.tipo-rubros.index');
    }

    public function destroy(TipoRubro $tipoRubro): RedirectResponse
    {
        if ($tipoRubro->rubros()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar un tipo de rubro que tiene rubros asociados.']);
        }

        $tipoRubro->delete();

        return to_route('admin.costos.tipo-rubros.index');
    }
}
