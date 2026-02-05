<?php

namespace App\Http\Controllers\Admin\Sti;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Sti\CostoStoreRequest;
use App\Http\Requests\Admin\Sti\MantenimientoStoreRequest;
use App\Http\Requests\Admin\Sti\MantenimientoUpdateRequest;
use App\Http\Requests\Admin\Sti\MediaStoreRequest;
use App\Models\Media;
use App\Models\Sti\CostoMantenimiento;
use App\Models\Sti\Equipo;
use App\Models\Sti\Mantenimiento;
use App\Models\Sti\Tecnico;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class MantenimientoController extends Controller
{
    public function index(Request $request): Response
    {
        $mantenimientos = Mantenimiento::query()
            ->with(['equipo', 'tecnico'])
            ->when($request->search, fn ($q, $s) => $q->where('descripcion', 'like', "%{$s}%"))
            ->orderBy('fecha_programada', 'desc')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/sti/mantenimientos/index', [
            'mantenimientos' => $mantenimientos,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/sti/mantenimientos/create', [
            'equipos' => Equipo::orderBy('descripcion')->get(['id', 'descripcion', 'serie', 'periodicidad_mantenimiento']),
            'tecnicos' => Tecnico::where('activo', true)->orderBy('descripcion')->get(['id', 'descripcion']),
        ]);
    }

    public function store(MantenimientoStoreRequest $request): RedirectResponse
    {
        Mantenimiento::create([
            'equipo_id' => $request->equipo_id,
            'fecha_programada' => $request->fecha_programada,
            'descripcion' => $request->descripcion,
            'tecnico_id' => $request->tecnico_id,
            'status' => 'pendiente',
        ]);

        return to_route('admin.sti.mantenimientos.index');
    }

    public function edit(Mantenimiento $mantenimiento): Response
    {
        $mantenimiento->load(['equipo', 'tecnico', 'media', 'costos']);

        return Inertia::render('admin/sti/mantenimientos/edit', [
            'mantenimiento' => $mantenimiento,
            'equipos' => Equipo::orderBy('descripcion')->get(['id', 'descripcion', 'serie', 'periodicidad_mantenimiento']),
            'tecnicos' => Tecnico::where('activo', true)->orderBy('descripcion')->get(['id', 'descripcion']),
        ]);
    }

    public function update(MantenimientoUpdateRequest $request, Mantenimiento $mantenimiento): RedirectResponse
    {
        $mantenimiento->update([
            'equipo_id' => $request->equipo_id,
            'fecha_programada' => $request->fecha_programada,
            'descripcion' => $request->descripcion,
            'tecnico_id' => $request->tecnico_id,
        ]);

        return to_route('admin.sti.mantenimientos.index');
    }

    public function destroy(Mantenimiento $mantenimiento): RedirectResponse
    {
        // Eliminar archivos asociados
        foreach ($mantenimiento->media as $media) {
            Storage::disk('public')->delete($media->path);
        }
        $mantenimiento->media()->delete();
        $mantenimiento->costos()->delete();
        $mantenimiento->delete();

        return to_route('admin.sti.mantenimientos.index');
    }

    public function completar(Request $request, Mantenimiento $mantenimiento): RedirectResponse
    {
        $mantenimiento->update([
            'status' => 'realizado',
            'fecha_realizado' => now(),
        ]);

        // Si se solicita crear siguiente mantenimiento
        if ($request->boolean('crear_siguiente')) {
            $equipo = $mantenimiento->equipo;

            if ($equipo && $equipo->periodicidad_mantenimiento) {
                $siguienteFecha = $request->fecha_siguiente
                    ? $request->fecha_siguiente
                    : now()->addDays($equipo->periodicidad_mantenimiento);

                Mantenimiento::create([
                    'equipo_id' => $mantenimiento->equipo_id,
                    'fecha_programada' => $siguienteFecha,
                    'descripcion' => $mantenimiento->descripcion,
                    'tecnico_id' => $mantenimiento->tecnico_id,
                    'status' => 'pendiente',
                ]);
            }
        }

        return to_route('admin.sti.mantenimientos.index')->with('success', 'Mantenimiento marcado como realizado.');
    }

    public function gantt(Request $request): Response
    {
        $mes = $request->input('mes', now()->format('Y-m'));

        $mantenimientos = Mantenimiento::query()
            ->with(['equipo', 'tecnico'])
            ->whereYear('fecha_programada', substr($mes, 0, 4))
            ->whereMonth('fecha_programada', substr($mes, 5, 2))
            ->when($request->equipo_id, fn ($q, $id) => $q->where('equipo_id', $id))
            ->when($request->tecnico_id, fn ($q, $id) => $q->where('tecnico_id', $id))
            ->orderBy('fecha_programada')
            ->get();

        return Inertia::render('admin/sti/mantenimientos/gantt', [
            'mantenimientos' => $mantenimientos,
            'mes' => $mes,
            'equipos' => Equipo::orderBy('descripcion')->get(['id', 'descripcion']),
            'tecnicos' => Tecnico::where('activo', true)->orderBy('descripcion')->get(['id', 'descripcion']),
            'filters' => $request->only(['mes', 'equipo_id', 'tecnico_id']),
        ]);
    }

    public function storeMedia(MediaStoreRequest $request, Mantenimiento $mantenimiento): RedirectResponse
    {
        $file = $request->file('archivo');
        $path = $file->store('sti/mantenimientos', 'public');

        $mantenimiento->media()->create([
            'descripcion' => $request->descripcion ?? $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return back()->with('success', 'Imagen subida correctamente.');
    }

    public function destroyMedia(Mantenimiento $mantenimiento, Media $media): RedirectResponse
    {
        if ($media->mediable_id !== $mantenimiento->id || $media->mediable_type !== Mantenimiento::class) {
            abort(404);
        }

        Storage::disk('public')->delete($media->path);
        $media->delete();

        return back()->with('success', 'Imagen eliminada correctamente.');
    }

    public function storeCosto(CostoStoreRequest $request, Mantenimiento $mantenimiento): RedirectResponse
    {
        $mantenimiento->costos()->create([
            'descripcion' => $request->descripcion,
            'cantidad' => $request->cantidad,
        ]);

        return back()->with('success', 'Costo agregado correctamente.');
    }

    public function destroyCosto(Mantenimiento $mantenimiento, CostoMantenimiento $costo): RedirectResponse
    {
        if ($costo->costeable_id !== $mantenimiento->id || $costo->costeable_type !== Mantenimiento::class) {
            abort(404);
        }

        $costo->delete();

        return back()->with('success', 'Costo eliminado correctamente.');
    }
}
