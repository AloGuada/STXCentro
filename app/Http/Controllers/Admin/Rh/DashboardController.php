<?php

namespace App\Http\Controllers\Admin\Rh;

use App\Http\Controllers\Controller;
use App\Models\Departamento;
use App\Models\Rh\Puesto;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        $this->authorize('rh.puestos.ver');

        $puestos = Puesto::query()
            ->with('departamento:id,descripcion')
            ->with(['periodosLaborales' => fn ($q) => $q->where('estado', 'activo')->with('persona:id,nombre,apellido')])
            ->withCount(['periodosLaborales as empleados_activos' => fn ($q) => $q->where('estado', 'activo')])
            ->get();

        $tree = $this->buildTree($puestos);

        $totalEmpleados = $puestos->sum('empleados_activos');
        $puestosVacantes = $puestos->where('empleados_activos', 0)->count();
        $departamentosIds = $puestos->pluck('departamento_id')->unique()->filter();

        $kpis = [
            'total_puestos' => $puestos->count(),
            'total_empleados' => $totalEmpleados,
            'puestos_vacantes' => $puestosVacantes,
            'total_departamentos' => $departamentosIds->count(),
        ];

        $departamentos = Departamento::query()
            ->whereIn('id', $departamentosIds)
            ->select('id', 'descripcion')
            ->orderBy('descripcion')
            ->get();

        return Inertia::render('admin/rh/dashboard/index', [
            'tree' => $tree,
            'kpis' => $kpis,
            'departamentos' => $departamentos,
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(Collection $puestos): array
    {
        $grouped = $puestos->groupBy(fn ($p) => $p->puesto_jefe_id ?? 0);

        return $this->buildChildren($grouped, 0);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function buildChildren(Collection $grouped, int $parentId): array
    {
        $children = $grouped->get($parentId, collect());

        return $children->map(function (Puesto $puesto) use ($grouped) {
            return [
                'id' => $puesto->id,
                'nombre' => $puesto->nombre,
                'codigo' => $puesto->codigo,
                'departamento' => $puesto->departamento?->descripcion ?? 'Sin departamento',
                'departamento_id' => $puesto->departamento_id,
                'empleados_activos' => $puesto->empleados_activos,
                'empleados' => $puesto->periodosLaborales->map(fn ($pl) => [
                    'nombre' => $pl->persona->nombre,
                    'apellido' => $pl->persona->apellido,
                ])->values()->all(),
                'children' => $this->buildChildren($grouped, $puesto->id),
            ];
        })->values()->all();
    }
}
