<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\GrupoTrabajoStoreRequest;
use App\Http\Requests\Admin\Prod\GrupoTrabajoUpdateRequest;
use App\Models\Prod\CategoriaEmpleado;
use App\Models\Prod\GrupoEmpleado;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Ubicacion;
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
            ->with('ubicaciones:id,nombre')
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
        return Inertia::render('admin/prod/grupos-trabajo/create', $this->catalogos());
    }

    /**
     * Catálogos que alimentan el formulario: ubicaciones donde trabaja el grupo
     * y categorías con las que se reparte el excedente.
     *
     * @return array<string, mixed>
     */
    private function catalogos(): array
    {
        return [
            'ubicaciones' => Ubicacion::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'categorias' => CategoriaEmpleado::where('activo', true)
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'valor']),
        ];
    }

    public function store(GrupoTrabajoStoreRequest $request): RedirectResponse
    {
        return DB::transaction(function () use ($request) {
            $grupo = GrupoTrabajo::create([
                'descripcion' => $request->descripcion,
                'activo' => $request->boolean('activo', true),
            ]);

            $grupo->ubicaciones()->sync($request->ubicacion_ids ?? []);

            if ($request->filled('empleados')) {
                foreach ($request->empleados as $empData) {
                    $grupo->empleados()->create([
                        'nombre' => $empData['nombre'],
                        'no_empleado' => $empData['no_empleado'] ?? null,
                        'categoria_empleado_id' => $empData['categoria_empleado_id'] ?? null,
                    ]);
                }
            }

            return to_route('admin.prod.grupos-trabajo.edit', $grupo);
        });
    }

    public function edit(GrupoTrabajo $grupoTrabajo): Response
    {
        $grupoTrabajo->load(['empleados.categoria', 'ubicaciones']);

        return Inertia::render('admin/prod/grupos-trabajo/edit', [
            'grupo' => $grupoTrabajo,
            ...$this->catalogos(),
        ]);
    }

    public function update(GrupoTrabajoUpdateRequest $request, GrupoTrabajo $grupoTrabajo): RedirectResponse
    {
        $grupoTrabajo->update([
            'descripcion' => $request->descripcion,
            'activo' => $request->boolean('activo', $grupoTrabajo->activo),
        ]);

        if ($request->has('ubicacion_ids')) {
            $grupoTrabajo->ubicaciones()->sync($request->ubicacion_ids ?? []);
        }

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
            'categoria_empleado_id' => ['nullable', 'exists:prod_categorias_empleado,id'],
        ]);

        $grupoTrabajo->empleados()->create([
            'nombre' => $request->nombre,
            'no_empleado' => $request->no_empleado,
            'categoria_empleado_id' => $request->categoria_empleado_id,
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
