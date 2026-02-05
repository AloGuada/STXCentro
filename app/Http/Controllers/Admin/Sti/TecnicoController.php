<?php

namespace App\Http\Controllers\Admin\Sti;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Sti\TecnicoStoreRequest;
use App\Http\Requests\Admin\Sti\TecnicoUpdateRequest;
use App\Models\Sti\Tecnico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TecnicoController extends Controller
{
    public function index(Request $request): Response
    {
        $tecnicos = Tecnico::query()
            ->when($request->search, fn ($q, $s) => $q->where('descripcion', 'like', "%{$s}%"))
            ->when(! $request->boolean('todos'), fn ($q) => $q->where('activo', true))
            ->orderBy('descripcion')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/sti/tecnicos/index', [
            'tecnicos' => $tecnicos,
            'filters' => $request->only(['search', 'todos']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/sti/tecnicos/create');
    }

    public function store(TecnicoStoreRequest $request): RedirectResponse
    {
        Tecnico::create([
            'descripcion' => $request->descripcion,
            'activo' => $request->boolean('activo', true),
        ]);

        return to_route('admin.sti.tecnicos.index');
    }

    public function edit(Tecnico $tecnico): Response
    {
        return Inertia::render('admin/sti/tecnicos/edit', [
            'tecnico' => $tecnico,
        ]);
    }

    public function update(TecnicoUpdateRequest $request, Tecnico $tecnico): RedirectResponse
    {
        $tecnico->update([
            'descripcion' => $request->descripcion,
            'activo' => $request->boolean('activo', true),
        ]);

        return to_route('admin.sti.tecnicos.index');
    }

    public function destroy(Tecnico $tecnico): RedirectResponse
    {
        if ($tecnico->tickets()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar un técnico que tiene tickets asignados.']);
        }

        if ($tecnico->mantenimientos()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar un técnico que tiene mantenimientos asignados.']);
        }

        $tecnico->delete();

        return to_route('admin.sti.tecnicos.index');
    }
}
