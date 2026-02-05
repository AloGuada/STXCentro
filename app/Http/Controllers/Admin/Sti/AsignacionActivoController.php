<?php

namespace App\Http\Controllers\Admin\Sti;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Sti\AsignacionActivoStoreRequest;
use App\Http\Requests\Admin\Sti\AsignacionActivoUpdateRequest;
use App\Models\Departamento;
use App\Models\Sti\AsignacionActivo;
use App\Models\Sti\Equipo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AsignacionActivoController extends Controller
{
    public function index(Request $request): Response
    {
        $asignaciones = AsignacionActivo::query()
            ->with(['departamento', 'equipo'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('empleado', 'like', "%{$search}%")
                        ->orWhere('no_empleado', 'like', "%{$search}%")
                        ->orWhere('nombre_ti', 'like', "%{$search}%")
                        ->orWhereHas('equipo', fn ($eq) => $eq->where('descripcion', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/sti/asignacion-activos/index', [
            'asignaciones' => $asignaciones,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/sti/asignacion-activos/create', [
            'departamentos' => Departamento::orderBy('descripcion')->get(['id', 'descripcion']),
            'equipos' => Equipo::orderBy('descripcion')->get(['id', 'descripcion', 'serie']),
        ]);
    }

    public function store(AsignacionActivoStoreRequest $request): RedirectResponse
    {
        AsignacionActivo::create($request->validated());

        return to_route('admin.sti.asignacion-activos.index')->with('success', 'Asignacion creada correctamente.');
    }

    public function edit(AsignacionActivo $asignacion_activo): Response
    {
        return Inertia::render('admin/sti/asignacion-activos/edit', [
            'asignacion' => $asignacion_activo->load(['departamento', 'equipo']),
            'departamentos' => Departamento::orderBy('descripcion')->get(['id', 'descripcion']),
            'equipos' => Equipo::orderBy('descripcion')->get(['id', 'descripcion', 'serie']),
        ]);
    }

    public function update(AsignacionActivoUpdateRequest $request, AsignacionActivo $asignacion_activo): RedirectResponse
    {
        $asignacion_activo->update($request->validated());

        return to_route('admin.sti.asignacion-activos.index')->with('success', 'Asignacion actualizada correctamente.');
    }

    public function destroy(AsignacionActivo $asignacion_activo): RedirectResponse
    {
        $asignacion_activo->delete();

        return to_route('admin.sti.asignacion-activos.index')->with('success', 'Asignacion eliminada correctamente.');
    }
}
