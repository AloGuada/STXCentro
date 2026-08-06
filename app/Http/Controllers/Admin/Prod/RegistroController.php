<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\RegistroImportCsvRequest;
use App\Http\Requests\Admin\Prod\RegistroStoreRequest;
use App\Models\Obra;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoTrabajo;
use App\Models\Prod\Pieza;
use App\Models\Prod\Proceso;
use App\Models\Prod\ProcesoEvento;
use App\Models\Prod\Registro;
use App\Models\Prod\Ubicacion;
use App\Services\Prod\AvanceDePiezas;
use App\Services\Prod\LectorCsvDeProduccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Collection;

class RegistroController extends Controller
{
    /**
     * Captura manual: una o varias piezas (QS) del mismo modelo, en un proceso.
     * Las que no caben en su tope se reportan y el resto sí se guarda.
     */
    public function store(RegistroStoreRequest $request, Destajo $destajo, AvanceDePiezas $avance): RedirectResponse
    {
        if ($destajo->cerrado) {
            return back()->withErrors(['error' => 'No se puede capturar produccion en un destajo cerrado.']);
        }

        if ($request->date('fecha')->lt($destajo->fecha_inicio) || $request->date('fecha')->gt($destajo->fecha_fin)) {
            return back()->withErrors(['fecha' => 'La fecha debe estar dentro del periodo del destajo.']);
        }

        $proceso = Proceso::findOrFail($request->integer('proceso_id'));
        $porcentaje = (float) ($request->porcentaje ?? 100);
        // Sin deduplicar, el mismo QS repetido en el payload se cobraria dos
        // veces: el tope se resuelve contra una foto del avance que no ve lo que
        // se acaba de guardar en este mismo bucle.
        $piezas = Pieza::with(['marca', 'catalogo'])
            ->findMany(array_unique($request->input('piezas', [])));

        if ($error = $this->errorDeProcesoEnObra($piezas, $proceso)) {
            return back()->withErrors(['proceso_id' => $error]);
        }

        $rechazadas = [];
        $guardadas = 0;

        foreach ($piezas as $pieza) {
            if (! $avance->cabe($pieza, $proceso->id, $porcentaje)) {
                $rechazadas[] = $this->mensajeDeTope($pieza, $proceso, $avance->disponible($pieza, $proceso->id));

                continue;
            }

            Registro::create([
                'fecha' => $request->date('fecha'),
                'pieza_id' => $pieza->id,
                'proceso_id' => $proceso->id,
                'grupo_trabajo_id' => $request->integer('grupo_trabajo_id'),
                'porcentaje' => $porcentaje,
            ]);
            $guardadas++;
        }

        if ($rechazadas !== []) {
            return back()
                ->with('success', "Se capturaron {$guardadas} pieza(s).")
                ->withErrors(['piezas' => implode(' ', $rechazadas)]);
        }

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
     * Carga masiva de produccion. Acepta el export de avance de planta (Proceso,
     * Ubicacion y QS) o un CSV a mano con GRUPO, QS y opcionalmente PROCESO y
     * PORCENTAJE. Todos los renglones toman la fecha del formulario.
     *
     * La pieza se resuelve por QS contra el catalogo vigente: exacto, sin
     * adivinar por marca. El numero de evento decide el proceso.
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
                ? 'El archivo no trae ningun movimiento con QS de los eventos configurados.'
                : 'El archivo no trae renglones para importar.';

            return back()->withErrors(['csv_file' => $vacio]);
        }

        $procesosPorEvento = ProcesoEvento::with('proceso')->get()->keyBy('evento');
        $procesosPorNombre = Proceso::activos()->get()->keyBy(fn (Proceso $p) => $lector->normalizar($p->nombre));

        $importados = 0;
        $errores = [];
        /** @var array<string, float> $topes */
        $topes = [];

        foreach ($filas as $fila) {
            $ref = $fila['referencia'];

            $proceso = $fila['evento'] !== null
                ? $procesosPorEvento->get($fila['evento'])?->proceso
                : $procesosPorNombre->get($lector->normalizar((string) $fila['proceso']));

            if ($proceso === null) {
                // Los eventos que no pagan destajo (corte, inspeccion, embarque)
                // son la mayoria del export: se saltan sin ruido.
                if ($fila['evento'] !== null) {
                    continue;
                }

                $errores[] = "{$ref}: proceso \"{$fila['proceso']}\" no encontrado.";

                continue;
            }

            $grupo = $fila['ubicacion'] !== null
                ? $this->grupoDeUbicacion($fila['ubicacion'], $lector, $errores, $ref)
                : GrupoTrabajo::where('descripcion', $fila['grupo'])->first();

            if ($grupo === null) {
                if ($fila['ubicacion'] === null) {
                    $errores[] = "{$ref}: grupo \"{$fila['grupo']}\" no encontrado.";
                }

                continue;
            }

            // El QR identifica sin ambiguedad; el QS puede repetirse entre lotes,
            // asi que solo se usa cuando el archivo no trae QR.
            $porQr = ($fila['qr'] ?? null) !== null;
            $identificador = $porQr ? "QR {$fila['qr']}" : "QS {$fila['qs']}";

            $piezas = Pieza::with(['marca', 'catalogo'])
                ->deCatalogoVigente()
                ->when($porQr, fn ($q) => $q->where('qr', $fila['qr']))
                ->unless($porQr, fn ($q) => $q->where('qs', $fila['qs']))
                ->where('activo', true)
                ->get();

            if ($piezas->isEmpty()) {
                $errores[] = "{$ref}: la pieza {$identificador} no esta en ningun catalogo vigente.";

                continue;
            }
            if ($piezas->count() > 1) {
                $errores[] = $porQr
                    ? "{$ref}: el {$identificador} existe en varias obras (ambiguo)."
                    : "{$ref}: el {$identificador} existe en varias piezas (ambiguo); el archivo tiene que traer el QR.";

                continue;
            }
            if ($fila['porcentaje'] <= 0 || $fila['porcentaje'] > 100) {
                $errores[] = "{$ref}: porcentaje invalido (debe ir de 1 a 100).";

                continue;
            }

            $pieza = $piezas->first();

            if ($error = $this->errorDeProcesoEnObra(collect([$pieza]), $proceso)) {
                $errores[] = "{$ref}: {$error}";

                continue;
            }

            // El tope se descuenta dentro del propio archivo: dos renglones de la
            // misma pieza y proceso no pueden rebasar juntos lo que falta.
            $clave = $pieza->id.'|'.$proceso->id;
            $disponible = $topes[$clave] ??= $avance->disponible($pieza, $proceso->id);
            $consumo = round($fila['porcentaje'] / 100, 4);

            if ($consumo > $disponible + 0.0001) {
                $errores[] = "{$ref}: ".$this->mensajeDeTope($pieza, $proceso, $disponible);

                continue;
            }

            Registro::create([
                'fecha' => $fecha,
                'pieza_id' => $pieza->id,
                'proceso_id' => $proceso->id,
                'grupo_trabajo_id' => $grupo->id,
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

        // Un archivo lleno de eventos que no pagan destajo se salta entero sin
        // un solo error. Decir "0 registros correctamente" haria pensar que la
        // semana ya esta capturada.
        if ($importados === 0) {
            return back()->withErrors([
                'csv_file' => 'El archivo no trae ningun movimiento de los eventos configurados en los procesos.',
            ]);
        }

        return back()->with('success', "Se importaron {$importados} registros correctamente.");
    }

    /**
     * Una obra sólo paga los procesos que tiene configurados: capturar pintura
     * en una obra que sólo suelda inventaría dinero que nadie presupuestó.
     *
     * @param  Collection<int, Pieza>  $piezas
     */
    private function errorDeProcesoEnObra(Collection $piezas, Proceso $proceso): ?string
    {
        $obraIds = $piezas->map(fn (Pieza $p) => (int) $p->catalogo?->obra_id)->filter()->unique();

        $conElProceso = Obra::query()
            ->whereIn('id', $obraIds)
            ->whereHas('procesos', fn ($q) => $q->where('prod_procesos.id', $proceso->id))
            ->pluck('id');

        return $obraIds->diff($conElProceso)->isEmpty()
            ? null
            : "La obra no paga el proceso \"{$proceso->nombre}\"; configúralo en la obra antes de capturar.";
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
     * Explica el tope: una pieza vale 1 en cada proceso, así que lo que queda es
     * una fracción. Las piezas rehechas se pagan como pago extra.
     */
    private function mensajeDeTope(Pieza $pieza, Proceso $proceso, float $disponible): string
    {
        $salida = ' Si es una pieza rehecha, regístrala como pago extra.';
        $etiqueta = $pieza->etiqueta();

        if ($disponible <= 0) {
            return "La pieza {$etiqueta} ya está pagada al 100% en {$proceso->nombre}.".$salida;
        }

        $pendiente = rtrim(rtrim(number_format($disponible * 100, 2, '.', ''), '0'), '.');

        return "La pieza {$etiqueta} sólo tiene {$pendiente}% por pagar en {$proceso->nombre}.".$salida;
    }
}
