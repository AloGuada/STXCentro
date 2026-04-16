<?php

namespace App\Http\Controllers\Admin\Dg;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Dg\ArchivoNotasRequest;
use App\Http\Requests\Admin\Dg\ReporteArchivoStoreRequest;
use App\Http\Requests\Admin\Dg\ReporteStoreRequest;
use App\Http\Requests\Admin\Dg\ReporteUpdateRequest;
use App\Models\Dg\Carpeta;
use App\Models\Dg\Reporte;
use App\Models\Dg\ReporteArchivo;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReporteController extends Controller
{
    public function store(ReporteStoreRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $carpeta = Carpeta::query()->findOrFail($data['carpeta_id']);
        $this->authorize('createForCarpeta', $carpeta);

        // Si no llegan `anio`/`semana` (subida desde "Mis reportes"), calcular desde hoy.
        $hoy = CarbonImmutable::now();
        $anio = $data['anio'] ?? $hoy->isoWeekYear;
        $semana = $data['semana'] ?? $hoy->isoWeek;

        $reporte = DB::transaction(function () use ($data, $request, $anio, $semana) {
            $reporte = Reporte::query()->updateOrCreate(
                [
                    'carpeta_id' => $data['carpeta_id'],
                    'anio' => $anio,
                    'semana' => $semana,
                ],
                [
                    'observaciones' => $data['observaciones'] ?? null,
                    'creado_por_id' => $request->user()->getKey(),
                ]
            );

            foreach ($request->file('archivos') ?? [] as $file) {
                $this->guardarArchivo($reporte, $file, $request->user()->getKey());
            }

            return $reporte;
        });

        $destino = $request->user()->can('dg.reportes.ver')
            ? to_route('admin.dg.carpetas.show', $reporte->carpeta_id)
            : to_route('admin.dg.mis-reportes');

        return $destino->with('success', 'Reporte guardado correctamente.');
    }

    public function update(ReporteUpdateRequest $request, Reporte $reporte): RedirectResponse
    {
        $this->authorize('update', $reporte);

        $reporte->update($request->validated());

        return back()->with('success', 'Reporte actualizado correctamente.');
    }

    public function destroy(Reporte $reporte): RedirectResponse
    {
        $this->authorize('delete', $reporte);

        foreach ($reporte->archivos as $archivo) {
            Storage::disk('local')->delete($archivo->path);
        }

        $reporte->archivos()->delete();
        $reporte->delete();

        return to_route('admin.dg.carpetas.show', $reporte->carpeta_id)
            ->with('success', 'Reporte eliminado correctamente.');
    }

    public function uploadArchivo(ReporteArchivoStoreRequest $request, Reporte $reporte): RedirectResponse
    {
        $this->authorize('update', $reporte);

        foreach ($request->file('archivos') as $file) {
            $this->guardarArchivo($reporte, $file, $request->user()->getKey());
        }

        return back()->with('success', 'Archivos subidos correctamente.');
    }

    public function downloadArchivo(ReporteArchivo $archivo): StreamedResponse
    {
        $this->authorize('view', $archivo->reporte);

        $this->marcarVistoPorDg($archivo);

        return Storage::disk('local')->download($archivo->path, $archivo->nombre_original);
    }

    public function streamArchivo(ReporteArchivo $archivo): StreamedResponse
    {
        $this->authorize('view', $archivo->reporte);

        $this->marcarVistoPorDg($archivo);

        return Storage::disk('local')->response($archivo->path, $archivo->nombre_original, [
            'Content-Type' => $archivo->mime ?? 'application/octet-stream',
        ]);
    }

    private function marcarVistoPorDg(ReporteArchivo $archivo): void
    {
        $usuario = request()->user();
        if (! $usuario || ! $usuario->can('dg.reportes.ver')) {
            return;
        }
        if ($archivo->visto_por_dg_en !== null) {
            return;
        }
        $archivo->update(['visto_por_dg_en' => now()]);
    }

    public function marcarVisto(ReporteArchivo $archivo): RedirectResponse
    {
        $this->authorize('view', $archivo->reporte);

        $this->marcarVistoPorDg($archivo);

        return back(303);
    }

    public function updateNotas(ArchivoNotasRequest $request, ReporteArchivo $archivo): RedirectResponse
    {
        $this->authorize('editarNotas', Reporte::class);

        $archivo->update([
            'notas' => $request->string('notas')->toString() ?: null,
            'notas_editado_por_id' => $request->user()->getKey(),
            'notas_actualizado_en' => now(),
        ]);

        return back(303)->with('success', 'Notas guardadas.');
    }

    public function destroyArchivo(ReporteArchivo $archivo): RedirectResponse
    {
        $this->authorize('update', $archivo->reporte);

        Storage::disk('local')->delete($archivo->path);
        $archivo->delete();

        return back()->with('success', 'Archivo eliminado correctamente.');
    }

    private function guardarArchivo(Reporte $reporte, UploadedFile $file, string $usuarioId): ReporteArchivo
    {
        $directorio = sprintf(
            'dg-reportes/%d/%d/S%02d',
            $reporte->carpeta_id,
            $reporte->anio,
            $reporte->semana
        );

        $path = $file->store($directorio, 'local');

        return ReporteArchivo::query()->create([
            'reporte_id' => $reporte->id,
            'nombre_original' => $file->getClientOriginalName(),
            'path' => $path,
            'mime' => $file->getMimeType(),
            'size' => $file->getSize(),
            'subido_por_id' => $usuarioId,
        ]);
    }
}
