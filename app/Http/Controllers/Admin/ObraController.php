<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ObraStoreRequest;
use App\Http\Requests\Admin\ObraUpdateRequest;
use App\Http\Requests\Admin\PiezaImportCsvRequest;
use App\Models\Obra;
use App\Models\Pieza;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ObraController extends Controller
{
    public function index(Request $request): Response
    {
        $obras = Obra::query()
            ->when($request->search, fn ($q, $s) => $q->where('no', 'like', "%{$s}%")
                ->orWhere('descripcion', 'like', "%{$s}%"))
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
        $obra->load(['piezas' => fn ($q) => $q->orderBy('marca')]);

        return Inertia::render('admin/obras/edit', [
            'obra' => $obra,
        ]);
    }

    public function importPiezas(PiezaImportCsvRequest $request, Obra $obra): RedirectResponse
    {
        $file = $request->file('csv_file');
        $handle = fopen($file->getRealPath(), 'r');

        $header = fgetcsv($handle);
        $header = array_map(fn ($col) => mb_strtoupper(trim($col)), $header);

        // Recolectar todas las filas con su version
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
                'longitud' => ($data['LARGO'] ?? '') !== '' ? (float) str_replace(',', '', $data['LARGO']) : null,
                'peso' => (float) str_replace(',', '', $data['KG.UNIT.'] ?? '0'),
                'cantidad' => (int) ($data['CANT.'] ?? 0),
                'version' => $version,
            ];
        }

        fclose($handle);

        $count = 0;

        foreach ($rows as $rowData) {
            // Verificar contra piezas existentes en la obra
            $existing = Pieza::where('obra_id', $obra->id)
                ->where('marca', $rowData['marca'])
                ->first();

            if ($existing) {
                // Solo actualizar si la version importada es mayor
                if ($rowData['version'] > $existing->version) {
                    $existing->update($rowData);
                    $count++;
                }
            } else {
                Pieza::create(array_merge($rowData, ['obra_id' => $obra->id]));
                $count++;
            }
        }

        return back()->with('success', "Se importaron {$count} piezas correctamente.");
    }

    public function update(ObraUpdateRequest $request, Obra $obra): RedirectResponse
    {
        $obra->update($request->validated());

        return to_route('admin.obras.index');
    }

    public function destroy(Obra $obra): RedirectResponse
    {
        $obra->delete();

        return to_route('admin.obras.index');
    }
}
