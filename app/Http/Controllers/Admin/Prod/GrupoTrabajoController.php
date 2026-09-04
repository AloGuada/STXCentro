<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\GrupoEmpleadoStoreRequest;
use App\Http\Requests\Admin\Prod\GrupoEmpleadoUpdateRequest;
use App\Http\Requests\Admin\Prod\GrupoTrabajoStoreRequest;
use App\Http\Requests\Admin\Prod\GrupoTrabajoUpdateRequest;
use App\Models\Prod\CategoriaEmpleado;
use App\Models\Prod\GrupoEmpleado;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Ubicacion;
use App\Models\Rh\Persona;
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
     * Catálogos que alimentan el formulario: ubicaciones donde trabaja el grupo,
     * categorías con las que se reparte el excedente y el padrón de personas de
     * RH del que salen los integrantes.
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
            'personas' => $this->personasDisponibles(),
        ];
    }

    /**
     * Padrón para el buscador del alta. Incluye a todas las personas de RH (no
     * sólo las contratadas) para que no se dupliquen dando de alta a alguien que
     * ya existe como prospecto, pero deja fuera a quien ya está en un grupo:
     * nadie puede pertenecer a dos a la vez.
     *
     * @return list<array{id: int, nombre: string, no_empleado: string|null, contratado: bool}>
     */
    private function personasDisponibles(): array
    {
        return Persona::query()
            ->whereDoesntHave('grupoDeProduccion')
            ->with('periodoVigente:id,persona_id,numero_empleado')
            ->orderBy('nombre')
            ->orderBy('apellido')
            ->get(['id', 'nombre', 'apellido'])
            ->map(fn (Persona $persona) => [
                'id' => $persona->id,
                'nombre' => $persona->nombre_completo,
                'no_empleado' => $persona->periodoVigente?->numero_empleado,
                'contratado' => $persona->periodoVigente !== null,
            ])
            ->all();
    }

    /**
     * Traduce un renglón del formulario a los datos del integrante: resuelve la
     * persona (elegida o creada al vuelo) y copia de ella el nombre y el número
     * de empleado que se muestran en producción.
     *
     * @param  array<string, mixed>  $datos
     * @return array<string, mixed>
     */
    private function datosDelEmpleado(array $datos): array
    {
        $persona = $this->personaDeLaSeleccion($datos);

        return [
            'persona_id' => $persona->id,
            'nombre' => $persona->nombre_completo,
            'no_empleado' => $persona->periodoVigente?->numero_empleado,
            'categoria_empleado_id' => $datos['categoria_empleado_id'] ?? null,
        ];
    }

    /**
     * La persona que eligio el formulario: una ya existente en RH o una nueva
     * que se da de alta ahi mismo con lo minimo (nombre y apellido).
     *
     * @param  array<string, mixed>  $datos
     */
    private function personaDeLaSeleccion(array $datos): Persona
    {
        return isset($datos['persona_id'])
            ? Persona::findOrFail($datos['persona_id'])
            : Persona::create([
                'nombre' => $datos['persona_nueva']['nombre'],
                'apellido' => $datos['persona_nueva']['apellido'],
            ]);
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
                    $grupo->empleados()->create($this->datosDelEmpleado($empData));
                }
            }

            return to_route('admin.prod.grupos-trabajo.edit', $grupo);
        });
    }

    public function edit(GrupoTrabajo $grupoTrabajo): Response
    {
        $grupoTrabajo->load(['empleados.categoria', 'empleados.persona.periodoVigente', 'ubicaciones']);

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

    public function storeEmpleado(GrupoEmpleadoStoreRequest $request, GrupoTrabajo $grupoTrabajo): RedirectResponse
    {
        DB::transaction(function () use ($request, $grupoTrabajo) {
            $grupoTrabajo->empleados()->create($this->datosDelEmpleado($request->validated()));
        });

        return back();
    }

    /**
     * Edita a un integrante sin sacarlo del grupo: le cambia la categoría con la
     * que participa en el reparto y, si es un renglón viejo sin enlace, le amarra
     * su persona de RH.
     *
     * El nombre y el número de empleado son copia de RH, así que se vuelven a
     * traer en cada edición: si RH lo contrató después de darlo de alta aquí, su
     * número aparecía vacío para siempre.
     */
    public function updateEmpleado(GrupoEmpleadoUpdateRequest $request, GrupoTrabajo $grupoTrabajo, GrupoEmpleado $empleado): RedirectResponse
    {
        if ($empleado->grupo_trabajo_id !== $grupoTrabajo->id) {
            abort(404);
        }

        DB::transaction(function () use ($request, $empleado) {
            // La pantalla edita un dato a la vez: lo que no venga en la peticion
            // se queda como esta, que mandarlo en blanco lo borraria.
            $datos = [
                'categoria_empleado_id' => $request->has('categoria_empleado_id')
                    ? $request->categoria_empleado_id
                    : $empleado->categoria_empleado_id,
            ];

            $persona = $request->filled('persona_id') || $request->filled('persona_nueva')
                ? $this->personaDeLaSeleccion($request->validated())
                : $empleado->persona;

            if ($persona !== null) {
                $datos['persona_id'] = $persona->id;
                $datos['nombre'] = $persona->nombre_completo;
                $datos['no_empleado'] = $persona->periodoVigente?->numero_empleado;
            }

            $empleado->update($datos);
        });

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
