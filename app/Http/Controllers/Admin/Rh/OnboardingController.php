<?php

namespace App\Http\Controllers\Admin\Rh;

use App\Http\Controllers\Controller;
use App\Models\Rh\Onboarding;
use App\Models\Rh\OnboardingTarea;
use App\Models\Rh\PeriodoLaboral;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class OnboardingController extends Controller
{
    public function show(Onboarding $onboarding): Response
    {
        $this->authorize('rh.onboarding.ver');

        $onboarding->load(['periodo.persona', 'periodo.puesto', 'tareas.responsable.persona', 'tareas.media']);

        $periodosActivos = PeriodoLaboral::query()
            ->where('estado', 'activo')
            ->with('persona')
            ->get()
            ->map(fn (PeriodoLaboral $p) => [
                'id' => $p->id,
                'nombre' => $p->persona->nombre.' '.$p->persona->apellido,
            ]);

        return Inertia::render('admin/rh/onboarding/show', [
            'onboarding' => $onboarding,
            'periodosActivos' => $periodosActivos,
        ]);
    }

    public function storeTarea(Request $request, Onboarding $onboarding): RedirectResponse
    {
        $this->authorize('rh.onboarding.editar');

        $request->validate([
            'titulo' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'responsable_periodo_id' => ['nullable', 'exists:rh_periodos_laborales,id'],
        ]);

        $onboarding->tareas()->create($request->only('titulo', 'descripcion', 'fecha_vencimiento', 'responsable_periodo_id'));
        $this->recalcularProgreso($onboarding);

        return back();
    }

    public function toggleTarea(Onboarding $onboarding, OnboardingTarea $tarea): RedirectResponse
    {
        $this->authorize('rh.onboarding.editar');

        $tarea->update([
            'completada' => ! $tarea->completada,
            'fecha_completada' => ! $tarea->completada ? now() : null,
        ]);

        $this->recalcularProgreso($onboarding);

        return back();
    }

    public function subirEvidencia(Request $request, Onboarding $onboarding, OnboardingTarea $tarea): RedirectResponse
    {
        $this->authorize('rh.onboarding.editar');

        $request->validate([
            'evidencia' => ['required', 'file', 'max:10240'],
        ]);

        if ($tarea->media) {
            Storage::disk('public')->delete($tarea->media->path);
            $tarea->media->delete();
        }

        $file = $request->file('evidencia');
        $tarea->media()->create([
            'descripcion' => 'evidencia',
            'nombre_original' => $file->getClientOriginalName(),
            'path' => $file->store('rh/onboarding/'.$onboarding->id, 'public'),
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
        ]);

        return back();
    }

    public function destroyTarea(Onboarding $onboarding, OnboardingTarea $tarea): RedirectResponse
    {
        $this->authorize('rh.onboarding.editar');

        if ($tarea->media) {
            Storage::disk('public')->delete($tarea->media->path);
            $tarea->media->delete();
        }

        $tarea->delete();
        $this->recalcularProgreso($onboarding);

        return back();
    }

    private function recalcularProgreso(Onboarding $onboarding): void
    {
        $total = $onboarding->tareas()->count();
        $completadas = $onboarding->tareas()->where('completada', true)->count();
        $onboarding->update([
            'progreso' => $total > 0 ? (int) round(($completadas / $total) * 100) : 0,
        ]);
    }
}
