<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\GrupoStoreRequest;
use App\Http\Requests\Admin\Prod\GrupoUpdateRequest;
use App\Models\Prod\EmpleadoGrupo;
use App\Models\Prod\Grupo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class GrupoController extends Controller
{
    public function index(Request $request): Response
    {
        $grupos = Grupo::query()
            ->withCount(['empleados', 'fabricados'])
            ->when($request->search, fn ($q, $s) => $q->where('descripcion', 'like', "%{$s}%"))
            ->orderBy('descripcion')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/prod/grupos/index', [
            'grupos' => $grupos,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/prod/grupos/create');
    }

    public function store(GrupoStoreRequest $request): RedirectResponse
    {
        return DB::transaction(function () use ($request) {
            $grupo = Grupo::create([
                'descripcion' => $request->descripcion,
            ]);

            if ($request->filled('empleados')) {
                foreach ($request->empleados as $empData) {
                    $grupo->empleados()->create([
                        'nombre' => $empData['nombre'],
                        'no_empleado' => $empData['no_empleado'] ?? null,
                    ]);
                }
            }

            return to_route('admin.prod.grupos.edit', $grupo);
        });
    }

    public function edit(Grupo $grupo): Response
    {
        $grupo->load('empleados');

        return Inertia::render('admin/prod/grupos/edit', [
            'grupo' => $grupo,
        ]);
    }

    public function update(GrupoUpdateRequest $request, Grupo $grupo): RedirectResponse
    {
        $grupo->update([
            'descripcion' => $request->descripcion,
        ]);

        return to_route('admin.prod.grupos.index');
    }

    public function destroy(Grupo $grupo): RedirectResponse
    {
        if ($grupo->fabricados()->exists() || $grupo->pagosExtra()->exists()) {
            return back()->withErrors(['error' => 'No se puede eliminar un grupo que tiene fabricados o pagos extra asociados.']);
        }

        $grupo->empleados()->delete();
        $grupo->delete();

        return to_route('admin.prod.grupos.index');
    }

    public function storeEmpleado(Request $request, Grupo $grupo): RedirectResponse
    {
        $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'no_empleado' => ['nullable', 'string', 'max:50'],
        ]);

        $grupo->empleados()->create([
            'nombre' => $request->nombre,
            'no_empleado' => $request->no_empleado,
        ]);

        return back();
    }

    public function destroyEmpleado(Grupo $grupo, EmpleadoGrupo $empleado): RedirectResponse
    {
        if ($empleado->grupo_id !== $grupo->id) {
            abort(404);
        }

        $empleado->delete();

        return back();
    }
}
