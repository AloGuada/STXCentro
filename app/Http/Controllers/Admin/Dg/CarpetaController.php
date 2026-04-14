<?php

namespace App\Http\Controllers\Admin\Dg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Dg\CarpetaRequest;
use App\Models\Dg\Carpeta;
use App\Models\Dg\Reporte;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class CarpetaController extends Controller
{
    public function show(Carpeta $carpeta): Response
    {
        $this->authorize('viewCarpeta', $carpeta);

        $hoy = CarbonImmutable::now();
        $anioActual = $hoy->isoWeekYear;
        $semanaActual = $hoy->isoWeek;

        $semanaAnteriorFecha = $hoy->subWeek();
        $anioAnterior = $semanaAnteriorFecha->isoWeekYear;
        $semanaAnterior = $semanaAnteriorFecha->isoWeek;

        $actual = $this->cargarReporte($carpeta->id, $anioActual, $semanaActual);
        $anterior = $this->cargarReporte($carpeta->id, $anioAnterior, $semanaAnterior);

        $historial = Reporte::query()
            ->where('carpeta_id', $carpeta->id)
            ->where(function ($q) use ($anioActual, $semanaActual, $anioAnterior, $semanaAnterior) {
                $q->whereNot(fn ($sub) => $sub->where('anio', $anioActual)->where('semana', $semanaActual))
                    ->whereNot(fn ($sub) => $sub->where('anio', $anioAnterior)->where('semana', $semanaAnterior));
            })
            ->with(['archivos', 'creadoPor:id,name'])
            ->orderByDesc('anio')
            ->orderByDesc('semana')
            ->paginate(20);

        $usuario = request()->user();
        $puedeSubir = $usuario !== null && $carpeta->usuarioPuedeEscribir($usuario);
        $puedeAdministrar = $usuario !== null && $usuario->can('dg.reportes.administrar');

        return Inertia::render('admin/dg/carpetas/show', [
            'carpeta' => $carpeta->only(['id', 'nombre', 'descripcion']),
            'reporte_actual' => $actual,
            'reporte_anterior' => $anterior,
            'historial' => $historial,
            'periodo' => [
                'anio_actual' => $anioActual,
                'semana_actual' => $semanaActual,
                'anio_anterior' => $anioAnterior,
                'semana_anterior' => $semanaAnterior,
            ],
            'puede_subir' => $puedeSubir,
            'puede_administrar' => $puedeAdministrar,
        ]);
    }

    public function store(CarpetaRequest $request): RedirectResponse
    {
        $this->authorize('gestionarAccesos', Carpeta::class);

        Carpeta::create($request->validated());

        return to_route('admin.dg.dashboard')->with('success', 'Carpeta creada.');
    }

    public function update(CarpetaRequest $request, Carpeta $carpeta): RedirectResponse
    {
        $this->authorize('gestionarAccesos', $carpeta);

        $carpeta->update($request->validated());

        return back()->with('success', 'Carpeta actualizada.');
    }

    public function destroy(Carpeta $carpeta): RedirectResponse
    {
        $this->authorize('gestionarAccesos', $carpeta);

        foreach ($carpeta->reportes()->with('archivos')->get() as $reporte) {
            foreach ($reporte->archivos as $archivo) {
                Storage::disk('local')->delete($archivo->path);
            }
        }

        $carpeta->delete();

        return to_route('admin.dg.dashboard')->with('success', 'Carpeta eliminada.');
    }

    private function cargarReporte(int $carpetaId, int $anio, int $semana): ?Reporte
    {
        return Reporte::query()
            ->where('carpeta_id', $carpetaId)
            ->where('anio', $anio)
            ->where('semana', $semana)
            ->with(['archivos.subidoPor:id,name', 'creadoPor:id,name'])
            ->first();
    }
}
