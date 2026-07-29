<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\UbicacionStoreRequest;
use App\Http\Requests\Admin\Prod\UbicacionUpdateRequest;
use App\Models\Prod\Ubicacion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UbicacionController extends Controller
{
    public function index(Request $request): Response
    {
        $ubicaciones = Ubicacion::query()
            ->when($request->search, fn ($q, $s) => $q->where('nombre', 'like', "%{$s}%"))
            ->withCount('gruposTrabajo')
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/prod/ubicaciones/index', [
            'ubicaciones' => $ubicaciones,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/prod/ubicaciones/create');
    }

    public function store(UbicacionStoreRequest $request): RedirectResponse
    {
        Ubicacion::create($request->validated());

        return to_route('admin.prod.ubicaciones.index');
    }

    public function edit(Ubicacion $ubicacion): Response
    {
        return Inertia::render('admin/prod/ubicaciones/edit', [
            'ubicacion' => $ubicacion->loadCount('gruposTrabajo'),
        ]);
    }

    public function update(UbicacionUpdateRequest $request, Ubicacion $ubicacion): RedirectResponse
    {
        $ubicacion->update($request->validated());

        return to_route('admin.prod.ubicaciones.index');
    }

    public function destroy(Ubicacion $ubicacion): RedirectResponse
    {
        if ($ubicacion->gruposTrabajo()->exists()) {
            return back()->withErrors(['error' => 'No se puede eliminar una ubicacion asignada a grupos de trabajo.']);
        }

        $ubicacion->delete();

        return to_route('admin.prod.ubicaciones.index');
    }
}
