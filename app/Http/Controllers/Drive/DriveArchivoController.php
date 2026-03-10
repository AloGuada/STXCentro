<?php

namespace App\Http\Controllers\Drive;

use App\Http\Controllers\Controller;
use App\Http\Requests\Drive\DriveArchivoStoreRequest;
use App\Models\Drive\Archivo;
use App\Models\Drive\Carpeta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DriveArchivoController extends Controller
{
    public function index(Carpeta $carpeta): Response
    {
        $externo = Auth::guard('externo')->user();

        abort_if(! $externo->carpetas()->where('drive_carpetas.id', $carpeta->id)->exists(), 403);

        $archivos = $carpeta->archivos()
            ->latest()
            ->paginate(20);

        return Inertia::render('drive/carpetas/show', [
            'carpeta' => $carpeta,
            'archivos' => $archivos,
        ]);
    }

    public function store(DriveArchivoStoreRequest $request): RedirectResponse
    {
        $externo = Auth::guard('externo')->user();
        $carpeta = Carpeta::findOrFail($request->carpeta_id);

        abort_if(! $externo->carpetas()->where('drive_carpetas.id', $carpeta->id)->exists(), 403);

        $file = $request->file('archivo');
        $path = $file->store("drive/{$carpeta->id}", 'local');

        Archivo::create([
            'carpeta_id' => $carpeta->id,
            'nombre_original' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'descripcion' => $request->descripcion,
            'subido_por_type' => 'externo',
            'subido_por_id' => $externo->id,
        ]);

        return back()->with('success', 'Archivo subido correctamente.');
    }

    public function show(Archivo $archivo): StreamedResponse
    {
        $externo = Auth::guard('externo')->user();

        abort_if(! $externo->carpetas()->where('drive_carpetas.id', $archivo->carpeta_id)->exists(), 403);

        return Storage::disk('local')->download($archivo->path, $archivo->nombre_original);
    }

    public function destroy(Archivo $archivo): RedirectResponse
    {
        $externo = Auth::guard('externo')->user();

        abort_if(! $externo->carpetas()->where('drive_carpetas.id', $archivo->carpeta_id)->exists(), 403);
        abort_if($archivo->subido_por_type !== 'externo' || $archivo->subido_por_id !== $externo->id, 403);

        Storage::disk('local')->delete($archivo->path);
        $archivo->delete();

        return back()->with('success', 'Archivo eliminado correctamente.');
    }

    public function compartido(string $token): StreamedResponse
    {
        $archivo = Archivo::where('link_token', $token)->firstOrFail();

        abort_if(! $archivo->linkActivo(), 404);

        return Storage::disk('local')->download($archivo->path, $archivo->nombre_original);
    }
}
