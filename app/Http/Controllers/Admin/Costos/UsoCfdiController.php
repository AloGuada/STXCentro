<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\UsoCfdiStoreRequest;
use App\Http\Requests\Admin\Costos\UsoCfdiUpdateRequest;
use App\Models\Costos\UsoCfdi;
use App\Support\OrdenaColumnas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UsoCfdiController extends Controller
{
    use OrdenaColumnas;

    public function index(Request $request): Response
    {
        Gate::authorize('costos.usos-cfdi.ver');

        $query = UsoCfdi::query()
            ->withCount('requisicionDetalles')
            ->when($request->search, fn ($q, $s) => $q->where('clave', 'like', "%{$s}%")
                ->orWhere('descripcion', 'like', "%{$s}%"));

        $orden = $this->aplicarOrden($query, $request, [
            'clave' => 'clave',
            'descripcion' => 'descripcion',
            'activo' => 'activo',
            'requisicion_detalles_count' => 'requisicion_detalles_count',
        ], 'clave', 'asc');

        $usosCfdi = $query->paginate(15)->withQueryString();

        return Inertia::render('admin/costos/usos-cfdi/index', [
            'usosCfdi' => $usosCfdi,
            'filters' => $request->only('search'),
            'sortBy' => $orden['by'],
            'sortDir' => $orden['dir'],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('costos.usos-cfdi.crear');

        return Inertia::render('admin/costos/usos-cfdi/create');
    }

    public function store(UsoCfdiStoreRequest $request): RedirectResponse
    {
        Gate::authorize('costos.usos-cfdi.crear');

        UsoCfdi::create($request->validated());

        return to_route('admin.costos.usos-cfdi.index')->with('success', 'Uso de CFDI creado.');
    }

    public function edit(UsoCfdi $usoCfdi): Response
    {
        Gate::authorize('costos.usos-cfdi.editar');

        return Inertia::render('admin/costos/usos-cfdi/edit', [
            'usoCfdi' => $usoCfdi,
        ]);
    }

    public function update(UsoCfdiUpdateRequest $request, UsoCfdi $usoCfdi): RedirectResponse
    {
        Gate::authorize('costos.usos-cfdi.editar');

        $usoCfdi->update($request->validated());

        return to_route('admin.costos.usos-cfdi.index')->with('success', 'Uso de CFDI actualizado.');
    }

    public function destroy(UsoCfdi $usoCfdi): RedirectResponse
    {
        Gate::authorize('costos.usos-cfdi.eliminar');

        if ($usoCfdi->requisicionDetalles()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar un uso de CFDI con partidas asociadas.']);
        }

        $usoCfdi->delete();

        return to_route('admin.costos.usos-cfdi.index')->with('success', 'Uso de CFDI eliminado.');
    }
}
