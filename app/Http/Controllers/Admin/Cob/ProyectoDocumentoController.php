<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\DocumentoArchivoStoreRequest;
use App\Http\Requests\Admin\Cob\DocumentoCarpetaRequest;
use App\Http\Requests\Admin\Cob\SeccionEstatusRequest;
use App\Http\Requests\Admin\Cob\SeccionVisibilidadRequest;
use App\Models\Cob\DocumentoArchivo;
use App\Models\Cob\DocumentoCarpeta;
use App\Models\Cob\DocumentoSeccion;
use App\Models\Proyecto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProyectoDocumentoController extends Controller
{
    public function carpetaStore(DocumentoCarpetaRequest $request, Proyecto $proyecto): RedirectResponse
    {
        $data = $request->validated();

        $proyecto->documentoCarpetas()->create([
            'seccion_id' => $data['seccion_id'],
            'parent_id' => $data['parent_id'] ?? null,
            'nombre' => $data['nombre'],
        ]);

        return back();
    }

    public function carpetaUpdate(DocumentoCarpetaRequest $request, Proyecto $proyecto, DocumentoCarpeta $carpeta): RedirectResponse
    {
        $carpeta->update(['nombre' => $request->validated()['nombre']]);

        return back();
    }

    public function carpetaDestroy(Proyecto $proyecto, DocumentoCarpeta $carpeta): RedirectResponse
    {
        $carpetaIds = $this->descendientes($carpeta);

        DocumentoArchivo::query()
            ->whereIn('carpeta_id', $carpetaIds)
            ->get()
            ->each(fn (DocumentoArchivo $archivo) => Storage::disk('local')->delete($archivo->path));

        $carpeta->delete();

        return back();
    }

    public function archivoStore(DocumentoArchivoStoreRequest $request, Proyecto $proyecto): RedirectResponse
    {
        $data = $request->validated();

        foreach ($request->file('archivos') as $file) {
            $path = $file->store("cob/documentos/{$proyecto->id}/{$data['seccion_id']}", 'local');

            $proyecto->documentoArchivos()->create([
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

    public function archivoDestroy(Proyecto $proyecto, DocumentoArchivo $archivo): RedirectResponse
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

    /** Marca una sección del expediente como pendiente/completado para este proyecto. */
    public function seccionEstatus(SeccionEstatusRequest $request, Proyecto $proyecto, DocumentoSeccion $documentoSeccion): RedirectResponse
    {
        $proyecto->seccionEstatus()->updateOrCreate(
            ['seccion_id' => $documentoSeccion->id],
            ['estatus' => $request->validated('estatus')],
        );

        return back();
    }

    /** Muestra u oculta una sección del expediente para este proyecto (no afecta otros). */
    public function seccionVisibilidad(SeccionVisibilidadRequest $request, Proyecto $proyecto, DocumentoSeccion $documentoSeccion): RedirectResponse
    {
        $proyecto->seccionEstatus()->updateOrCreate(
            ['seccion_id' => $documentoSeccion->id],
            ['visible' => $request->validated('visible')],
        );

        return back();
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
