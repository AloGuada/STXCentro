<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\RegistroImportCsvRequest;
use App\Http\Requests\Admin\Prod\RegistroStoreRequest;
use App\Models\Prod\Destajo;
use App\Models\Prod\GrupoPrecioSubproceso;
use App\Models\Prod\Pieza;
use App\Models\Prod\Proceso;
use App\Models\Prod\Registro;
use App\Services\Prod\AvanceDePiezas;
use App\Services\Prod\LectorCsvDeProduccion;
use App\Services\Prod\ModalidadDePago;
use App\Services\Prod\PlanDeImportacionCsv;
use App\Services\Prod\ProcesosPagadosPorObra;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class RegistroController extends Controller
{
    /**
     * Captura manual: una o varias piezas (QS) del mismo modelo, en un proceso y
     * —si su grupo de precios paga por pasos— en un subproceso.
     * Las que no caben en su tope se reportan y el resto sí se guarda.
     */
    public function store(
        RegistroStoreRequest $request,
        Destajo $destajo,
        AvanceDePiezas $avance,
        ProcesosPagadosPorObra $procesos,
        ModalidadDePago $modalidad,
    ): RedirectResponse {
        if ($destajo->cerrado) {
            return back()->withErrors(['error' => 'No se puede capturar produccion en un destajo cerrado.']);
        }

        if ($request->date('fecha')->lt($destajo->fecha_inicio) || $request->date('fecha')->gt($destajo->fecha_fin)) {
            return back()->withErrors(['fecha' => 'La fecha debe estar dentro del periodo del destajo.']);
        }

        $proceso = Proceso::findOrFail($request->integer('proceso_id'));
        $porcentaje = (float) ($request->porcentaje ?? 100);
        $subproceso = $request->filled('subproceso_id')
            ? GrupoPrecioSubproceso::find($request->integer('subproceso_id'))
            : null;
        // Sin deduplicar, el mismo QS repetido en el payload se cobraria dos
        // veces: el tope se resuelve contra una foto del avance que no ve lo que
        // se acaba de guardar en este mismo bucle.
        $piezas = Pieza::with(['marca', 'catalogo'])
            ->findMany(array_unique($request->input('piezas', [])));

        if ($error = $procesos->error($piezas, $proceso)) {
            return back()->withErrors(['proceso_id' => $error]);
        }

        $rechazadas = [];
        $guardadas = 0;

        foreach ($piezas as $pieza) {
            // El subproceso pone el precio, asi que capturarlo cruzado pagaria
            // una tarifa que nadie autorizo para esa marca.
            if ($error = $modalidad->errorDeSubproceso($pieza, (int) $proceso->id, $subproceso)) {
                $rechazadas[] = $error;

                continue;
            }

            // En un grupo por kilo el subproceso no se guarda aunque venga en el
            // payload: la guarda de arriba ya lo rechazo, esto solo lo hace explicito.
            $subprocesoId = $modalidad->piezaPagaPorSubproceso($pieza) ? (int) $subproceso->id : null;

            if (! $avance->cabe($pieza, $proceso->id, $porcentaje, $subprocesoId)) {
                $rechazadas[] = $avance->mensajeDeTope(
                    $pieza,
                    $proceso,
                    $avance->disponible($pieza, $proceso->id, $subprocesoId),
                    $subprocesoId !== null ? $subproceso : null,
                );

                continue;
            }

            Registro::create([
                'fecha' => $request->date('fecha'),
                'pieza_id' => $pieza->id,
                'proceso_id' => $proceso->id,
                'subproceso_id' => $subprocesoId,
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
     * Paso 1: analiza el archivo y devuelve lo que pasaria, sin escribir nada.
     *
     * Es lo que alimenta el modal de revision. Sale del mismo armador que usa el
     * guardado, para que lo que se ensena y lo que se escribe no puedan diferir.
     */
    public function analizarCsv(
        RegistroImportCsvRequest $request,
        Destajo $destajo,
        LectorCsvDeProduccion $lector,
        PlanDeImportacionCsv $planificador,
    ): JsonResponse {
        if ($error = $this->errorDeArchivo($request, $destajo)) {
            return response()->json(['errors' => $error], 422);
        }

        ['formato' => $formato, 'filas' => $filas] = $lector->leer($request->file('csv_file')->getRealPath());

        if ($filas === []) {
            return response()->json(['errors' => ['csv_file' => [$this->mensajeDeArchivoVacio($formato)]]], 422);
        }

        return response()->json($planificador->armar($filas, $formato)->toArray());
    }

    /**
     * Paso 2: aplica el archivo. Acepta el export de avance de planta (Proceso,
     * Ubicacion y QS) o un CSV a mano con GRUPO, QS, PROCESO y opcionalmente
     * PORCENTAJE. Todos los renglones toman la fecha del formulario.
     *
     * Se vuelve a armar el plan en vez de confiar en el que se reviso: entre la
     * revision y el guardado alguien mas pudo capturar, y el tope tiene que
     * decidirse contra lo que hay ahora.
     */
    public function importCsv(
        RegistroImportCsvRequest $request,
        Destajo $destajo,
        LectorCsvDeProduccion $lector,
        PlanDeImportacionCsv $planificador,
    ): RedirectResponse {
        if ($error = $this->errorDeArchivo($request, $destajo)) {
            return back()->withErrors(array_map(fn (array $mensajes): string => $mensajes[0], $error));
        }

        ['formato' => $formato, 'filas' => $filas] = $lector->leer($request->file('csv_file')->getRealPath());

        if ($filas === []) {
            return back()->withErrors(['csv_file' => $this->mensajeDeArchivoVacio($formato)]);
        }

        $plan = $planificador->armar($filas, $formato);

        // Un archivo lleno de eventos que no pagan destajo se salta entero sin un
        // solo error. Decir "0 registros correctamente" haria pensar que la
        // semana ya esta capturada.
        if ($plan->vacio()) {
            $motivos = $plan->motivos();

            return back()->withErrors([
                'csv_file' => $motivos === []
                    ? 'El archivo no trae ningun movimiento de los eventos configurados en los procesos.'
                    : implode(' ', $motivos),
            ]);
        }

        $fecha = $request->date('fecha');
        $aplicables = $plan->aplicables();

        DB::transaction(function () use ($aplicables, $fecha): void {
            foreach ($aplicables as $renglon) {
                Registro::create([
                    'fecha' => $fecha,
                    'pieza_id' => $renglon['pieza_id'],
                    'proceso_id' => $renglon['proceso_id'],
                    'grupo_trabajo_id' => $renglon['grupo_trabajo_id'],
                    'porcentaje' => $renglon['porcentaje'],
                ]);
            }
        });

        $importados = count($aplicables);
        $errores = (int) $plan->resumen()['errores'];
        $omitidas = (int) $plan->resumen()['omitidas'];

        // Lo omitido (el mismo escaneo repetido) es benigno y ya se vio en la
        // revision: se cuenta en el mensaje y ya. Lo rechazado si se sigue
        // reportando como error, porque es algo que alguien tiene que atender.
        $exito = $omitidas > 0
            ? "Se importaron {$importados} registros; {$omitidas} renglon(es) se omitieron por repetidos."
            : "Se importaron {$importados} registros correctamente.";

        if ($errores > 0) {
            return back()
                ->with('success', "Se importaron {$importados} registros.")
                ->withErrors(['csv_file' => implode(' ', $plan->motivos('error'))]);
        }

        return back()->with('success', $exito);
    }

    /**
     * Las dos guardas que comparten analizar y aplicar, en forma de errores por
     * campo para poder devolverlas como redirect o como JSON.
     *
     * @return array<string, list<string>>|null
     */
    private function errorDeArchivo(RegistroImportCsvRequest $request, Destajo $destajo): ?array
    {
        if ($destajo->cerrado) {
            return ['error' => ['No se puede importar produccion en un destajo cerrado.']];
        }

        $fecha = $request->date('fecha');

        if ($fecha->lt($destajo->fecha_inicio) || $fecha->gt($destajo->fecha_fin)) {
            return ['fecha' => ['La fecha debe estar dentro del periodo del destajo.']];
        }

        return null;
    }

    private function mensajeDeArchivoVacio(string $formato): string
    {
        return $formato === LectorCsvDeProduccion::FORMATO_EXPORT
            ? 'El archivo no trae ningun movimiento con QS de los eventos configurados.'
            : 'El archivo no trae renglones para importar.';
    }
}
