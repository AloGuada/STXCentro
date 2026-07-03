<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\PermisoStoreRequest;
use App\Http\Requests\Admin\Costos\PermisoUpdateRequest;
use App\Models\Costos\AprobacionDepartamento;
use App\Models\Costos\OmitirRubro;
use App\Models\Costos\Permiso;
use App\Models\Costos\Rubro;
use App\Models\Departamento;
use App\Models\Usuario;
use App\Support\OrdenaColumnas;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Permission;

class PermisoController extends Controller
{
    use OrdenaColumnas;

    public function index(Request $request): Response
    {
        $query = Permiso::query()
            ->when($request->search, function ($query, $search) {
                $query->where('descripcion', 'like', "%{$search}%");
            });

        $orden = $this->aplicarOrden($query, $request, [
            'descripcion' => 'descripcion',
            'nivel' => 'nivel',
            'tipo_aprobacion' => 'tipo_aprobacion',
        ], 'nivel');

        $permisos = $query->paginate(15)->withQueryString();

        return Inertia::render('admin/costos/permisos/index', [
            'permisos' => $permisos,
            'filters' => $request->only('search'),
            'sortBy' => $orden['by'],
            'sortDir' => $orden['dir'],
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

        // Candidatos: usuarios con el permiso de aprobar correspondiente al
        // tipo del permiso (vía rol o asignación directa). Si el permiso no
        // existe aún en la BD, devuelve lista vacía sin reventar.
        $permissionName = match ($permiso->tipo_aprobacion) {
            'requisicion' => 'costos.requisiciones.aprobar',
            'solicitud_pago' => 'costos.solicitudes-pago.aprobar',
            default => null,
        };

        $usuarios = $permissionName && Permission::where('name', $permissionName)->exists()
            ? Usuario::permission($permissionName)->orderBy('name')->get(['id', 'name'])
            : collect();

        $porDepto = AprobacionDepartamento::where('permiso_id', $permiso->id)
            ->get()
            ->groupBy('departamento_id');

        $asignaciones = $porDepto->map(fn ($group) => $group->pluck('aprobador_id')->filter()->values());
        $omitir = $porDepto->map(fn ($group) => (bool) $group->first()->omitir_si_presupuesto_reservado);

        $rubrosPermitidos = OmitirRubro::where('permiso_id', $permiso->id)
            ->get()
            ->groupBy('departamento_id')
            ->map(fn ($group) => $group->pluck('rubro_id')->map(fn ($id) => (int) $id)->values());

        return Inertia::render('admin/costos/permisos/show', [
            'permiso' => $permiso,
            'departamentos' => $departamentos,
            'usuarios' => $usuarios,
            'asignaciones' => $asignaciones,
            'omitir' => $omitir,
            'rubros' => Rubro::orderBy('codigo')->get(['id', 'codigo', 'descripcion']),
            'rubrosPermitidos' => $rubrosPermitidos,
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
            'asignaciones.*.omitir_si_presupuesto_reservado' => ['boolean'],
            'asignaciones.*.rubro_ids' => ['nullable', 'array'],
            'asignaciones.*.rubro_ids.*' => ['exists:costos_rubros,id'],
        ]);

        AprobacionDepartamento::where('permiso_id', $permiso->id)->delete();
        OmitirRubro::where('permiso_id', $permiso->id)->delete();

        foreach ($request->input('asignaciones', []) as $asignacion) {
            $omitir = (bool) ($asignacion['omitir_si_presupuesto_reservado'] ?? false);
            foreach ($asignacion['aprobador_ids'] ?? [] as $aprobadorId) {
                AprobacionDepartamento::create([
                    'departamento_id' => $asignacion['departamento_id'],
                    'permiso_id' => $permiso->id,
                    'aprobador_id' => $aprobadorId,
                    'omitir_si_presupuesto_reservado' => $omitir,
                ]);
            }

            // Centros de costo permitidos para el salto (solo si está activo).
            if ($omitir) {
                foreach (array_unique($asignacion['rubro_ids'] ?? []) as $rubroId) {
                    OmitirRubro::create([
                        'departamento_id' => $asignacion['departamento_id'],
                        'permiso_id' => $permiso->id,
                        'rubro_id' => $rubroId,
                    ]);
                }
            }
        }

        return back()->with('success', 'Asignaciones actualizadas correctamente.');
    }
}
