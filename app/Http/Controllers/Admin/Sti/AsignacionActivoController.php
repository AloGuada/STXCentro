<?php

namespace App\Http\Controllers\Admin\Sti;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Sti\AsignacionActivoFirmaRequest;
use App\Http\Requests\Admin\Sti\AsignacionActivoStoreRequest;
use App\Http\Requests\Admin\Sti\AsignacionActivoUpdateRequest;
use App\Http\Requests\Admin\Sti\MediaStoreRequest;
use App\Models\Departamento;
use App\Models\Media;
use App\Models\Sti\AsignacionActivo;
use App\Models\Sti\Equipo;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class AsignacionActivoController extends Controller
{
    public function index(Request $request): Response
    {
        $asignaciones = AsignacionActivo::query()
            ->with(['departamento', 'equipo'])
            ->when($request->search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('empleado', 'like', "%{$search}%")
                        ->orWhere('no_empleado', 'like', "%{$search}%")
                        ->orWhere('nombre_ti', 'like', "%{$search}%")
                        ->orWhereHas('equipo', fn ($eq) => $eq->where('descripcion', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/sti/asignacion-activos/index', [
            'asignaciones' => $asignaciones,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/sti/asignacion-activos/create', [
            'departamentos' => Departamento::orderBy('descripcion')->get(['id', 'descripcion']),
            'equipos' => Equipo::orderBy('descripcion')->get(['id', 'descripcion', 'serie']),
        ]);
    }

    public function store(AsignacionActivoStoreRequest $request): RedirectResponse
    {
        AsignacionActivo::create([
            ...$request->validated(),
            'firma_token' => AsignacionActivo::generarFirmaToken(),
        ]);

        return to_route('admin.sti.asignacion-activos.index')->with('success', 'Asignacion creada correctamente.');
    }

    public function edit(AsignacionActivo $asignacion_activo): Response
    {
        return Inertia::render('admin/sti/asignacion-activos/edit', [
            'asignacion' => $asignacion_activo->load(['departamento', 'equipo', 'media']),
            'departamentos' => Departamento::orderBy('descripcion')->get(['id', 'descripcion']),
            'equipos' => Equipo::orderBy('descripcion')->get(['id', 'descripcion', 'serie']),
        ]);
    }

    public function update(AsignacionActivoUpdateRequest $request, AsignacionActivo $asignacion_activo): RedirectResponse
    {
        $asignacion_activo->update($request->validated());

        return to_route('admin.sti.asignacion-activos.index')->with('success', 'Asignacion actualizada correctamente.');
    }

    public function generarEnlaceFirma(AsignacionActivo $asignacion_activo): RedirectResponse
    {
        if (! $asignacion_activo->firma_token) {
            $asignacion_activo->update(['firma_token' => AsignacionActivo::generarFirmaToken()]);
        }

        return back()->with('success', 'Enlace de firma generado correctamente.');
    }

    public function createSign(AsignacionActivo $asignacion_activo): Response
    {
        $asignacion_activo->load([
            'departamento:id,descripcion',
            'equipo:id,descripcion,serie',
            'media' => fn ($query) => $query->where('mime', 'like', 'image/%')->oldest('id'),
        ]);

        return Inertia::render('sti/firma-asignacion', [
            'token' => $asignacion_activo->firma_token,
            'imagenes' => $asignacion_activo->media->map(fn (Media $media): array => [
                'id' => $media->id,
                'descripcion' => $media->descripcion,
                'url' => '/storage/'.$media->path,
            ])->values(),
            'asignacion' => [
                'id' => $asignacion_activo->id,
                'empleado' => $asignacion_activo->empleado,
                'no_empleado' => $asignacion_activo->no_empleado,
                'departamento' => $asignacion_activo->departamento?->descripcion,
                'equipo' => $asignacion_activo->equipo?->descripcion,
                'serie' => $asignacion_activo->equipo?->serie,
                'nombre_ti' => $asignacion_activo->nombre_ti,
                'fecha_inicial' => $asignacion_activo->fecha_inicial->toDateString(),
                'firmada' => $asignacion_activo->estaFirmadaPorEmpleado(),
            ],
        ]);
    }

    public function storeSign(AsignacionActivoFirmaRequest $request, AsignacionActivo $asignacion_activo): RedirectResponse
    {
        $firmadas = AsignacionActivo::query()
            ->whereKey($asignacion_activo->id)
            ->where(fn ($query) => $query->whereNull('firma_empleado')->orWhere('firma_empleado', ''))
            ->update(['firma_empleado' => $request->validated('firma_empleado')]);

        if ($firmadas === 0) {
            return back()->withErrors(['firma_empleado' => 'Esta asignacion ya fue firmada.']);
        }

        return to_route('sti.firmas.create', ['asignacion_activo' => $asignacion_activo->firma_token])
            ->with('success', 'Firma registrada correctamente.');
    }

    public function pdfSign(AsignacionActivo $asignacion_activo): HttpResponse
    {
        abort_unless($asignacion_activo->estaFirmadaPorEmpleado(), 404);

        return $this->construirPdf($asignacion_activo)->download('responsiva-equipo-'.$asignacion_activo->id.'.pdf');
    }

    public function destroy(AsignacionActivo $asignacion_activo): RedirectResponse
    {
        // Eliminar archivos asociados
        foreach ($asignacion_activo->media as $media) {
            Storage::disk('public')->delete($media->path);
        }
        $asignacion_activo->media()->delete();
        $asignacion_activo->delete();

        return to_route('admin.sti.asignacion-activos.index')->with('success', 'Asignacion eliminada correctamente.');
    }

    public function storeMedia(MediaStoreRequest $request, AsignacionActivo $asignacion_activo): RedirectResponse
    {
        $file = $request->file('archivo');
        $path = $file->store('sti/asignaciones', 'public');

        $asignacion_activo->media()->create([
            'descripcion' => $request->descripcion ?? $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return back()->with('success', 'Archivo subido correctamente.');
    }

    public function generarPdf(AsignacionActivo $asignacion_activo): HttpResponse
    {
        return $this->construirPdf($asignacion_activo)->download('responsiva-equipo-'.$asignacion_activo->id.'.pdf');
    }

    private function construirPdf(AsignacionActivo $asignacion_activo): DomPdf
    {
        $asignacion_activo->load([
            'departamento',
            'equipo.items.tipo',
            'equipo.items.media',
        ]);

        $items = $asignacion_activo->equipo?->items ?? collect();
        $itemPrincipal = $items->firstWhere('principal', true);
        $itemsAccesorios = $items->where('accesorio', true)->values();

        return Pdf::loadView('pdf.sti.responsiva-equipo', [
            'asignacion' => $asignacion_activo,
            'equipo' => $asignacion_activo->equipo,
            'itemPrincipal' => $itemPrincipal,
            'itemsAccesorios' => $itemsAccesorios,
            'items' => $items,
        ])->setPaper('letter', 'portrait')
            ->setOption('margin-top', 30)
            ->setOption('margin-bottom', 60)
            ->setOption('margin-left', 60)
            ->setOption('margin-right', 60);
    }

    public function destroyMedia(AsignacionActivo $asignacion_activo, Media $media): RedirectResponse
    {
        if ($media->mediable_id !== $asignacion_activo->id || $media->mediable_type !== AsignacionActivo::class) {
            abort(404);
        }

        Storage::disk('public')->delete($media->path);
        $media->delete();

        return back()->with('success', 'Archivo eliminado correctamente.');
    }
}
