<?php

namespace App\Http\Controllers\Admin\Rh;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Rh\PermisoAusenciaStoreRequest;
use App\Http\Requests\Admin\Rh\PermisoAusenciaUpdateRequest;
use App\Models\Rh\PermisoAusencia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PermisoAusenciaController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('rh.permisos-ausencia.ver');

        $permisos = PermisoAusencia::query()
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('folio', 'like', "%{$search}%")
                        ->orWhere('nombres', 'like', "%{$search}%")
                        ->orWhere('apellidos', 'like', "%{$search}%")
                        ->orWhere('tipo', 'like', "%{$search}%")
                        ->orWhere('modalidad', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/rh/permisos-ausencia/index', [
            'permisos' => $permisos,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('rh.permisos-ausencia.crear');

        return Inertia::render('admin/rh/permisos-ausencia/create');
    }

    public function store(PermisoAusenciaStoreRequest $request): RedirectResponse
    {
        $this->authorize('rh.permisos-ausencia.crear');

        $folio = 'PA-'.date('Y').'-'.str_pad((string) (PermisoAusencia::whereYear('created_at', date('Y'))->count() + 1), 4, '0', STR_PAD_LEFT);

        PermisoAusencia::create(array_merge($request->validated(), [
            'folio' => $folio,
            'fecha_elaboracion' => now(),
        ]));

        return to_route('admin.rh.permisos-ausencia.index');
    }

    public function edit(PermisoAusencia $permisoAusencia): Response
    {
        $this->authorize('rh.permisos-ausencia.editar');

        return Inertia::render('admin/rh/permisos-ausencia/edit', [
            'permiso' => $permisoAusencia,
        ]);
    }

    public function update(PermisoAusenciaUpdateRequest $request, PermisoAusencia $permisoAusencia): RedirectResponse
    {
        $this->authorize('rh.permisos-ausencia.editar');

        $permisoAusencia->update($request->validated());

        return to_route('admin.rh.permisos-ausencia.index');
    }

    public function destroy(PermisoAusencia $permisoAusencia): RedirectResponse
    {
        $this->authorize('rh.permisos-ausencia.eliminar');

        $permisoAusencia->delete();

        return to_route('admin.rh.permisos-ausencia.index');
    }
}
