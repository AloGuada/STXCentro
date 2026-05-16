<?php

namespace App\Http\Controllers\Admin\Rh;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Rh\PersonaStoreRequest;
use App\Http\Requests\Admin\Rh\PersonaUpdateRequest;
use App\Models\Rh\Persona;
use App\Models\Rh\PersonaDocumento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class PersonaController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('rh.personas.ver');

        $sortable = ['nombre', 'apellido', 'email', 'telefono', 'fecha_nacimiento', 'created_at'];
        $sortBy = in_array($request->sort_by, $sortable) ? $request->sort_by : 'created_at';
        $sortDir = $request->sort_dir === 'desc' ? 'desc' : ($request->sort_by ? 'asc' : 'desc');

        $personas = Persona::query()
            ->with(['periodosLaborales.puesto.departamento', 'periodosLaborales.requisicion', 'foto', 'documentos.media'])
            ->when($request->search, function ($query, $search) {
                $search = mb_strtolower($search);
                $query->where(function ($q) use ($search) {
                    $q->whereRaw('LOWER(nombre) like ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(apellido) like ?', ["%{$search}%"])
                        ->orWhereRaw('LOWER(email) like ?', ["%{$search}%"])
                        ->orWhere('telefono', 'like', "%{$search}%")
                        ->orWhereRaw('LOWER(localidad) like ?', ["%{$search}%"])
                        ->orWhereHas('periodosLaborales', function ($q) use ($search) {
                            $q->where('numero_empleado', 'like', "%{$search}%")
                                ->orWhereHas('puesto', function ($q) use ($search) {
                                    $q->whereRaw('LOWER(nombre) like ?', ["%{$search}%"])
                                        ->orWhereHas('departamento', function ($q) use ($search) {
                                            $q->whereRaw('LOWER(descripcion) like ?', ["%{$search}%"]);
                                        });
                                });
                        });
                });
            })
            ->when($request->estado === 'activo', function ($query) {
                $query->whereHas('periodosLaborales', fn ($q) => $q->where('estado', 'activo'));
            })
            ->when($request->estado === 'inactivo', function ($query) {
                $query->whereDoesntHave('periodosLaborales', fn ($q) => $q->where('estado', 'activo'));
            })
            ->orderBy($sortBy, $sortDir)
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/rh/personas/index', [
            'personas' => $personas,
            'filters' => $request->only('search', 'sort_by', 'sort_dir', 'estado'),
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

        $uploadedPaths = [];

        try {
            DB::transaction(function () use ($request, &$uploadedPaths) {
                $persona = Persona::create($request->safe()->except(['cv', 'foto']));

                if ($request->hasFile('cv')) {
                    $file = $request->file('cv');
                    $path = $file->store('rh/cv/'.$persona->id, 'public');
                    $uploadedPaths[] = $path;
                    $persona->media()->create([
                        'descripcion' => 'cv',
                        'nombre_original' => $file->getClientOriginalName(),
                        'path' => $path,
                        'mime' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ]);
                    $persona->update(['cv_estado' => 'pendiente']);
                }

                if ($request->hasFile('foto')) {
                    $file = $request->file('foto');
                    $path = $file->store('rh/fotos/'.$persona->id, 'public');
                    $uploadedPaths[] = $path;
                    $persona->foto()->create([
                        'descripcion' => 'foto',
                        'nombre_original' => $file->getClientOriginalName(),
                        'path' => $path,
                        'mime' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            foreach ($uploadedPaths as $path) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }

        return to_route('admin.rh.personas.index');
    }

    public function show(Persona $persona): Response
    {
        $this->authorize('rh.personas.ver');

        $persona->load(['documentos.media', 'periodosLaborales.puesto', 'candidaturas.requisicion', 'media', 'foto']);

        return Inertia::render('admin/rh/personas/show', [
            'persona' => $persona,
        ]);
    }

    public function edit(Persona $persona): Response
    {
        $this->authorize('rh.personas.editar');

        $persona->load(['documentos.media', 'media', 'foto', 'periodosLaborales.puesto.departamento']);

        return Inertia::render('admin/rh/personas/edit', [
            'persona' => $persona,
        ]);
    }

    public function update(PersonaUpdateRequest $request, Persona $persona): RedirectResponse
    {
        $this->authorize('rh.personas.editar');

        $uploadedPaths = [];
        $oldPathsToDelete = [];

        try {
            DB::transaction(function () use ($request, $persona, &$uploadedPaths, &$oldPathsToDelete) {
                $persona->update($request->safe()->except(['cv', 'foto']));

                if ($request->hasFile('cv')) {
                    $file = $request->file('cv');
                    $path = $file->store('rh/cv/'.$persona->id, 'public');
                    $uploadedPaths[] = $path;

                    if ($persona->media) {
                        $oldPathsToDelete[] = $persona->media->path;
                        $persona->media->delete();
                    }

                    $persona->media()->create([
                        'descripcion' => 'cv',
                        'nombre_original' => $file->getClientOriginalName(),
                        'path' => $path,
                        'mime' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ]);
                    $persona->update(['cv_estado' => 'pendiente']);
                }

                if ($request->hasFile('foto')) {
                    $file = $request->file('foto');
                    $path = $file->store('rh/fotos/'.$persona->id, 'public');
                    $uploadedPaths[] = $path;

                    if ($persona->foto) {
                        $oldPathsToDelete[] = $persona->foto->path;
                        $persona->foto->delete();
                    }

                    $persona->foto()->create([
                        'descripcion' => 'foto',
                        'nombre_original' => $file->getClientOriginalName(),
                        'path' => $path,
                        'mime' => $file->getMimeType(),
                        'size' => $file->getSize(),
                    ]);
                }
            });
        } catch (\Throwable $e) {
            foreach ($uploadedPaths as $path) {
                Storage::disk('public')->delete($path);
            }
            throw $e;
        }

        foreach ($oldPathsToDelete as $path) {
            Storage::disk('public')->delete($path);
        }

        return back();
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

        try {
            DB::transaction(function () use ($request, $persona, $file, $path) {
                $media = \App\Models\Media::create([
                    'descripcion' => 'persona_documento',
                    'nombre_original' => $file->getClientOriginalName(),
                    'path' => $path,
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);

                $persona->documentos()->create([
                    'media_id' => $media->id,
                    'tipo_documento' => $request->tipo_documento,
                    'fecha_emision' => $request->fecha_emision,
                    'fecha_vigencia' => $request->fecha_vigencia,
                    'notas' => $request->notas,
                ]);
            });
        } catch (\Throwable $e) {
            Storage::disk('public')->delete($path);
            throw $e;
        }

        return back();
    }

    public function destroyDocumento(Persona $persona, PersonaDocumento $documento): RedirectResponse
    {
        $this->authorize('rh.personas.editar');

        $media = $documento->media;
        $documento->delete();

        if ($media) {
            Storage::disk('public')->delete($media->path);
            $media->delete();
        }

        return back();
    }
}
