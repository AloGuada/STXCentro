<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Exports\Prod\ConceptosLayoutExport;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConceptoImportCsvRequest;
use App\Http\Requests\Admin\Prod\ConceptoStoreRequest;
use App\Http\Requests\Admin\Prod\ConceptoUpdateRequest;
use App\Models\Concepto;
use App\Models\Prod\Catalogo;
use App\Models\Prod\Categoria;
use App\Services\Prod\ImportadorDeLayout;
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
            'piezas' => fn ($q) => $q->orderBy('qs'),
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

    public function descargarLayout(): BinaryFileResponse
    {
        return Excel::download(new ConceptosLayoutExport, 'layout-conceptos.xlsx');
    }

    /**
     * Carga el layout de planta sobre el catálogo. Un renglón por pieza: la
     * marca se escribe una vez y cada QS entra como pieza suya.
     */
    public function importCsv(ConceptoImportCsvRequest $request, Catalogo $catalogo, ImportadorDeLayout $importador): RedirectResponse
    {
        $resultado = $importador->importar($catalogo, $request->file('csv_file')->getRealPath());

        if ($resultado['piezas'] === 0 && $resultado['marcas'] === 0) {
            return back()->withErrors([
                'csv_file' => $resultado['avisos'] === []
                    ? 'El archivo no trae renglones para importar.'
                    : implode(' ', $resultado['avisos']),
            ]);
        }

        $mensaje = "Se importaron {$resultado['marcas']} marca(s) con {$resultado['piezas']} pieza(s).";

        return $resultado['avisos'] === []
            ? back()->with('success', $mensaje)
            : back()->with('success', $mensaje)->withErrors(['csv_file' => implode(' ', $resultado['avisos'])]);
    }
}
