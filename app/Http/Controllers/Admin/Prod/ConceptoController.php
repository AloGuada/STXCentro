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
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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
            'qs' => $request->qs,
            'marca' => $request->marca,
            'etapa' => Concepto::normalizarEtapa($request->etapa),
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
        $concepto->load(['catalogo.obra:id,no,descripcion', 'grupoPrecioConceptos.grupoPrecio']);

        return Inertia::render('admin/prod/conceptos/edit', [
            'concepto' => $concepto,
            'categorias' => Categoria::orderBy('nombre')->get(),
        ]);
    }

    public function update(ConceptoUpdateRequest $request, Concepto $concepto): RedirectResponse
    {
        $concepto->update([
            'qs' => $request->qs,
            'marca' => $request->marca,
            'etapa' => Concepto::normalizarEtapa($request->etapa),
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
            return back()->withErrors(['error' => 'No se puede eliminar un concepto que tiene registros asociados.']);
        }

        $catalogoId = $concepto->catalogo_id;
        $concepto->grupoPrecioConceptos()->delete();
        $concepto->delete();

        return to_route('admin.prod.catalogos.show', $catalogoId);
    }

    public function descargarLayout(): BinaryFileResponse
    {
        return Excel::download(new ConceptosLayoutExport, 'layout-conceptos.xlsx');
    }

    /**
     * Importa el layout sobre un catálogo. Los modelos que ya existen en esa
     * versión se sobrescriben; los nuevos se agregan.
     *
     * El modelo es el par (marca, etapa), no la marca sola: un catálogo puede
     * repetir la misma marca en etapas distintas de la obra. `QS` es el id que
     * trae el layout del sistema de planta; se guarda como dato de consulta pero
     * no decide a qué pieza pega el renglón, para que un renumerado en planta no
     * duplique el catálogo.
     */
    public function importCsv(ConceptoImportCsvRequest $request, Catalogo $catalogo): RedirectResponse
    {
        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');

        $header = fgetcsv($handle);
        $header = array_map(fn ($col) => mb_strtoupper(trim($col)), $header);

        $categoriasCache = [];
        $rows = [];
        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < count($header)) {
                continue;
            }

            $data = array_combine($header, $row);

            $marca = trim($data['MARCA'] ?? '');
            if (! $marca) {
                continue;
            }

            $cantidad = (int) str_replace(',', '', $data['CANTIDAD'] ?? '');
            $peso = (float) str_replace(',', '', $data['PESOKG'] ?? '');

            // Ignorar filas de resumen/totales al pie del layout (sin cantidad ni peso reales).
            if ($cantidad < 1 && $peso <= 0) {
                continue;
            }

            $categoriaNombre = trim($data['CATEGORIA'] ?? '');
            $categoriaId = null;
            if ($categoriaNombre !== '') {
                $categoriaId = $categoriasCache[$categoriaNombre] ??= Categoria::firstOrCreate(['nombre' => $categoriaNombre])->id;
            }

            $etapa = Concepto::normalizarEtapa($data['ETAPA'] ?? null);
            $qs = trim((string) ($data['QS'] ?? ''));

            $rows[Concepto::claveDeModelo($marca, $etapa)] = [
                'qs' => $qs === '' ? null : $qs,
                'marca' => $marca,
                'etapa' => $etapa,
                'descripcion' => trim($data['DESCRIPCION'] ?? $data['DESCRIPCIÓN'] ?? ''),
                'categoria_id' => $categoriaId,
                'cantidad' => max($cantidad, 1),
                'peso_unitario' => $peso,
                'longitud' => (int) round((float) str_replace(',', '', $data['LONGITUDMM'] ?? '0')),
                'obra_id' => $catalogo->obra_id,
            ];
        }

        fclose($handle);

        $count = 0;

        foreach ($rows as $rowData) {
            Concepto::updateOrCreate(
                [
                    'catalogo_id' => $catalogo->id,
                    'marca' => $rowData['marca'],
                    'etapa' => $rowData['etapa'],
                ],
                $rowData,
            );
            $count++;
        }

        return back()->with('success', "Se importaron {$count} conceptos correctamente.");
    }
}
