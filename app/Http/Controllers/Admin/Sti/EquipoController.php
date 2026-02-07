<?php

namespace App\Http\Controllers\Admin\Sti;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Sti\EquipoStoreRequest;
use App\Http\Requests\Admin\Sti\EquipoUpdateRequest;
use App\Models\Sti\Equipo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EquipoController extends Controller
{
    public function index(Request $request): Response
    {
        $equipos = Equipo::query()
            ->withCount(['tickets', 'mantenimientos'])
            ->with(['tickets.costos', 'mantenimientos.costos'])
            ->when($request->search, fn ($q, $s) => $q->where('descripcion', 'like', "%{$s}%")
                ->orWhere('serie', 'like', "%{$s}%")
                ->orWhere('marca', 'like', "%{$s}%"))
            ->orderBy('descripcion')
            ->paginate(15)
            ->withQueryString();

        // Calculate total costos for each equipo
        $equipos->getCollection()->transform(function ($equipo) {
            $ticketsCostos = $equipo->tickets->sum(fn ($t) => $t->costos->sum('cantidad'));
            $mantenimientosCostos = $equipo->mantenimientos->sum(fn ($m) => $m->costos->sum('cantidad'));
            $equipo->total_costos = $ticketsCostos + $mantenimientosCostos;

            // Unset the loaded relations to reduce payload
            unset($equipo->tickets, $equipo->mantenimientos);

            return $equipo;
        });

        return Inertia::render('admin/sti/equipos/index', [
            'equipos' => $equipos,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/sti/equipos/create');
    }

    public function store(EquipoStoreRequest $request): RedirectResponse
    {
        Equipo::create([
            'descripcion' => $request->descripcion,
            'serie' => $request->serie,
            'marca' => $request->marca,
            'factor_criticidad' => $request->factor_criticidad,
        ]);

        return to_route('admin.sti.equipos.index');
    }

    public function edit(Equipo $equipo): Response
    {
        // Cargar historial de tickets y mantenimientos con costos
        $equipo->load([
            'tickets.historial.status',
            'tickets.costos',
            'tickets.tecnico',
            'mantenimientos.costos',
            'mantenimientos.tecnico',
        ]);

        return Inertia::render('admin/sti/equipos/edit', [
            'equipo' => $equipo,
        ]);
    }

    public function update(EquipoUpdateRequest $request, Equipo $equipo): RedirectResponse
    {
        $equipo->update([
            'descripcion' => $request->descripcion,
            'serie' => $request->serie,
            'marca' => $request->marca,
            'factor_criticidad' => $request->factor_criticidad,
        ]);

        return to_route('admin.sti.equipos.index');
    }

    public function destroy(Equipo $equipo): RedirectResponse
    {
        if ($equipo->tickets()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar un equipo que tiene tickets asociados.']);
        }

        if ($equipo->mantenimientos()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar un equipo que tiene mantenimientos asociados.']);
        }

        if ($equipo->asignaciones()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar un equipo que tiene asignaciones activas.']);
        }

        $equipo->delete();

        return to_route('admin.sti.equipos.index');
    }
}
