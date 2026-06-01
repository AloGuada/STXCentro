<?php

namespace App\Http\Controllers\Admin\Drive;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Drive\CarpetaStoreRequest;
use App\Http\Requests\Admin\Drive\CarpetaUpdateRequest;
use App\Models\Drive\Archivo;
use App\Models\Drive\Carpeta;
use App\Models\Drive\Externo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DriveCarpetaController extends Controller
{
    public function index(): Response
    {
        $this->authorize('drive.gestionar');

        $carpetas = Carpeta::query()
            ->withCount(['archivos', 'externos'])
            ->withSum('archivos', 'size')
            ->with('usuario:id,name')
            ->latest()
            ->paginate(15);

        return Inertia::render('admin/drive/carpetas/index', [
            'carpetas' => $carpetas,
        ]);
    }

    public function create(): Response
    {
        $this->authorize('drive.gestionar');

        return Inertia::render('admin/drive/carpetas/create');
    }

    public function store(CarpetaStoreRequest $request): RedirectResponse
    {
        $this->authorize('drive.gestionar');

        Carpeta::create([
            ...$request->validated(),
            'usuario_id' => $request->user()->getKey(),
        ]);

        return to_route('admin.drive.carpetas.index')->with('success', 'Carpeta creada correctamente.');
    }

    public function show(Carpeta $carpeta): Response
    {
        $this->authorize('drive.gestionar');

        $carpeta->load('usuario:id,name');

        $archivos = $carpeta->archivos()
            ->latest()
            ->paginate(20);

        $externos = $carpeta->externos()->get();

        $externosDisponibles = Externo::query()
            ->where('activo', true)
            ->whereDoesntHave('carpetas', fn ($q) => $q->where('drive_carpetas.id', $carpeta->id))
            ->get(['id', 'nombre', 'email', 'empresa']);

        return Inertia::render('admin/drive/carpetas/show', [
            'carpeta' => $carpeta,
            'archivos' => $archivos,
            'externos' => $externos,
            'externosDisponibles' => $externosDisponibles,
        ]);
    }

    public function edit(Carpeta $carpeta): Response
    {
        $this->authorize('drive.gestionar');

        return Inertia::render('admin/drive/carpetas/edit', [
            'carpeta' => $carpeta,
        ]);
    }

    public function update(CarpetaUpdateRequest $request, Carpeta $carpeta): RedirectResponse
    {
        $this->authorize('drive.gestionar');

        $carpeta->update($request->validated());

        return to_route('admin.drive.carpetas.show', $carpeta)->with('success', 'Carpeta actualizada correctamente.');
    }

    public function destroy(Carpeta $carpeta): RedirectResponse
    {
        $this->authorize('drive.gestionar');

        foreach ($carpeta->archivos as $archivo) {
            Storage::disk('local')->delete($archivo->path);
        }

        $carpeta->delete();

        return to_route('admin.drive.carpetas.index')->with('success', 'Carpeta eliminada correctamente.');
    }

    public function toggleAcceso(Carpeta $carpeta, Externo $externo): RedirectResponse
    {
        $this->authorize('drive.gestionar');

        $carpeta->externos()->toggle($externo->id);

        return back()->with('success', 'Acceso actualizado correctamente.');
    }

    public function uploadArchivo(Request $request, Carpeta $carpeta): RedirectResponse
    {
        $this->authorize('drive.gestionar');

        $request->validate([
            'archivo' => ['required', 'file', 'max:51200', 'mimes:pdf,doc,docx,xls,xlsx,zip,rar,jpg,jpeg,png,dwg,dxf'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('archivo');
        $path = $file->store("drive/{$carpeta->id}", 'local');

        Archivo::create([
            'carpeta_id' => $carpeta->id,
            'nombre_original' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'descripcion' => $request->descripcion,
            'subido_por_type' => 'interno',
            'subido_por_id' => $request->user()->getKey(),
        ]);

        return back()->with('success', 'Archivo subido correctamente.');
    }

    public function downloadArchivo(Archivo $archivo): StreamedResponse
    {
        $this->authorize('drive.gestionar');

        return Storage::disk('local')->download($archivo->path, $archivo->nombre_original);
    }

    public function destroyArchivo(Archivo $archivo): RedirectResponse
    {
        $this->authorize('drive.gestionar');

        Storage::disk('local')->delete($archivo->path);
        $archivo->delete();

        return back()->with('success', 'Archivo eliminado correctamente.');
    }

    public function generarLink(Request $request, Archivo $archivo): RedirectResponse
    {
        $this->authorize('drive.gestionar');

        $request->validate([
            'link_expira_en' => ['nullable', 'date', 'after:now'],
            'auto_eliminar_en' => ['nullable', 'date', 'after:now'],
        ]);

        $archivo->update([
            'link_token' => Str::uuid()->toString(),
            'link_expira_en' => $request->link_expira_en,
            'auto_eliminar_en' => $request->auto_eliminar_en,
        ]);

        return back()->with('success', 'Link público generado correctamente.');
    }

    public function revocarLink(Archivo $archivo): RedirectResponse
    {
        $this->authorize('drive.gestionar');

        $archivo->update([
            'link_token' => null,
            'link_expira_en' => null,
        ]);

        return back()->with('success', 'Link público revocado correctamente.');
    }
}
