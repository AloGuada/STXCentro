<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\PermisoStoreRequest;
use App\Http\Requests\Admin\Costos\PermisoUpdateRequest;
use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\Permiso;
use App\Models\Departamento;
use App\Models\Usuario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class PermisoController extends Controller
{
    public function index(Request $request): Response
    {
        $permisos = Permiso::query()
            ->when($request->search, function ($query, $search) {
                $query->where('descripcion', 'like', "%{$search}%");
            })
            ->orderBy('nivel')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/costos/permisos/index', [
            'permisos' => $permisos,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/costos/permisos/create');
    }

    public function store(PermisoStoreRequest $request): RedirectResponse
    {
        Permiso::create($request->validated());

        return to_route('admin.costos.permisos.index');
    }

    public function show(Permiso $permiso): Response
    {
        $departamentos = Departamento::orderBy('descripcion')->get(['id', 'descripcion']);

        $roleId = Role::where('name', 'admin_costos_alt')->value('id');

        $usuarios = $roleId
            ? Usuario::whereHas('roles', fn ($q) => $q->where('role_id', $roleId))
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();

        $asignaciones = AprobacionDepartamento::where('permiso_id', $permiso->id)
            ->get()
            ->groupBy('departamento_id')
            ->map(fn ($group) => $group->pluck('aprobador_id')->filter()->values());

        return Inertia::render('admin/costos/permisos/show', [
            'permiso' => $permiso,
            'departamentos' => $departamentos,
            'usuarios' => $usuarios,
            'asignaciones' => $asignaciones,
        ]);
    }

    public function edit(Permiso $permiso): Response
    {
        return Inertia::render('admin/costos/permisos/edit', [
            'permiso' => $permiso,
        ]);
    }

    public function update(PermisoUpdateRequest $request, Permiso $permiso): RedirectResponse
    {
        $permiso->update($request->validated());

        return to_route('admin.costos.permisos.index');
    }

    public function destroy(Permiso $permiso): RedirectResponse
    {
        $permiso->delete();

        return to_route('admin.costos.permisos.index');
    }

    public function syncDepartamentos(Request $request, Permiso $permiso): RedirectResponse
    {
        $request->validate([
            'asignaciones' => ['required', 'array'],
            'asignaciones.*.departamento_id' => ['required', 'exists:departamentos,id'],
            'asignaciones.*.aprobador_ids' => ['nullable', 'array'],
            'asignaciones.*.aprobador_ids.*' => ['exists:usuarios,id'],
        ]);

        AprobacionDepartamento::where('permiso_id', $permiso->id)->delete();

        foreach ($request->input('asignaciones', []) as $asignacion) {
            foreach ($asignacion['aprobador_ids'] ?? [] as $aprobadorId) {
                AprobacionDepartamento::create([
                    'departamento_id' => $asignacion['departamento_id'],
                    'permiso_id' => $permiso->id,
                    'aprobador_id' => $aprobadorId,
                ]);
            }
        }

        return back()->with('success', 'Asignaciones actualizadas correctamente.');
    }
}
