<?php

namespace App\Http\Controllers\Admin\Rh;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Rh\PuestoStoreRequest;
use App\Http\Requests\Admin\Rh\PuestoUpdateRequest;
use App\Models\Departamento;
use App\Models\Rh\Actividad;
use App\Models\Rh\DocumentoPuesto;
use App\Models\Rh\OnboardingTareaPlantilla;
use App\Models\Rh\Puesto;
use App\Models\Rh\Requerimiento;
use App\Models\Rh\Skill;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PuestoController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('rh.puestos.ver');

        $puestos = Puesto::query()
            ->with('departamento')
            ->when($request->search, fn ($q, $s) => $q->where('nombre', 'like', "%{$s}%"))
            ->orderBy('nombre')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/rh/puestos/index', [
            'puestos' => $puestos,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('rh.puestos.crear');

        return Inertia::render('admin/rh/puestos/create', [
            'departamentos' => Departamento::orderBy('descripcion')->get(),
            'puestos' => Puesto::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(PuestoStoreRequest $request): RedirectResponse
    {
        $this->authorize('rh.puestos.crear');

        Puesto::create($request->validated());

        return to_route('admin.rh.puestos.index');
    }

    public function edit(Puesto $puesto): Response
    {
        $this->authorize('rh.puestos.editar');

        $puesto->load(['departamento', 'skills', 'requerimientos', 'actividades', 'documentosPuesto', 'plantillasOnboarding' => fn ($q) => $q->orderBy('orden')->orderBy('id')]);

        return Inertia::render('admin/rh/puestos/edit', [
            'puesto' => $puesto,
            'departamentos' => Departamento::orderBy('descripcion')->get(),
            'puestosJefe' => Puesto::where('id', '!=', $puesto->id)->orderBy('nombre')->get(['id', 'nombre']),
            'allSkills' => Skill::orderBy('nombre')->get(),
            'allRequerimientos' => Requerimiento::orderBy('descripcion')->get(),
        ]);
    }

    public function update(PuestoUpdateRequest $request, Puesto $puesto): RedirectResponse
    {
        $this->authorize('rh.puestos.editar');

        $puesto->update($request->validated());

        return to_route('admin.rh.puestos.index');
    }

    public function destroy(Puesto $puesto): RedirectResponse
    {
        $this->authorize('rh.puestos.eliminar');

        if ($puesto->periodosLaborales()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar un puesto con periodos laborales asociados.']);
        }

        $puesto->delete();

        return to_route('admin.rh.puestos.index');
    }

    public function addSkill(Request $request, Puesto $puesto): RedirectResponse
    {
        $this->authorize('rh.puestos.editar');

        $request->validate([
            'skill_id' => ['nullable', 'exists:rh_skills,id'],
            'nombre' => ['required_without:skill_id', 'nullable', 'string', 'max:255'],
            'tipo' => ['nullable', 'in:hard,soft'],
            'nivel_requerido' => ['required', 'in:basico,intermedio,avanzado'],
        ]);

        if ($request->skill_id) {
            $skill = Skill::findOrFail($request->skill_id);
        } else {
            $skill = Skill::firstOrCreate(
                ['nombre' => $request->nombre],
                ['tipo' => $request->tipo ?? 'hard'],
            );
        }

        if (! $puesto->skills()->where('skill_id', $skill->id)->exists()) {
            $puesto->skills()->attach($skill->id, ['nivel_requerido' => $request->nivel_requerido]);
        }

        return back();
    }

    public function removeSkill(Puesto $puesto, Skill $skill): RedirectResponse
    {
        $this->authorize('rh.puestos.editar');

        $puesto->skills()->detach($skill->id);

        return back();
    }

    public function addRequerimiento(Request $request, Puesto $puesto): RedirectResponse
    {
        $this->authorize('rh.puestos.editar');

        $request->validate([
            'requerimiento_id' => ['nullable', 'exists:rh_requerimientos,id'],
            'descripcion' => ['required_without:requerimiento_id', 'nullable', 'string', 'max:255'],
            'valor' => ['nullable', 'string', 'max:255'],
        ]);

        if ($request->requerimiento_id) {
            $requerimiento = Requerimiento::findOrFail($request->requerimiento_id);
            if ($request->filled('valor')) {
                $requerimiento->update(['valor' => $request->valor]);
            }
        } else {
            $requerimiento = Requerimiento::firstOrCreate(
                ['descripcion' => $request->descripcion],
                ['valor' => $request->valor],
            );
        }

        if (! $puesto->requerimientos()->where('requerimiento_id', $requerimiento->id)->exists()) {
            $puesto->requerimientos()->attach($requerimiento->id);
        }

        return back();
    }

    public function removeRequerimiento(Puesto $puesto, Requerimiento $requerimiento): RedirectResponse
    {
        $this->authorize('rh.puestos.editar');

        $puesto->requerimientos()->detach($requerimiento->id);

        return back();
    }

    public function storeActividad(Request $request, Puesto $puesto): RedirectResponse
    {
        $this->authorize('rh.puestos.editar');

        $request->validate(['descripcion' => ['required', 'string']]);

        $puesto->actividades()->create(['descripcion' => $request->descripcion]);

        return back();
    }

    public function destroyActividad(Puesto $puesto, Actividad $actividad): RedirectResponse
    {
        $this->authorize('rh.puestos.editar');

        $actividad->delete();

        return back();
    }

    public function storeDocumentoPuesto(Request $request, Puesto $puesto): RedirectResponse
    {
        $this->authorize('rh.puestos.editar');

        $request->validate([
            'nombre_reporte' => ['required', 'string', 'max:255'],
            'frecuencia_entrega' => ['nullable', 'string', 'max:255'],
            'cargo_entrega' => ['nullable', 'string', 'max:255'],
        ]);

        $puesto->documentosPuesto()->create($request->only('nombre_reporte', 'frecuencia_entrega', 'cargo_entrega'));

        return back();
    }

    public function destroyDocumentoPuesto(Puesto $puesto, DocumentoPuesto $documentoPuesto): RedirectResponse
    {
        $this->authorize('rh.puestos.editar');

        $documentoPuesto->delete();

        return back();
    }

    public function storePlantillaOnboarding(Request $request, Puesto $puesto): RedirectResponse
    {
        $this->authorize('rh.puestos.editar');

        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'dias_desde_inicio' => ['nullable', 'integer', 'min:0'],
            'orden' => ['nullable', 'integer', 'min:0'],
        ]);

        $puesto->plantillasOnboarding()->create($data);

        return back();
    }

    public function updatePlantillaOnboarding(Request $request, Puesto $puesto, OnboardingTareaPlantilla $plantilla): RedirectResponse
    {
        $this->authorize('rh.puestos.editar');

        if ($plantilla->puesto_id !== $puesto->id) {
            abort(404);
        }

        $data = $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'dias_desde_inicio' => ['nullable', 'integer', 'min:0'],
            'orden' => ['nullable', 'integer', 'min:0'],
        ]);

        $plantilla->update($data);

        return back();
    }

    public function destroyPlantillaOnboarding(Puesto $puesto, OnboardingTareaPlantilla $plantilla): RedirectResponse
    {
        $this->authorize('rh.puestos.editar');

        if ($plantilla->puesto_id !== $puesto->id) {
            abort(404);
        }

        $plantilla->delete();

        return back();
    }
}
