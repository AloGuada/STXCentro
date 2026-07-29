<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\RegistroImportCsvRequest;
use App\Http\Requests\Admin\Prod\RegistroStoreRequest;
use App\Models\Concepto;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Registro;
use App\Services\Prod\AvanceDePiezas;
use Illuminate\Http\RedirectResponse;

class RegistroController extends Controller
{
    public function store(RegistroStoreRequest $request, Destajo $destajo, AvanceDePiezas $avance): RedirectResponse
    {
        if ($destajo->cerrado) {
            return back()->withErrors(['error' => 'No se puede capturar produccion en un destajo cerrado.']);
        }

        if ($request->date('fecha')->lt($destajo->fecha_inicio) || $request->date('fecha')->gt($destajo->fecha_fin)) {
            return back()->withErrors(['fecha' => 'La fecha debe estar dentro del periodo del destajo.']);
        }

        $concepto = Concepto::findOrFail($request->concepto_id);
        $porcentaje = (float) ($request->porcentaje ?? 100);

        if (! $avance->cabe($concepto, $request->integer('cantidad'), $porcentaje)) {
            return back()->withErrors([
                'cantidad' => $this->mensajeDeTope($concepto, $avance->disponible($concepto)),
            ]);
        }

        Registro::create([...$request->validated(), 'porcentaje' => $porcentaje]);

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
    public function importCsv(RegistroImportCsvRequest $request, Destajo $destajo, AvanceDePiezas $avance): RedirectResponse
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
        /** @var array<string, int> $topes */
        $topes = [];

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

            $conceptos = Concepto::deCatalogoVigente()->where('marca', $marca)->where('activo', true)->get();
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

            $porcentaje = (float) str_replace('%', '', trim($data['PORCENTAJE'] ?? '100')) ?: 100.0;
            if ($porcentaje <= 0 || $porcentaje > 100) {
                $errores[] = "Linea {$linea}: porcentaje invalido (debe ir de 1 a 100).";

                continue;
            }

            $concepto = $conceptos->first();

            // El tope se descuenta dentro del propio archivo: dos renglones de
            // la misma marca no pueden rebasar juntos lo que falta.
            $clave = $concepto->obra_id.'|'.$concepto->marca;
            $disponible = $topes[$clave] ??= $avance->disponible($concepto);
            $consumo = round($cantidad * ($porcentaje / 100), 4);

            if ($consumo > $disponible + 0.0001) {
                $errores[] = "Linea {$linea}: ".$this->mensajeDeTope($concepto, $disponible);

                continue;
            }

            Registro::create([
                'fecha' => $fecha,
                'concepto_id' => $concepto->id,
                'grupo_trabajo_id' => $grupo->id,
                'cantidad' => $cantidad,
                'porcentaje' => $porcentaje,
            ]);
            $topes[$clave] -= $consumo;
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

    /**
     * Explica el tope con los tres números que el capturista necesita (lo que
     * manda el catálogo, lo ya capturado y lo que queda) y hacia dónde ir: las
     * piezas rehechas se pagan como pago extra, no como producción.
     */
    private function mensajeDeTope(Concepto $concepto, float $disponible): string
    {
        $salida = ' Si son piezas rehechas, regístralas como pago extra.';

        if ($disponible <= 0) {
            return "La pieza \"{$concepto->marca}\" ya está pagada al 100%: el catálogo pide {$concepto->cantidad} y no queda nada por pagar.".$salida;
        }

        $pendiente = rtrim(rtrim(number_format($disponible, 2, '.', ''), '0'), '.');
        $pagado = rtrim(rtrim(number_format((float) $concepto->cantidad - $disponible, 2, '.', ''), '0'), '.');

        return "La pieza \"{$concepto->marca}\" solo tiene {$pendiente} pieza(s) por pagar: el catálogo pide {$concepto->cantidad} y ya se pagaron {$pagado} (contando parcialidades).".$salida;
    }
}
