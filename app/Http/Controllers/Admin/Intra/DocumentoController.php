<?php

namespace App\Http\Controllers\Admin\Intra;

use App\Enums\TipoDocumento;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Intra\DocumentoStoreRequest;
use App\Http\Requests\Admin\Intra\DocumentoUpdateRequest;
use App\Models\Intra\Area;
use App\Models\Intra\Documento;
use App\Models\Media;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class DocumentoController extends Controller
{
    public function index(Request $request): Response
    {
        $documentos = Documento::query()
            ->with(['area', 'media'])
            ->when($request->search, fn ($q, $s) => $q->where('descripcion', 'like', "%{$s}%")
                ->orWhere('codigo', 'like', "%{$s}%"))
            ->when($request->area_id, fn ($q, $id) => $q->where('area_id', $id))
            ->when($request->tipo, fn ($q, $t) => $q->where('tipo', $t))
            ->orderBy('order')
            ->orderBy('descripcion')
            ->paginate(15)
            ->withQueryString();

        $areas = Area::query()
            ->where('activo', true)
            ->orderBy('descripcion')
            ->get(['id', 'descripcion']);

        return Inertia::render('admin/intra/documentos/index', [
            'documentos' => $documentos,
            'filters' => $request->only('search', 'area_id', 'tipo'),
            'areas' => $areas,
            'tipos' => TipoDocumento::optionsWithNumbers(),
        ]);
    }

    public function create(): Response
    {
        $areas = Area::query()
            ->where('activo', true)
            ->orderBy('descripcion')
            ->get(['id', 'descripcion']);

        return Inertia::render('admin/intra/documentos/create', [
            'areas' => $areas,
            'tipos' => TipoDocumento::optionsWithNumbers(),
        ]);
    }

    public function store(DocumentoStoreRequest $request): RedirectResponse
    {
        $file = $request->file('file');
        $path = $file->store('intra/documentos', 'public');

        $media = Media::create([
            'descripcion' => $request->descripcion,
            'path' => $path,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        // Get order from tipo enum
        $tipo = TipoDocumento::from($request->tipo);

        Documento::create([
            'area_id' => $request->area_id,
            'media_id' => $media->id,
            'descripcion' => $request->descripcion,
            'codigo' => $request->codigo,
            'tipo' => $request->tipo,
            'order' => $tipo->order(),
            'activo' => $request->boolean('activo', true),
        ]);

        return to_route('admin.intra.documentos.index');
    }

    public function edit(Documento $documento): Response
    {
        $areas = Area::query()
            ->where('activo', true)
            ->orderBy('descripcion')
            ->get(['id', 'descripcion']);

        return Inertia::render('admin/intra/documentos/edit', [
            'documento' => $documento->load(['area', 'media']),
            'areas' => $areas,
            'tipos' => TipoDocumento::optionsWithNumbers(),
        ]);
    }

    public function update(DocumentoUpdateRequest $request, Documento $documento): RedirectResponse
    {
        // Get order from tipo enum
        $tipo = TipoDocumento::from($request->tipo);

        $documento->update([
            'area_id' => $request->area_id,
            'descripcion' => $request->descripcion,
            'codigo' => $request->codigo,
            'tipo' => $request->tipo,
            'order' => $tipo->order(),
            'activo' => $request->boolean('activo', true),
        ]);

        if ($request->hasFile('file')) {
            // Delete old media file
            Storage::disk('public')->delete($documento->media->path);

            $file = $request->file('file');
            $path = $file->store('intra/documentos', 'public');

            $documento->media->update([
                'descripcion' => $request->descripcion,
                'path' => $path,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
            ]);
        }

        return to_route('admin.intra.documentos.index');
    }

    public function destroy(Documento $documento): RedirectResponse
    {
        Storage::disk('public')->delete($documento->media->path);
        $documento->media->delete();
        $documento->delete();

        return to_route('admin.intra.documentos.index');
    }
}
