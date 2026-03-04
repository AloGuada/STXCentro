<?php

namespace App\Http\Controllers\Admin\Rh;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Rh\PersonaStoreRequest;
use App\Http\Requests\Admin\Rh\PersonaUpdateRequest;
use App\Models\Rh\Persona;
use App\Models\Rh\PersonaDocumento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PersonaController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('rh.personas.ver');

        $personas = Persona::query()
            ->when($request->search, fn ($q, $s) => $q
                ->where('nombre', 'like', "%{$s}%")
                ->orWhere('apellido', 'like', "%{$s}%")
                ->orWhere('email', 'like', "%{$s}%")
            )
            ->orderBy('apellido')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/rh/personas/index', [
            'personas' => $personas,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('rh.personas.crear');

        return Inertia::render('admin/rh/personas/create');
    }

    public function store(PersonaStoreRequest $request): RedirectResponse
    {
        $this->authorize('rh.personas.crear');

        $persona = Persona::create($request->safe()->except('cv'));

        if ($request->hasFile('cv')) {
            $path = $request->file('cv')->store('rh/cv/'.$persona->id, 'public');
            $persona->update([
                'cv_ruta' => $path,
                'cv_estado' => 'pendiente',
            ]);
        }

        return to_route('admin.rh.personas.index');
    }

    public function show(Persona $persona): Response
    {
        $this->authorize('rh.personas.ver');

        $persona->load(['datosExtra', 'documentos', 'periodosLaborales.puesto', 'candidaturas.requisicion']);

        return Inertia::render('admin/rh/personas/show', [
            'persona' => $persona,
        ]);
    }

    public function edit(Persona $persona): Response
    {
        $this->authorize('rh.personas.editar');

        $persona->load(['datosExtra', 'documentos']);

        return Inertia::render('admin/rh/personas/edit', [
            'persona' => $persona,
        ]);
    }

    public function update(PersonaUpdateRequest $request, Persona $persona): RedirectResponse
    {
        $this->authorize('rh.personas.editar');

        $persona->update($request->safe()->except('cv'));

        if ($request->hasFile('cv')) {
            if ($persona->cv_ruta) {
                Storage::disk('public')->delete($persona->cv_ruta);
            }

            $path = $request->file('cv')->store('rh/cv/'.$persona->id, 'public');
            $persona->update([
                'cv_ruta' => $path,
                'cv_estado' => 'pendiente',
            ]);
        }

        if ($request->has('datos_extra')) {
            $persona->datosExtra()->updateOrCreate(
                ['persona_id' => $persona->id],
                $request->datos_extra,
            );
        }

        return to_route('admin.rh.personas.index');
    }

    public function destroy(Persona $persona): RedirectResponse
    {
        $this->authorize('rh.personas.eliminar');

        if ($persona->periodosLaborales()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar una persona con periodos laborales.']);
        }

        $persona->delete();

        return to_route('admin.rh.personas.index');
    }

    public function storeDocumento(Request $request, Persona $persona): RedirectResponse
    {
        $this->authorize('rh.personas.editar');

        $request->validate([
            'archivo' => ['required', 'file', 'max:10240'],
            'tipo_documento' => ['required', 'string', 'max:255'],
            'fecha_emision' => ['nullable', 'date'],
            'fecha_vigencia' => ['nullable', 'date'],
            'notas' => ['nullable', 'string'],
        ]);

        $file = $request->file('archivo');
        $path = $file->store('rh/personas/'.$persona->id, 'public');

        $persona->documentos()->create([
            'tipo_documento' => $request->tipo_documento,
            'nombre_archivo' => $file->getClientOriginalName(),
            'ruta_archivo' => $path,
            'extension' => $file->getClientOriginalExtension(),
            'tamano' => $file->getSize(),
            'fecha_emision' => $request->fecha_emision,
            'fecha_vigencia' => $request->fecha_vigencia,
            'notas' => $request->notas,
        ]);

        return back();
    }

    public function destroyDocumento(Persona $persona, PersonaDocumento $documento): RedirectResponse
    {
        $this->authorize('rh.personas.editar');

        Storage::disk('public')->delete($documento->ruta_archivo);
        $documento->delete();

        return back();
    }
}
