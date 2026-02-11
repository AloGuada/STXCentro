<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\GrupoTrabajoStoreRequest;
use App\Http\Requests\Admin\Prod\GrupoTrabajoUpdateRequest;
use App\Models\Prod\GrupoEmpleado;
use App\Models\Prod\GrupoTrabajo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GrupoTrabajoController extends Controller
{
    public function index(Request $request): Response
    {
        $grupos = GrupoTrabajo::query()
            ->withCount('empleados')
            ->when($request->search, fn ($q, $s) => $q->where('descripcion', 'like', "%{$s}%"))
            ->orderBy('descripcion')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/prod/grupos-trabajo/index', [
            'grupos' => $grupos,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/prod/grupos-trabajo/create');
    }

    public function store(GrupoTrabajoStoreRequest $request): RedirectResponse
    {
        return DB::transaction(function () use ($request) {
            $grupo = GrupoTrabajo::create([
                'descripcion' => $request->descripcion,
                'linea' => $request->linea ?? 0,
                'modulo' => $request->modulo ?? 0,
                'activo' => $request->boolean('activo', true),
            ]);

            if ($request->filled('empleados')) {
                foreach ($request->empleados as $empData) {
                    $grupo->empleados()->create([
                        'nombre' => $empData['nombre'],
                        'no_empleado' => $empData['no_empleado'] ?? null,
                        'porcentaje' => $empData['porcentaje'] ?? 100.00,
                    ]);
                }
            }

            return to_route('admin.prod.grupos-trabajo.edit', $grupo);
        });
    }

    public function edit(GrupoTrabajo $grupoTrabajo): Response
    {
        $grupoTrabajo->load('empleados');

        return Inertia::render('admin/prod/grupos-trabajo/edit', [
            'grupo' => $grupoTrabajo,
        ]);
    }

    public function update(GrupoTrabajoUpdateRequest $request, GrupoTrabajo $grupoTrabajo): RedirectResponse
    {
        $grupoTrabajo->update([
            'descripcion' => $request->descripcion,
            'linea' => $request->linea ?? $grupoTrabajo->linea,
            'modulo' => $request->modulo ?? $grupoTrabajo->modulo,
            'activo' => $request->boolean('activo', $grupoTrabajo->activo),
        ]);

        return to_route('admin.prod.grupos-trabajo.index');
    }

    public function destroy(GrupoTrabajo $grupoTrabajo): RedirectResponse
    {
        if ($grupoTrabajo->registros()->exists() || $grupoTrabajo->liquidaciones()->exists()) {
            return back()->withErrors(['error' => 'No se puede eliminar un grupo que tiene registros o liquidaciones asociadas.']);
        }

        $grupoTrabajo->empleados()->delete();
        $grupoTrabajo->delete();

        return to_route('admin.prod.grupos-trabajo.index');
    }

    public function storeEmpleado(Request $request, GrupoTrabajo $grupoTrabajo): RedirectResponse
    {
        $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'no_empleado' => ['nullable', 'string', 'max:50'],
            'porcentaje' => ['nullable', 'numeric', 'min:0', 'max:100'],
        ]);

        $grupoTrabajo->empleados()->create([
            'nombre' => $request->nombre,
            'no_empleado' => $request->no_empleado,
            'porcentaje' => $request->porcentaje ?? 100.00,
        ]);

        return back();
    }

    public function destroyEmpleado(GrupoTrabajo $grupoTrabajo, GrupoEmpleado $empleado): RedirectResponse
    {
        if ($empleado->grupo_trabajo_id !== $grupoTrabajo->id) {
            abort(404);
        }

        $empleado->delete();

        return back();
    }
}
