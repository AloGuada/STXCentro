<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\RegistroImportCsvRequest;
use App\Http\Requests\Admin\Prod\RegistroStoreRequest;
use App\Models\Concepto;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Registro;
use App\Models\Prod\Ubicacion;
use App\Services\Prod\AvanceDePiezas;
use App\Services\Prod\LectorCsvDeProduccion;
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
     * Carga masiva de produccion. Acepta el export de avance de planta (se queda
     * con el evento 55 y usa Ubicacion, Marca, Etapa y Cantidad) o un CSV a mano
     * con GRUPO, MARCA, ETAPA, CANTIDAD. Todos los renglones toman la fecha del
     * formulario.
     *
     * La pieza se resuelve por marca + etapa. Si el archivo no trae ETAPA y esa
     * marca esta repetida en varias etapas del catalogo, el renglon se reporta en
     * vez de cargarse: pagarlo a la pieza equivocada no tiene vuelta atras.
     */
    public function importCsv(
        RegistroImportCsvRequest $request,
        Destajo $destajo,
        AvanceDePiezas $avance,
        LectorCsvDeProduccion $lector,
    ): RedirectResponse {
        if ($destajo->cerrado) {
            return back()->withErrors(['error' => 'No se puede importar produccion en un destajo cerrado.']);
        }

        $fecha = $request->date('fecha');
        if ($fecha->lt($destajo->fecha_inicio) || $fecha->gt($destajo->fecha_fin)) {
            return back()->withErrors(['fecha' => 'La fecha debe estar dentro del periodo del destajo.']);
        }

        ['formato' => $formato, 'filas' => $filas] = $lector->leer($request->file('csv_file')->getRealPath());

        if ($filas === []) {
            $vacio = $formato === LectorCsvDeProduccion::FORMATO_EXPORT
                ? 'El archivo no trae ningun movimiento del evento '.LectorCsvDeProduccion::EVENTO_DESTAJO.'.'
                : 'El archivo no trae renglones para importar.';

            return back()->withErrors(['csv_file' => $vacio]);
        }

        $importados = 0;
        $errores = [];
        /** @var array<string, float> $topes */
        $topes = [];

        foreach ($filas as $fila) {
            $ref = $fila['referencia'];

            $grupo = $fila['ubicacion'] !== null
                ? $this->grupoDeUbicacion($fila['ubicacion'], $lector, $errores, $ref)
                : GrupoTrabajo::where('descripcion', $fila['grupo'])->first();

            if ($grupo === null) {
                if ($fila['ubicacion'] === null) {
                    $errores[] = "{$ref}: grupo \"{$fila['grupo']}\" no encontrado.";
                }

                continue;
            }

            $etiqueta = Concepto::etiquetaDeModelo($fila['marca'], $fila['etapa']);

            $conceptos = Concepto::deCatalogoVigente()
                ->where('marca', $fila['marca'])
                ->when($fila['etapa'] !== null, fn ($q) => $q->where('etapa', $fila['etapa']))
                ->where('activo', true)
                ->get();

            if ($conceptos->isEmpty()) {
                $errores[] = "{$ref}: pieza \"{$etiqueta}\" no encontrada.";

                continue;
            }
            if ($conceptos->count() > 1) {
                $etapas = $conceptos->pluck('etapa')->unique();

                $errores[] = $etapas->count() > 1
                    ? "{$ref}: la marca \"{$fila['marca']}\" existe en varias etapas ({$etapas->map(fn (?string $e) => $e ?? 'sin etapa')->implode(', ')}); agrega la columna ETAPA al archivo."
                    : "{$ref}: pieza \"{$etiqueta}\" existe en varias obras (ambigua).";

                continue;
            }
            if ($fila['cantidad'] < 1) {
                $errores[] = "{$ref}: cantidad invalida.";

                continue;
            }
            if ($fila['porcentaje'] <= 0 || $fila['porcentaje'] > 100) {
                $errores[] = "{$ref}: porcentaje invalido (debe ir de 1 a 100).";

                continue;
            }

            $concepto = $conceptos->first();

            // El tope se descuenta dentro del propio archivo: dos renglones del
            // mismo modelo no pueden rebasar juntos lo que falta.
            $clave = $concepto->obra_id.'|'.$concepto->claveModelo();
            $disponible = $topes[$clave] ??= $avance->disponible($concepto);
            $consumo = round($fila['cantidad'] * ($fila['porcentaje'] / 100), 4);

            if ($consumo > $disponible + 0.0001) {
                $errores[] = "{$ref}: ".$this->mensajeDeTope($concepto, $disponible);

                continue;
            }

            Registro::create([
                'fecha' => $fecha,
                'concepto_id' => $concepto->id,
                'grupo_trabajo_id' => $grupo->id,
                'cantidad' => $fila['cantidad'],
                'porcentaje' => $fila['porcentaje'],
            ]);
            $topes[$clave] -= $consumo;
            $importados++;
        }

        if ($errores !== []) {
            return back()
                ->with('success', "Se importaron {$importados} registros.")
                ->withErrors(['csv_file' => implode(' ', $errores)]);
        }

        return back()->with('success', "Se importaron {$importados} registros correctamente.");
    }

    /**
     * Traduce la ubicación del export al grupo que trabaja ahí. La ubicación debe
     * existir en el catálogo y pertenecer a un solo grupo; si no, el renglón se
     * reporta y se salta.
     *
     * @param  list<string>  $errores
     */
    private function grupoDeUbicacion(string $ubicacion, LectorCsvDeProduccion $lector, array &$errores, string $ref): ?GrupoTrabajo
    {
        $buscada = $lector->normalizar($ubicacion);

        $candidata = Ubicacion::with('gruposTrabajo:id,descripcion')
            ->get(['id', 'nombre'])
            ->first(fn (Ubicacion $u) => $lector->normalizar($u->nombre) === $buscada);

        if ($candidata === null) {
            $errores[] = "{$ref}: la ubicacion \"{$ubicacion}\" no esta en el catalogo de modulos.";

            return null;
        }

        $grupos = $candidata->gruposTrabajo;

        if ($grupos->isEmpty()) {
            $errores[] = "{$ref}: la ubicacion \"{$ubicacion}\" no tiene ningun grupo de trabajo asignado.";

            return null;
        }

        if ($grupos->count() > 1) {
            $errores[] = "{$ref}: la ubicacion \"{$ubicacion}\" la trabajan varios grupos ({$grupos->pluck('descripcion')->implode(', ')}).";

            return null;
        }

        return $grupos->first();
    }

    /**
     * Explica el tope con los tres números que el capturista necesita (lo que
     * manda el catálogo, lo ya capturado y lo que queda) y hacia dónde ir: las
     * piezas rehechas se pagan como pago extra, no como producción.
     */
    private function mensajeDeTope(Concepto $concepto, float $disponible): string
    {
        $salida = ' Si son piezas rehechas, regístralas como pago extra.';
        $etiqueta = $concepto->etiquetaModelo();

        if ($disponible <= 0) {
            return "La pieza \"{$etiqueta}\" ya está pagada al 100%: el catálogo pide {$concepto->cantidad} y no queda nada por pagar.".$salida;
        }

        $pendiente = rtrim(rtrim(number_format($disponible, 2, '.', ''), '0'), '.');
        $pagado = rtrim(rtrim(number_format((float) $concepto->cantidad - $disponible, 2, '.', ''), '0'), '.');

        return "La pieza \"{$etiqueta}\" solo tiene {$pendiente} pieza(s) por pagar: el catálogo pide {$concepto->cantidad} y ya se pagaron {$pagado} (contando parcialidades).".$salida;
    }
}
