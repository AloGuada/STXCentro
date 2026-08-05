<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ConceptoImportCsvRequest;
use App\Http\Requests\Admin\ObraStoreRequest;
use App\Http\Requests\Admin\ObraUpdateRequest;
use App\Models\Concepto;
use App\Models\Costos\Rubro;
use App\Models\Obra;
use App\Models\Prod\Catalogo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ObraController extends Controller
{
    public function index(Request $request): Response
    {
        $obras = Obra::query()
            ->sinPlanta()
            ->when($request->search, fn ($q, $s) => $q->where(fn ($q) => $q->where('no', 'like', "%{$s}%")
                ->orWhere('descripcion', 'like', "%{$s}%")))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/obras/index', [
            'obras' => $obras,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/obras/create');
    }

    public function store(ObraStoreRequest $request): RedirectResponse
    {
        Obra::create($request->validated());

        return to_route('admin.obras.index');
    }

    public function edit(Obra $obra): Response
    {
        abort_if($obra->es_planta, 404);

        $obra->load([
            'conceptos' => fn ($q) => $q->orderBy('marca'),
            'obraRubros.rubro.tipoRubro',
        ]);

        return Inertia::render('admin/obras/edit', [
            'obra' => $obra,
            'rubros' => Rubro::query()->where('ambito', 'obra')->with('tipoRubro')->orderBy('codigo')->get(),
        ]);
    }

    public function importConceptos(ConceptoImportCsvRequest $request, Obra $obra): RedirectResponse
    {
        abort_if($obra->es_planta, 404);

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

            $marca = trim($data['PLANO'] ?? '');
            if (! $marca) {
                continue;
            }

            $version = (int) ($data['OBSERVACIONES'] ?? 1);
            if ($version < 1) {
                $version = 1;
            }

            $rows[$marca] = [
                'marca' => $marca,
                'descripcion' => trim($data['CONCEPTO'] ?? ''),
                'peso_unitario' => (float) str_replace(',', '', $data['KG.UNIT.'] ?? '0'),
                'version' => $version,
            ];
        }

        fclose($handle);

        $count = 0;
        $catalogo = $this->catalogoVigenteDe($obra);

        foreach ($rows as $rowData) {
            // Este layout no tiene etapa, así que sólo administra las piezas sin
            // etapa: emparejar por marca a secas escogería al azar entre las
            // etapas de una misma marca.
            $existing = Concepto::where('catalogo_id', $catalogo->id)
                ->where('marca', $rowData['marca'])
                ->whereNull('etapa')
                ->first();

            if ($existing) {
                if ($rowData['version'] > $existing->version) {
                    $existing->update($rowData);
                    $count++;
                }
            } else {
                Concepto::create(array_merge($rowData, [
                    'obra_id' => $obra->id,
                    'catalogo_id' => $catalogo->id,
                ]));
                $count++;
            }
        }

        return back()->with('success', "Se importaron {$count} conceptos correctamente.");
    }

    /**
     * Las piezas siempre viven en un catálogo versionado; si la obra todavía no
     * tiene uno, esta importación lo estrena.
     */
    private function catalogoVigenteDe(Obra $obra): Catalogo
    {
        return $obra->catalogoVigente()->first()
            ?? Catalogo::create([
                'obra_id' => $obra->id,
                'nombre' => 'Catálogo '.$obra->no,
                'version' => 1,
                'vigente' => true,
            ]);
    }

    public function update(ObraUpdateRequest $request, Obra $obra): RedirectResponse
    {
        abort_if($obra->es_planta, 404);

        $obra->update($request->validated());

        return to_route('admin.obras.index');
    }

    public function destroy(Obra $obra): RedirectResponse
    {
        abort_if($obra->es_planta, 404);

        $obra->delete();

        return to_route('admin.obras.index');
    }
}
