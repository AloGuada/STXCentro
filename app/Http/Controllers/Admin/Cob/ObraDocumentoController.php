<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\DocumentoArchivoStoreRequest;
use App\Http\Requests\Admin\Cob\DocumentoCarpetaRequest;
use App\Models\Cob\DocumentoArchivo;
use App\Models\Cob\DocumentoCarpeta;
use App\Models\Obra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ObraDocumentoController extends Controller
{
    public function carpetaStore(DocumentoCarpetaRequest $request, Obra $obra): RedirectResponse
    {
        $data = $request->validated();

        $obra->documentoCarpetas()->create([
            'seccion_id' => $data['seccion_id'],
            'parent_id' => $data['parent_id'] ?? null,
            'nombre' => $data['nombre'],
        ]);

        return back();
    }

    public function carpetaUpdate(DocumentoCarpetaRequest $request, Obra $obra, DocumentoCarpeta $carpeta): RedirectResponse
    {
        $carpeta->update(['nombre' => $request->validated()['nombre']]);

        return back();
    }

    public function carpetaDestroy(Obra $obra, DocumentoCarpeta $carpeta): RedirectResponse
    {
        $carpetaIds = $this->descendientes($carpeta);

        DocumentoArchivo::query()
            ->whereIn('carpeta_id', $carpetaIds)
            ->get()
            ->each(fn (DocumentoArchivo $archivo) => Storage::disk('local')->delete($archivo->path));

        $carpeta->delete();

        return back();
    }

    public function archivoStore(DocumentoArchivoStoreRequest $request, Obra $obra): RedirectResponse
    {
        $data = $request->validated();

        foreach ($request->file('archivos') as $file) {
            $path = $file->store("cob/documentos/{$obra->id}/{$data['seccion_id']}", 'local');

            $obra->documentoArchivos()->create([
                'seccion_id' => $data['seccion_id'],
                'carpeta_id' => $data['carpeta_id'] ?? null,
                'nombre_original' => $file->getClientOriginalName(),
                'path' => $path,
                'mime' => $file->getMimeType(),
                'size' => $file->getSize(),
                'subido_por_id' => $request->user()->getKey(),
            ]);
        }

        return back();
    }

    public function archivoDestroy(Obra $obra, DocumentoArchivo $archivo): RedirectResponse
    {
        Storage::disk('local')->delete($archivo->path);
        $archivo->delete();

        return back();
    }

    public function archivoDownload(DocumentoArchivo $archivo): StreamedResponse
    {
        return Storage::disk('local')->download($archivo->path, $archivo->nombre_original);
    }

    public function archivoStream(DocumentoArchivo $archivo): StreamedResponse
    {
        return Storage::disk('local')->response($archivo->path, $archivo->nombre_original, [
            'Content-Type' => $archivo->mime ?? 'application/octet-stream',
        ]);
    }

    /**
     * IDs de la carpeta y todas sus descendientes (para borrado en cascada de archivos físicos).
     *
     * @return list<int>
     */
    private function descendientes(DocumentoCarpeta $carpeta): array
    {
        $ids = [$carpeta->id];
        $hijos = DocumentoCarpeta::query()->where('parent_id', $carpeta->id)->get();

        foreach ($hijos as $hijo) {
            $ids = array_merge($ids, $this->descendientes($hijo));
        }

        return $ids;
    }
}
