<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Enums\Qal\EstatusModelo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Qal\ModeloIfcRequest;
use App\Jobs\Qal\ProcesarModeloIfc;
use App\Models\Prod\Catalogo;
use App\Models\Qal\Modelo;
use App\Models\Qal\ModeloMarca;
use App\Services\Qal\ResolutorDeMarcas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El modelo 3D de cada obra: el IFC de Tekla convertido en marcas con sus
 * cordones de soldadura.
 *
 * Es una opción del catálogo de Producción de la obra, porque ahí está quien
 * tiene el IFC y quien sabe qué marcas lleva. La conversión corre en la cola
 * `ifc` contra el servicio aparte, y lo que sale es la plantilla sobre la que
 * Calidad reporta cada junta: aquí se ve cómo terminó cada versión y cómo va
 * cada cordón.
 */
class ModeloController extends Controller
{
    /** Las versiones del modelo de la obra de este catálogo. */
    public function index(Catalogo $catalogo): Response
    {
        return Inertia::render('admin/prod/modelos/index', [
            'catalogo' => $this->migas($catalogo),
            'modelos' => fn () => Modelo::query()
                ->with('obra:id,no,descripcion')
                ->withCount('marcas')
                ->where('obra_id', $catalogo->obra_id)
                ->orderByDesc('version')
                ->get()
                ->map(fn (Modelo $modelo): array => $this->resumen($modelo)),
        ]);
    }

    /**
     * Cada IFC es una versión nueva de la obra: la anterior se conserva con sus
     * cordones, porque sobre ellos pudo haber juntas.
     */
    public function store(ModeloIfcRequest $request): RedirectResponse
    {
        $archivo = $request->file('archivo');
        $modelo = $this->nuevaVersion($request->integer('obra_id'), $archivo->getClientOriginalName(), (int) $archivo->getSize(), $request->user()?->getKey());

        $modelo->update(['archivo_ifc' => $archivo->storeAs($modelo->carpeta(), 'modelo.ifc', 'local')]);
        ProcesarModeloIfc::dispatch($modelo->id);

        return back()->with('success', "Modelo v{$modelo->version} en cola: se convierte en unos minutos.");
    }

    public function show(Modelo $modelo): Response
    {
        $modelo->load('obra:id,no,descripcion')->loadCount('marcas');

        // La pantalla enseña la estructura entera y la lista de marcas; los
        // cordones y cómo van se piden al abrir una marca, no aquí: un modelo
        // trae decenas de miles y cargarlos de golpe tiraba la página.
        $marcas = $modelo->marcas()->with('concepto:id,marca,lote')->get()->sortBy('marca', SORT_NATURAL | SORT_FLAG_CASE)->values();
        $catalogo = $this->catalogoDe($modelo->obra_id);

        return Inertia::render('admin/prod/modelos/show', [
            'catalogo' => $catalogo ? $this->migas($catalogo) : null,
            'modelo' => $this->resumen($modelo),
            'marcas' => $marcas->map(fn (ModeloMarca $marca): array => [
                'id' => $marca->id,
                'marca' => $marca->marca,
                'nombre' => $marca->nombre,
                'piezas' => $marca->piezas,
                'peso_kg' => $marca->peso_kg,
                'soldaduras' => $marca->soldaduras,
                'en_catalogo' => $marca->concepto_id !== null,
            ]),
        ]);
    }

    /** Lo pregunta la pantalla mientras el modelo se convierte. */
    public function estado(Modelo $modelo): JsonResponse
    {
        return response()->json($this->resumen($modelo->loadCount('marcas')));
    }

    /**
     * Convierte otra vez el mismo IFC como versión nueva: para cuando cambió el
     * algoritmo de cordones o la conversión falló. La versión anterior no se
     * toca.
     */
    public function reprocesar(Request $request, Modelo $modelo): RedirectResponse
    {
        if (blank($modelo->archivo_ifc) || ! Storage::disk('local')->exists($modelo->archivo_ifc)) {
            return back()->withErrors(['modelo' => 'El IFC de esta versión ya no está en el servidor: súbelo de nuevo desde el catálogo de la obra.']);
        }

        $nueva = $this->nuevaVersion($modelo->obra_id, $modelo->nombre_original, $modelo->tamano_bytes, $request->user()?->getKey());
        $ruta = "{$nueva->carpeta()}/modelo.ifc";
        Storage::disk('local')->copy($modelo->archivo_ifc, $ruta);
        $nueva->update(['archivo_ifc' => $ruta]);
        ProcesarModeloIfc::dispatch($nueva->id);

        return redirect()
            ->route('admin.prod.modelos.show', $nueva)
            ->with('success', "Se reprocesa como v{$nueva->version}; la v{$modelo->version} se conserva con sus cordones.");
    }

    /** Vuelve a amarrar las marcas al catálogo vigente, que pudo cambiar después de subir el IFC. */
    public function resolverMarcas(Modelo $modelo, ResolutorDeMarcas $resolutor): RedirectResponse
    {
        $amarradas = $resolutor->resolver($modelo);

        return back()->with('success', "{$amarradas} de {$modelo->marcas()->count()} marcas quedaron amarradas al catálogo vigente.");
    }

    /**
     * Una versión con juntas capturadas sobre sus cordones no se borra: las
     * juntas se quedarían sin saber de qué soldadura hablan.
     */
    public function destroy(Modelo $modelo): RedirectResponse
    {
        if ($modelo->tieneJuntas()) {
            return back()->withErrors([
                'modelo' => 'Hay juntas capturadas sobre los cordones de esta versión, así que no se borra. Si el modelo cambió, sube una versión nueva.',
            ]);
        }

        Storage::disk('local')->deleteDirectory($modelo->carpeta());
        Storage::disk('public')->deleteDirectory($modelo->carpeta());
        $modelo->delete();

        $catalogo = $this->catalogoDe($modelo->obra_id);

        return ($catalogo
            ? redirect()->route('admin.prod.catalogos.modelos', $catalogo)
            : redirect()->route('admin.prod.catalogos.index'))
            ->with('success', "Modelo v{$modelo->version} eliminado.");
    }

    private function nuevaVersion(int $obraId, string $nombre, int $tamano, ?string $capturista): Modelo
    {
        return Modelo::query()->create([
            'obra_id' => $obraId,
            'catalogo_id' => Catalogo::query()->where('obra_id', $obraId)->where('vigente', true)->value('id'),
            'version' => (int) Modelo::query()->where('obra_id', $obraId)->max('version') + 1,
            'nombre_original' => $nombre,
            'tamano_bytes' => $tamano,
            'estatus' => EstatusModelo::Pendiente,
            'capturista_id' => $capturista,
        ]);
    }

    /** El catálogo al que se regresa: el vigente de la obra, o su última versión. */
    private function catalogoDe(int $obraId): ?Catalogo
    {
        return Catalogo::query()
            ->where('obra_id', $obraId)
            ->orderByDesc('vigente')
            ->orderByDesc('version')
            ->first();
    }

    /**
     * @return array{id: int, nombre: string, version: int, obra_id: int, obra: string|null}
     */
    private function migas(Catalogo $catalogo): array
    {
        $catalogo->loadMissing('obra:id,no,descripcion');

        return [
            'id' => $catalogo->id,
            'nombre' => $catalogo->nombre,
            'version' => $catalogo->version,
            'obra_id' => $catalogo->obra_id,
            'obra' => $catalogo->obra ? trim("{$catalogo->obra->no} — {$catalogo->obra->descripcion}", ' —') : null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function resumen(Modelo $modelo): array
    {
        return [
            'id' => $modelo->id,
            'obra_id' => $modelo->obra_id,
            'obra' => $modelo->obra ? trim("{$modelo->obra->no} — {$modelo->obra->descripcion}", ' —') : null,
            'version' => $modelo->version,
            'nombre_original' => $modelo->nombre_original,
            'tamano_bytes' => $modelo->tamano_bytes,
            'estatus' => $modelo->estatus->value,
            'estatus_etiqueta' => $modelo->estatus->etiqueta(),
            'error' => $modelo->error,
            'welds_version' => $modelo->welds_version,
            'resumen' => $modelo->resumen,
            'marcas_count' => $modelo->marcas_count,
            'modelo_url' => $modelo->modeloUrl(),
            'procesado_at' => $modelo->procesado_at?->toDateTimeString(),
            'subido_at' => $modelo->created_at?->toDateTimeString(),
        ];
    }
}
