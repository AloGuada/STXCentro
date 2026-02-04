<?php

namespace App\Http\Controllers\Admin\Intra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Intra\SeccionEstaticaStoreRequest;
use App\Http\Requests\Admin\Intra\SeccionEstaticaUpdateRequest;
use App\Models\Intra\SeccionEstatica;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SeccionEstaticaController extends Controller
{
    public function index(Request $request): Response
    {
        $secciones = SeccionEstatica::query()
            ->with('media')
            ->when($request->search, fn ($q, $s) => $q->where('titulo', 'like', "%{$s}%")
                ->orWhere('slug', 'like', "%{$s}%"))
            ->orderBy('titulo')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/intra/secciones/index', [
            'secciones' => $secciones,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/intra/secciones/create');
    }

    public function store(SeccionEstaticaStoreRequest $request): RedirectResponse
    {
        $slug = $this->generateUniqueSlug($request->titulo);

        $seccion = SeccionEstatica::create([
            'slug' => $slug,
            'titulo' => $request->titulo,
            'descripcion' => $request->descripcion,
            'boton' => $request->boton,
            'order' => 0,
            'activo' => $request->boolean('activo', true),
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $path = $file->store('intra/secciones', 'public');

            $seccion->media()->create([
                'descripcion' => $request->titulo,
                'path' => $path,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        return to_route('admin.intra.secciones.index');
    }

    public function edit(SeccionEstatica $seccione): Response
    {
        return Inertia::render('admin/intra/secciones/edit', [
            'seccion' => $seccione->load('media'),
        ]);
    }

    public function update(SeccionEstaticaUpdateRequest $request, SeccionEstatica $seccione): RedirectResponse
    {
        // Generate new slug if title changed
        $slug = $seccione->slug;
        if ($request->titulo !== $seccione->titulo) {
            $slug = $this->generateUniqueSlug($request->titulo, $seccione->id);
        }

        $seccione->update([
            'slug' => $slug,
            'titulo' => $request->titulo,
            'descripcion' => $request->descripcion,
            'boton' => $request->boton,
            'activo' => $request->boolean('activo', true),
        ]);

        if ($request->hasFile('file')) {
            // Delete old media if exists
            if ($seccione->media) {
                Storage::disk('public')->delete($seccione->media->path);
                $seccione->media->delete();
            }

            $file = $request->file('file');
            $path = $file->store('intra/secciones', 'public');

            $seccione->media()->create([
                'descripcion' => $request->titulo,
                'path' => $path,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        return to_route('admin.intra.secciones.index');
    }

    public function destroy(SeccionEstatica $seccione): RedirectResponse
    {
        if ($seccione->media) {
            Storage::disk('public')->delete($seccione->media->path);
            $seccione->media->delete();
        }

        $seccione->delete();

        return to_route('admin.intra.secciones.index');
    }

    /**
     * Generate a unique slug from the title.
     */
    private function generateUniqueSlug(string $titulo, ?int $excludeId = null): string
    {
        $slug = Str::slug($titulo);
        $originalSlug = $slug;
        $counter = 1;

        while (SeccionEstatica::query()
            ->where('slug', $slug)
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->exists()
        ) {
            $slug = "{$originalSlug}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
