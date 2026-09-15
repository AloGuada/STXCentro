<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Exports\Prod\ConceptosLayoutExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConceptoImportCsvRequest;
use App\Http\Requests\Admin\Prod\ConceptoStoreRequest;
use App\Http\Requests\Admin\Prod\ConceptoUpdateRequest;
use App\Models\Concepto;
use App\Models\Obra;
use App\Models\Prod\Catalogo;
use App\Models\Prod\Categoria;
use App\Models\Prod\LiquidacionDetalle;
use App\Models\Prod\Proceso;
use App\Services\Prod\AvanceDePiezas;
use App\Services\Prod\ImportadorDeLayout;
use App\Services\Prod\ModalidadDePago;
use App\Services\Prod\VersionadorCatalogo;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * La marca del catálogo: el modelo del que cuelgan las piezas (QS).
 */
class ConceptoController extends Controller
{
    public function create(Request $request): Response
    {
        $catalogo = Catalogo::with('obra:id,no,descripcion')->findOrFail($request->catalogo_id);

        return Inertia::render('admin/prod/conceptos/create', [
            'catalogo' => $catalogo,
            'categorias' => Categoria::orderBy('nombre')->get(),
        ]);
    }

    public function store(ConceptoStoreRequest $request): RedirectResponse
    {
        $catalogo = Catalogo::findOrFail($request->catalogo_id);

        Concepto::create([
            'catalogo_id' => $catalogo->id,
            'obra_id' => $catalogo->obra_id,
            'marca' => $request->marca,
            'lote' => Concepto::normalizarLote($request->lote),
            'descripcion' => $request->descripcion,
            'cantidad' => $request->cantidad,
            'peso_unitario' => $request->peso_unitario,
            'longitud' => $request->longitud,
            'categoria_id' => $request->categoria_id,
            'version' => $request->version ?? $catalogo->version,
            'activo' => $request->boolean('activo', true),
        ]);

        return to_route('admin.prod.catalogos.show', $catalogo);
    }

    public function edit(Concepto $concepto): Response
    {
        $concepto->load([
            'catalogo.obra:id,no,descripcion',
            'grupoPrecioConceptos.grupoPrecio',
            'piezas' => fn ($q) => $q->where('activo', true)->orderBy('correlativo')->orderBy('qr'),
        ]);

        return Inertia::render('admin/prod/conceptos/edit', [
            'concepto' => $concepto,
            'categorias' => Categoria::orderBy('nombre')->get(),
        ]);
    }

    public function update(ConceptoUpdateRequest $request, Concepto $concepto): RedirectResponse
    {
        $concepto->update([
            'marca' => $request->marca,
            'lote' => Concepto::normalizarLote($request->lote),
            'descripcion' => $request->descripcion,
            'cantidad' => $request->cantidad,
            'peso_unitario' => $request->peso_unitario,
            'longitud' => $request->longitud,
            'categoria_id' => $request->categoria_id,
            'version' => $request->version ?? $concepto->version,
            'activo' => $request->boolean('activo', $concepto->activo),
        ]);

        return to_route('admin.prod.catalogos.show', $concepto->catalogo_id);
    }

    public function destroy(Concepto $concepto): RedirectResponse
    {
        if ($concepto->registros()->exists()) {
            return back()->withErrors(['error' => 'No se puede eliminar una marca que tiene producción capturada.']);
        }

        $catalogoId = $concepto->catalogo_id;
        $concepto->grupoPrecioConceptos()->delete();
        $concepto->piezas()->delete();
        $concepto->delete();

        return to_route('admin.prod.catalogos.show', $catalogoId);
    }

    /**
     * Las marcas del catálogo vigente de una obra.
     *
     * Segundo escalón de la carga del catálogo: la pantalla trae las obras y
     * pide estas al elegir una. Nunca viajan con sus piezas —para eso está
     * `piezas()`—, que es lo que mantiene la respuesta en unos KB por grande
     * que sea la obra.
     */
    public function marcasDeObra(Obra $obra): JsonResponse
    {
        return response()->json([
            'marcas' => Concepto::query()
                ->where('obra_id', $obra->id)
                ->deCatalogoVigente()
                ->where('activo', true)
                ->orderBy('marca')
                ->orderBy('lote')
                ->get(['id', 'obra_id', 'marca', 'lote', 'descripcion', 'cantidad']),
        ]);
    }

    /**
     * Las piezas de una marca con su avance por proceso: el tercer escalón, que
     * se pide al elegir la marca o al desplegarla en el catálogo.
     *
     * Si el grupo de precios de la marca paga por subproceso, también bajan los
     * pasos con su precio y el avance de cada uno: la captura tiene que ofrecer
     * el paso, y el tope se cuenta por paso, no por proceso.
     */
    public function piezas(Concepto $concepto, AvanceDePiezas $avance, ModalidadDePago $modalidad): JsonResponse
    {
        $piezas = $concepto->piezas()
            ->where('activo', true)
            ->orderBy('correlativo')
            ->orderBy('qr')
            ->get(['id', 'catalogo_id', 'concepto_id', 'qr', 'qs', 'correlativo']);

        $procesoIds = Proceso::activos()->pluck('id')->all();

        $grupo = $modalidad->grupo((int) $concepto->id, (int) $concepto->obra_id);
        $pagaPorSubproceso = $grupo?->pagaPorSubproceso() ?? false;

        $subprocesos = $pagaPorSubproceso
            ? $grupo->subprocesos->where('activo', true)->sortBy('orden')->values()
            : collect();

        return response()->json([
            'paga_por_subproceso' => $pagaPorSubproceso,
            'subprocesos' => $subprocesos->map(fn ($subproceso) => [
                'id' => $subproceso->id,
                'proceso_id' => $subproceso->proceso_id,
                'nombre' => $subproceso->nombre,
                'precio' => (float) $subproceso->precio,
            ])->values(),
            'piezas' => $avance->decorar($piezas, $procesoIds, $subprocesos)->map(fn ($pieza) => [
                'id' => $pieza->id,
                'qr' => $pieza->qr,
                'qs' => $pieza->qs,
                'correlativo' => $pieza->correlativo,
                'avance' => $pieza->avance,
                'avance_subprocesos' => $pieza->avance_subprocesos,
            ])->values(),
        ]);
    }

    public function descargarLayout(): BinaryFileResponse
    {
        return Excel::download(new ConceptosLayoutExport, 'layout-conceptos.xlsx');
    }

    /**
     * Carga el layout de planta sobre el catálogo. Un renglón por pieza: la
     * marca se escribe una vez y cada QR entra como pieza suya.
     *
     * Si el catálogo vigente ya tiene producción capturada, el layout no se le
     * escribe encima: se abre una versión nueva y se carga ahí. Así la anterior
     * queda congelada y el comparador puede decir qué cambió y qué ya se había
     * pagado. Un catálogo sin producción se sobrescribe, que versionar cada
     * corrección de un layout recién cargado sólo estorba.
     */
    public function importCsv(
        ConceptoImportCsvRequest $request,
        Catalogo $catalogo,
        ImportadorDeLayout $importador,
        VersionadorCatalogo $versionador,
    ): RedirectResponse {
        $versionada = false;

        if ($catalogo->vigente && $this->tieneProduccion($catalogo)) {
            $catalogo = $versionador->nuevaVersion($catalogo, 'Layout cargado el '.now()->format('d/m/Y H:i'));
            $versionada = true;
        }

        $resultado = $importador->importar($catalogo, $request->file('csv_file')->getRealPath());

        if ($resultado['piezas'] === 0 && $resultado['marcas'] === 0) {
            return back()->withErrors([
                'csv_file' => $resultado['avisos'] === []
                    ? 'El archivo no trae renglones para importar.'
                    : implode(' ', $resultado['avisos']),
            ]);
        }

        $mensaje = "Se importaron {$resultado['marcas']} marca(s) con {$resultado['piezas']} pieza(s).";

        if ($versionada) {
            $mensaje = "Se creó la versión {$catalogo->version} y se cargó ahí el layout. {$mensaje}";
        }

        // Lo que el layout deja por debajo de lo ya pagado. Se pregunta con un
        // servicio fresco: el que corrió antes ya tiene cacheado el avance.
        $avisos = [...$resultado['avisos'], ...$this->avisosDeExcedente($catalogo)];

        $respuesta = $versionada
            ? to_route('admin.prod.catalogos.show', $catalogo)->with('success', $mensaje)
            : back()->with('success', $mensaje);

        return $avisos === [] ? $respuesta : $respuesta->withErrors(['csv_file' => implode(' ', $avisos)]);
    }

    /** Si alguna pieza del catálogo ya tiene producción capturada o pagada. */
    private function tieneProduccion(Catalogo $catalogo): bool
    {
        return $catalogo->piezas()->whereHas('registros')->exists()
            || LiquidacionDetalle::query()->whereIn('pieza_id', $catalogo->piezas()->select('id'))->exists();
    }

    /**
     * @return list<string>
     */
    private function avisosDeExcedente(Catalogo $catalogo): array
    {
        return array_map(
            fn (array $e): string => sprintf(
                '%s: ya van %s pieza(s) pagadas y el layout deja la cantidad en %d; %s de más.',
                Concepto::etiquetaDeModelo($e['marca'], $e['lote']),
                rtrim(rtrim(number_format($e['pagadas'], 4, '.', ''), '0'), '.'),
                $e['cantidad'],
                rtrim(rtrim(number_format($e['excedente'], 4, '.', ''), '0'), '.'),
            ),
            app()->make(AvanceDePiezas::class)->excedentesDe($catalogo),
        );
    }
}
