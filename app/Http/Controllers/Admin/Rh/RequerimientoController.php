<?php

namespace App\Http\Controllers\Admin\Rh;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Rh\RequerimientoStoreRequest;
use App\Http\Requests\Admin\Rh\RequerimientoUpdateRequest;
use App\Models\Rh\Requerimiento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RequerimientoController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('rh.requerimientos.ver');

        $requerimientos = Requerimiento::query()
            ->when($request->search, fn ($q, $s) => $q->where('descripcion', 'like', "%{$s}%"))
            ->orderBy('descripcion')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/rh/requerimientos/index', [
            'requerimientos' => $requerimientos,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('rh.requerimientos.crear');

        return Inertia::render('admin/rh/requerimientos/create');
    }

    public function store(RequerimientoStoreRequest $request): RedirectResponse
    {
        $this->authorize('rh.requerimientos.crear');

        Requerimiento::create([
            'descripcion' => $request->descripcion,
            'valor' => $request->valor,
        ]);

        return to_route('admin.rh.requerimientos.index');
    }

    public function edit(Requerimiento $requerimiento): Response
    {
        $this->authorize('rh.requerimientos.editar');

        return Inertia::render('admin/rh/requerimientos/edit', [
            'requerimiento' => $requerimiento,
        ]);
    }

    public function update(RequerimientoUpdateRequest $request, Requerimiento $requerimiento): RedirectResponse
    {
        $this->authorize('rh.requerimientos.editar');

        $requerimiento->update([
            'descripcion' => $request->descripcion,
            'valor' => $request->valor,
        ]);

        return to_route('admin.rh.requerimientos.index');
    }

    public function destroy(Requerimiento $requerimiento): RedirectResponse
    {
        $this->authorize('rh.requerimientos.eliminar');

        if ($requerimiento->puestos()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar un requerimiento asignado a puestos.']);
        }

        $requerimiento->delete();

        return to_route('admin.rh.requerimientos.index');
    }
}
