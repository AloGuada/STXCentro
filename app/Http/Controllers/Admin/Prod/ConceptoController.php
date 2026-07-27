<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConceptoImportCsvRequest;
use App\Http\Requests\Admin\Prod\ConceptoStoreRequest;
use App\Http\Requests\Admin\Prod\ConceptoUpdateRequest;
use App\Models\Concepto;
use App\Models\Obra;
use App\Models\Prod\Categoria;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConceptoController extends Controller
{
    public function index(Request $request): Response
    {
        $obras = Obra::query()
            ->sinPlanta()
            ->withCount([
                'conceptos as conceptos_count',
                'conceptos as conceptos_activos_count' => fn ($q) => $q->where('activo', true),
            ])
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('no', 'like', "%{$s}%")
                ->orWhere('descripcion', 'like', "%{$s}%")))
            ->orderBy('no')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/prod/conceptos/index', [
            'obras' => $obras,
            'filters' => $request->only(['search']),
        ]);
    }

    public function showByObra(Request $request, Obra $obra): Response
    {
        abort_if($obra->es_planta, 404);

        $conceptos = Concepto::query()
            ->where('obra_id', $obra->id)
            ->with('categoria')
            ->when($request->search, fn ($q, $s) => $q->where('marca', 'like', "%{$s}%")
                ->orWhere('descripcion', 'like', "%{$s}%"))
            ->orderBy('marca')
            ->get();

        return Inertia::render('admin/prod/conceptos/show', [
            'obra' => $obra,
            'conceptos' => $conceptos,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(Request $request): Response
    {
        $obra = Obra::sinPlanta()->findOrFail($request->obra_id);

        return Inertia::render('admin/prod/conceptos/create', [
            'obra' => $obra,
            'categorias' => Categoria::orderBy('nombre')->get(),
        ]);
    }

    public function store(ConceptoStoreRequest $request): RedirectResponse
    {
        Concepto::create([
            'obra_id' => $request->obra_id,
            'marca' => $request->marca,
            'descripcion' => $request->descripcion,
            'cantidad' => $request->cantidad,
            'peso_unitario' => $request->peso_unitario,
            'longitud' => $request->longitud,
            'categoria_id' => $request->categoria_id,
            'version' => $request->version ?? 1,
            'activo' => $request->boolean('activo', true),
        ]);

        return to_route('admin.prod.conceptos.show-by-obra', $request->obra_id);
    }

    public function edit(Concepto $concepto): Response
    {
        $concepto->load(['obra', 'grupoPrecioConceptos.grupoPrecio']);

        return Inertia::render('admin/prod/conceptos/edit', [
            'concepto' => $concepto,
            'categorias' => Categoria::orderBy('nombre')->get(),
        ]);
    }

    public function update(ConceptoUpdateRequest $request, Concepto $concepto): RedirectResponse
    {
        $concepto->update([
            'obra_id' => $request->obra_id,
            'marca' => $request->marca,
            'descripcion' => $request->descripcion,
            'cantidad' => $request->cantidad,
            'peso_unitario' => $request->peso_unitario,
            'longitud' => $request->longitud,
            'categoria_id' => $request->categoria_id,
            'version' => $request->version ?? $concepto->version,
            'activo' => $request->boolean('activo', $concepto->activo),
        ]);

        return to_route('admin.prod.conceptos.show-by-obra', $concepto->obra_id);
    }

    public function destroy(Concepto $concepto): RedirectResponse
    {
        if ($concepto->registros()->exists()) {
            return back()->withErrors(['error' => 'No se puede eliminar un concepto que tiene registros asociados.']);
        }

        $obraId = $concepto->obra_id;
        $concepto->grupoPrecioConceptos()->delete();
        $concepto->delete();

        return to_route('admin.prod.conceptos.show-by-obra', $obraId);
    }

    public function importCsv(ConceptoImportCsvRequest $request, Obra $obra): RedirectResponse
    {
        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');

        $header = fgetcsv($handle);
        $header = array_map(fn ($col) => mb_strtoupper(trim($col)), $header);

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

            // Peso viene en toneladas, convertir a kilos (* 1000)
            $pesoToneladas = (float) str_replace(',', '', $data['PESO(T)'] ?? '0');
            $pesoKilos = $pesoToneladas * 1000;

            // Version viene como "REV X", extraer el numero
            $versionRaw = trim($data['REVISIÓN DE DOCUMENTOS'] ?? $data['REVISION DE DOCUMENTOS'] ?? '1');
            preg_match('/(\d+)/', $versionRaw, $matches);
            $version = (int) ($matches[1] ?? 1);
            if ($version < 1) {
                $version = 1;
            }

            $rows[$marca] = [
                'marca' => $marca,
                'descripcion' => trim($data['DESCRIPCIÓN'] ?? $data['DESCRIPCION'] ?? ''),
                'peso_unitario' => $pesoKilos,
                'version' => $version,
            ];
        }

        fclose($handle);

        $count = 0;

        foreach ($rows as $rowData) {
            $existing = Concepto::where('obra_id', $obra->id)
                ->where('marca', $rowData['marca'])
                ->first();

            if ($existing) {
                if ($rowData['version'] > $existing->version) {
                    $existing->update($rowData);
                    $count++;
                }
            } else {
                Concepto::create(array_merge($rowData, ['obra_id' => $obra->id]));
                $count++;
            }
        }

        return back()->with('success', "Se importaron {$count} conceptos correctamente.");
    }
}
