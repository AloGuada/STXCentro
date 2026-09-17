<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Enums\Qal\EstatusDossier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Qal\ArbolDeSeccionesRequest;
use App\Http\Requests\Admin\Qal\DossierArchivoRequest;
use App\Http\Requests\Admin\Qal\DossierRequest;
use App\Http\Requests\Admin\Qal\DossierUpdateRequest;
use App\Models\Qal\Dossier;
use App\Models\Qal\DossierArchivo;
use App\Models\Qal\DossierPlantilla;
use App\Models\Qal\Obra;
use App\Services\Qal\Dosier\ArbolDeSecciones;
use App\Services\Qal\Dosier\CreadorDeDossier;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El dosier, en dos pestañas: los dosieres de cada obra y el catálogo de
 * plantillas con que nacen.
 *
 * Es la versión simplificada que pidió calidad: un repositorio donde por
 * sección se suben los PDF necesarios y «Descargar» los une. Sin biblioteca
 * de documentos ni secciones que se llenen solas —de momento todo se sube a
 * mano—.
 */
class DosierController extends Controller
{
    public function index(Request $request): Response
    {
        $dosieres = Dossier::query()
            ->with('obra:id,no,descripcion')
            ->withCount([
                'secciones',
                'secciones as con_archivo_count' => fn (Builder $seccion) => $seccion->has('archivos'),
                'archivos',
                'archivos as no_compatibles_count' => fn (Builder $archivo) => $archivo->where('qal_dossier_archivos.compatible', false),
            ])
            ->latest('updated_at')
            ->get();

        return Inertia::render('admin/calidad/dosier/index', [
            'tab' => $request->query('tab') === 'catalogo' ? 'catalogo' : 'dosieres',
            'plantillaElegida' => $request->integer('plantilla') ?: null,
            'plantillas' => DossierPlantilla::query()
                ->with('secciones')
                ->orderByDesc('activo')
                ->orderBy('nombre')
                ->get()
                ->map(fn (DossierPlantilla $plantilla): array => [
                    'id' => $plantilla->id,
                    'nombre' => $plantilla->nombre,
                    'descripcion' => $plantilla->descripcion,
                    'activo' => $plantilla->activo,
                    'secciones' => $plantilla->secciones->count(),
                    'arbol' => $plantilla->arbol(),
                ])
                ->all(),
            'dosieres' => $dosieres->map(fn (Dossier $dossier): array => [
                'id' => $dossier->id,
                'obra' => trim(($dossier->obra?->no ?? '').' — '.($dossier->obra?->descripcion ?? ''), ' —'),
                'plantilla' => $dossier->plantilla_nombre,
                'estatus' => $dossier->estatus->value,
                'entregado_at' => $dossier->entregado_at?->toDateString(),
                'secciones' => $dossier->secciones_count,
                'con_archivo' => $dossier->con_archivo_count,
                'archivos' => $dossier->archivos_count,
                'no_compatibles' => $dossier->no_compatibles_count,
                'actualizado' => $dossier->updated_at->toDateString(),
            ])->all(),
            // Sólo las obras que todavía no tienen dosier: una obra tiene uno.
            'obrasSinDosier' => Obra::opcionesDeSelector()
                ->reject(fn (array $obra): bool => $dosieres->contains('obra_id', $obra['id']))
                ->values()
                ->all(),
        ]);
    }

    public function store(DossierRequest $request, CreadorDeDossier $creador): RedirectResponse
    {
        $dossier = $creador->desdePlantilla(
            $request->integer('obra_id'),
            DossierPlantilla::query()->findOrFail($request->integer('plantilla_id')),
            $request->user(),
        );

        return to_route('admin.qal.dosier.show', $dossier)->with('success', 'Dosier creado. Sube los PDF de cada sección.');
    }

    public function show(Dossier $dossier): Response
    {
        $dossier->load(['obra:id,no,descripcion', 'capturista:id,name', 'secciones.archivos.capturista:id,name']);

        return Inertia::render('admin/calidad/dosier/show', [
            'dosier' => [
                'id' => $dossier->id,
                'obra' => trim(($dossier->obra?->no ?? '').' — '.($dossier->obra?->descripcion ?? ''), ' —'),
                'plantilla' => $dossier->plantilla_nombre,
                'estatus' => $dossier->estatus->value,
                'entregado_at' => $dossier->entregado_at?->toDateString(),
                'notas' => $dossier->notas,
                'creo' => $dossier->capturista?->name,
                'creado' => $dossier->created_at->toDateString(),
            ],
            'arbol' => $dossier->arbol(),
            'archivos' => $dossier->secciones
                ->flatMap(fn ($seccion) => $seccion->archivos)
                ->map(fn (DossierArchivo $archivo): array => [
                    'id' => $archivo->id,
                    'seccion_id' => $archivo->seccion_id,
                    'orden' => $archivo->orden,
                    'nombre' => $archivo->nombre_original,
                    'size' => $archivo->size,
                    'paginas' => $archivo->paginas,
                    'compatible' => $archivo->compatible,
                    'subio' => $archivo->capturista?->name,
                    'fecha' => $archivo->created_at->format('Y-m-d H:i'),
                    'url' => route('admin.qal.dosier.archivos.ver', [$dossier, $archivo]),
                ])
                ->values()
                ->all(),
            'estatus' => EstatusDossier::opciones(),
            'limites' => ['archivoMb' => DossierArchivoRequest::MAXIMO_KB / 1024, 'porEnvio' => DossierArchivoRequest::MAXIMO_POR_ENVIO],
        ]);
    }

    /**
     * Al entregarse se fija la fecha; si vuelve a revisión o a borrador, se
     * limpia: ya no está entregado.
     */
    public function update(DossierUpdateRequest $request, Dossier $dossier): RedirectResponse
    {
        $estatus = EstatusDossier::from($request->validated('estatus'));

        $dossier->update([
            'estatus' => $estatus,
            'entregado_at' => $estatus === EstatusDossier::Entregado ? ($dossier->entregado_at ?? now()) : null,
            'notas' => $request->validated('notas'),
        ]);

        return back()->with('success', 'Dosier actualizado.');
    }

    /**
     * Sólo se borra un dosier en borrador: uno revisado o entregado ya salió
     * de las manos de quien lo armó.
     */
    public function destroy(Dossier $dossier): RedirectResponse
    {
        if ($dossier->estatus !== EstatusDossier::Borrador) {
            return back()->withErrors(['dosier' => 'Sólo se borra un dosier en borrador.']);
        }

        Storage::disk(DossierArchivo::DISCO)->deleteDirectory($dossier->carpeta());
        $dossier->delete();

        return to_route('admin.qal.dosier.index')->with('success', 'Dosier borrado.');
    }

    /**
     * El árbol propio de este dosier: se le añaden o quitan secciones sin tocar
     * la plantilla ni los demás dosieres. Quitar una sección borra sus PDF.
     */
    public function secciones(ArbolDeSeccionesRequest $request, Dossier $dossier, ArbolDeSecciones $arboles): RedirectResponse
    {
        $antes = $dossier->archivos()->get(['qal_dossier_archivos.id', 'qal_dossier_archivos.path']);

        $arboles->guardar($dossier->secciones(), $request->input('arbol'));

        $quedan = DossierArchivo::query()->whereKey($antes->modelKeys())->pluck('id');
        $huerfanos = $antes->reject(fn (DossierArchivo $archivo): bool => $quedan->contains($archivo->id));
        Storage::disk(DossierArchivo::DISCO)->delete($huerfanos->pluck('path')->all());

        return back()->with('success', 'Secciones guardadas'.($huerfanos->isEmpty() ? '.' : " · se borraron {$huerfanos->count()} PDF de las secciones quitadas."));
    }
}
