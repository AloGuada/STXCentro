<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Qal\ProgramacionPiezaStoreRequest;
use App\Models\Prod\Pieza;
use App\Models\Qal\Programacion;
use App\Models\Qal\ProgramacionPieza;
use App\Services\Qal\AvanceProduccion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

/**
 * Las piezas del plan de la semana: se agregan con su grupo de trabajo y su
 * módulo, y cada una se quita por su cuenta.
 *
 * No hay «guardar el plan»: agregar ya guarda. Todo esto es del plan abierto,
 * que es un borrador; cerrado es el compromiso de la semana y ya no se le
 * agrega ni se le quita nada.
 */
class ProgramacionPiezaController extends Controller
{
    public function store(ProgramacionPiezaStoreRequest $request): RedirectResponse
    {
        [$anio, $numero] = AvanceProduccion::partesDeSemana($request->string('semana')->value());

        $piezas = Pieza::query()
            ->with('marca:id,marca,lote')
            ->whereKey($request->input('piezas'))
            ->orderBy('id')
            ->get();

        $cerrado = Programacion::query()
            ->where([
                'obra_id' => $request->integer('obra_id'),
                'fase' => AvanceProduccion::fase($request->string('fase')->value())->value,
                'anio' => $anio,
                'semana' => $numero,
            ])
            ->whereNotNull('cerrada_at')
            ->exists();

        if ($cerrado) {
            return back()->withErrors(['piezas' => "El plan de la semana {$numero} ya está cerrado: no se le agregan piezas."]);
        }

        $agregadas = DB::transaction(function () use ($request, $anio, $numero, $piezas): int {
            $programacion = Programacion::query()->firstOrCreate([
                'obra_id' => $request->integer('obra_id'),
                'fase' => AvanceProduccion::fase($request->string('fase')->value())->value,
                'anio' => $anio,
                'semana' => $numero,
            ], ['capturista_id' => $request->user()?->getKey()]);

            $yaEnElPlan = $programacion->piezas()->pluck('qr')->flip();
            $nuevas = $piezas->reject(fn (Pieza $pieza): bool => $yaEnElPlan->has($pieza->qr));

            $programacion->piezas()->createMany($nuevas->map(fn (Pieza $pieza): array => [
                'pieza_id' => $pieza->id,
                'concepto_id' => $pieza->concepto_id,
                'marca' => (string) $pieza->marca?->marca,
                'lote' => $pieza->marca?->lote,
                'qr' => $pieza->qr,
                'qs' => $pieza->qs,
                'grupo_trabajo_id' => $request->integer('grupo_trabajo_id'),
                'modulo' => trim((string) $request->input('modulo', '')) ?: null,
            ])->all());

            return $nuevas->count();
        });

        $repetidas = $piezas->count() - $agregadas;

        return back()->with('success', "Semana {$numero}: {$agregadas} pieza(s) agregadas al plan."
            .($repetidas > 0 ? " {$repetidas} ya estaban." : ''));
    }

    /**
     * Sólo se quita de un plan abierto: lo cerrado es lo que se prometió, y lo
     * que no se hizo se arrastra hasta que se fabrique.
     */
    public function destroy(ProgramacionPieza $programacionPieza): RedirectResponse
    {
        $programacion = $programacionPieza->programacion;

        if ($programacion->cerrada()) {
            return back()->withErrors(['plan' => 'El plan de esa semana ya está cerrado: sus piezas no se quitan.']);
        }

        $programacionPieza->delete();

        if ($programacion->notas === null && ! $programacion->piezas()->exists()) {
            $programacion->delete();
        }

        return back()->with('success', "Se quitó del plan la pieza {$programacionPieza->marca} (QR {$programacionPieza->qr}).");
    }
}
