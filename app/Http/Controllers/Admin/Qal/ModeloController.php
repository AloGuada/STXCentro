<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Enums\Qal\EstatusModelo;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Qal\ModeloIfcRequest;
use App\Jobs\Qal\ProcesarModeloIfc;
use App\Models\Prod\Catalogo;
use App\Models\Qal\Modelo;
use App\Models\Qal\ModeloCordon;
use App\Models\Qal\ModeloMarca;
use App\Models\Qal\Obra;
use App\Services\Qal\EstadoDeCordones;
use App\Services\Qal\ResolutorDeMarcas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Los modelos 3D de las obras: el IFC de Tekla convertido en marcas con sus
 * cordones de soldadura.
 *
 * El IFC se sube desde el catálogo de Producción de la obra, que es donde está
 * quien lo tiene, y la conversión corre en la cola `ifc` contra el servicio
 * aparte. Aquí se ve cómo terminó, qué marcas trae y cómo va cada cordón según
 * las juntas que se capturaron encima.
 */
class ModeloController extends Controller
{
    public function index(Request $request): Response
    {
        $obraId = $request->integer('obra') ?: null;

        return Inertia::render('admin/calidad/modelos/index', [
            'obras' => fn () => Obra::opcionesDeSelector(soloActivas: false),
            'obraId' => $obraId,
            'modelos' => fn () => Modelo::query()
                ->with('obra:id,no,descripcion')
                ->withCount('marcas')
                ->when($obraId, fn ($consulta) => $consulta->where('obra_id', $obraId))
                ->orderByDesc('id')
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

        return back()->with('success', "Modelo v{$modelo->version} en cola: se convierte en unos minutos y queda en Calidad → Modelos 3D.");
    }

    public function show(Modelo $modelo, EstadoDeCordones $estados): Response
    {
        $modelo->load('obra:id,no,descripcion')->loadCount('marcas');

        $marcas = $modelo->marcas()->with('concepto:id,marca,lote')->get()->sortBy('marca', SORT_NATURAL | SORT_FLAG_CASE)->values();
        $cordones = ModeloCordon::query()->whereIn('modelo_marca_id', $marcas->pluck('id'))->get(['id', 'modelo_marca_id']);
        $porCordon = $estados->de($cordones->pluck('id')->all());
        $porMarca = $cordones->groupBy('modelo_marca_id');

        return Inertia::render('admin/calidad/modelos/show', [
            'modelo' => $this->resumen($modelo),
            'marcas' => $marcas->map(fn (ModeloMarca $marca): array => [
                'id' => $marca->id,
                'marca' => $marca->marca,
                'nombre' => $marca->nombre,
                'piezas' => $marca->piezas,
                'peso_kg' => $marca->peso_kg,
                'soldaduras' => $marca->soldaduras,
                'en_catalogo' => $marca->concepto_id !== null,
                'cordones' => $this->conteo($porMarca->get($marca->id, collect()), $porCordon),
            ]),
        ]);
    }

    /** Lo pregunta la pantalla mientras el modelo se convierte. */
    public function estado(Modelo $modelo): JsonResponse
    {
        return response()->json($this->resumen($modelo->loadCount('marcas')));
    }

    /**
     * Lo que necesita el visor de una marca: dónde está su geometría y sus
     * cordones, cada uno con cómo va según sus juntas.
     */
    public function marca(ModeloMarca $modeloMarca, EstadoDeCordones $estados): JsonResponse
    {
        $cordones = $modeloMarca->cordones()->get();
        $porCordon = $estados->de($cordones->pluck('id')->all());

        return response()->json([
            'id' => $modeloMarca->id,
            'modelo_id' => $modeloMarca->modelo_id,
            'marca' => $modeloMarca->marca,
            'glb_url' => $modeloMarca->glbUrl(),
            'ficha_url' => $modeloMarca->fichaUrl(),
            'cordones' => $cordones->map(fn (ModeloCordon $cordon): array => [
                'id' => $cordon->id,
                'numero' => $cordon->numero,
                'identificador' => $cordon->identificador(),
                'tipo' => $cordon->tipo->value,
                'junta' => $cordon->junta,
                'piezas' => $cordon->piezas,
                'largo_mm' => $cordon->largo_mm,
                'angulo' => $cordon->angulo,
                't1_mm' => $cordon->t1_mm,
                't2_mm' => $cordon->t2_mm,
                'cateto_min_mm' => $cordon->cateto_min_mm,
                'cateto_max_mm' => $cordon->cateto_max_mm,
                'garganta_min_mm' => $cordon->garganta_min_mm,
                'preparacion' => $cordon->preparacion,
                'avisos' => $cordon->avisos ?? [],
                'puntos' => $cordon->puntos,
                ...$porCordon[$cordon->id],
            ])->values(),
        ]);
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
            ->route('admin.qal.modelos.show', $nueva)
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

        return redirect()
            ->route('admin.qal.modelos.index', ['obra' => $modelo->obra_id])
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
            'procesado_at' => $modelo->procesado_at?->toDateTimeString(),
            'subido_at' => $modelo->created_at?->toDateTimeString(),
        ];
    }

    /**
     * @param  Collection<int, ModeloCordon>  $cordones
     * @param  array<int, array{estado: string}>  $porCordon
     * @return array{correctos: int, con_defecto: int, sin_junta: int}
     */
    private function conteo(Collection $cordones, array $porCordon): array
    {
        $estados = $cordones->map(fn (ModeloCordon $cordon): string => $porCordon[$cordon->id]['estado']);

        return [
            'correctos' => $estados->filter(fn (string $estado): bool => $estado === 'correcta')->count(),
            'con_defecto' => $estados->filter(fn (string $estado): bool => $estado === 'defecto')->count(),
            'sin_junta' => $estados->filter(fn (string $estado): bool => $estado === 'sin')->count(),
        ];
    }
}
