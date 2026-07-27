<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\RegistroImportCsvRequest;
use App\Http\Requests\Admin\Prod\RegistroStoreRequest;
use App\Models\Concepto;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Registro;
use Illuminate\Http\RedirectResponse;

class RegistroController extends Controller
{
    public function store(RegistroStoreRequest $request, Destajo $destajo): RedirectResponse
    {
        if ($destajo->cerrado) {
            return back()->withErrors(['error' => 'No se puede capturar produccion en un destajo cerrado.']);
        }

        if ($request->date('fecha')->lt($destajo->fecha_inicio) || $request->date('fecha')->gt($destajo->fecha_fin)) {
            return back()->withErrors(['fecha' => 'La fecha debe estar dentro del periodo del destajo.']);
        }

        Registro::create($request->validated());

        return to_route('admin.prod.destajos.show', $destajo);
    }

    public function destroy(Destajo $destajo, Registro $registro): RedirectResponse
    {
        if ($destajo->cerrado) {
            return back()->withErrors(['error' => 'No se puede eliminar produccion de un destajo cerrado.']);
        }

        $registro->delete();

        return to_route('admin.prod.destajos.show', $destajo);
    }

    /**
     * Carga masiva de produccion. Columnas: GRUPO, MARCA, CANTIDAD.
     * Todos los renglones toman la fecha (dia) enviada en el formulario.
     */
    public function importCsv(RegistroImportCsvRequest $request, Destajo $destajo): RedirectResponse
    {
        if ($destajo->cerrado) {
            return back()->withErrors(['error' => 'No se puede importar produccion en un destajo cerrado.']);
        }

        $fecha = $request->date('fecha');
        if ($fecha->lt($destajo->fecha_inicio) || $fecha->gt($destajo->fecha_fin)) {
            return back()->withErrors(['fecha' => 'La fecha debe estar dentro del periodo del destajo.']);
        }

        $handle = fopen($request->file('csv_file')->getRealPath(), 'r');
        $header = array_map(fn ($col) => mb_strtoupper(trim($col)), fgetcsv($handle) ?: []);

        $importados = 0;
        $errores = [];
        $linea = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $linea++;
            if (count($row) < count($header)) {
                continue;
            }

            $data = array_combine($header, $row);
            $grupoNombre = trim($data['GRUPO'] ?? '');
            $marca = trim($data['MARCA'] ?? '');
            $cantidad = (int) trim($data['CANTIDAD'] ?? '0');

            if ($grupoNombre === '' && $marca === '') {
                continue;
            }

            $grupo = GrupoTrabajo::where('descripcion', $grupoNombre)->first();
            if ($grupo === null) {
                $errores[] = "Linea {$linea}: grupo \"{$grupoNombre}\" no encontrado.";

                continue;
            }

            $conceptos = Concepto::where('marca', $marca)->where('activo', true)->get();
            if ($conceptos->isEmpty()) {
                $errores[] = "Linea {$linea}: pieza \"{$marca}\" no encontrada.";

                continue;
            }
            if ($conceptos->count() > 1) {
                $errores[] = "Linea {$linea}: pieza \"{$marca}\" existe en varias obras (ambigua).";

                continue;
            }
            if ($cantidad < 1) {
                $errores[] = "Linea {$linea}: cantidad invalida.";

                continue;
            }

            Registro::create([
                'fecha' => $fecha,
                'concepto_id' => $conceptos->first()->id,
                'grupo_trabajo_id' => $grupo->id,
                'cantidad' => $cantidad,
            ]);
            $importados++;
        }

        fclose($handle);

        if ($errores !== []) {
            return back()
                ->with('success', "Se importaron {$importados} registros.")
                ->withErrors(['csv_file' => implode(' ', $errores)]);
        }

        return back()->with('success', "Se importaron {$importados} registros correctamente.");
    }
}
