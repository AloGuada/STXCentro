<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\ResumenCeldaRequest;
use App\Http\Requests\Admin\Cotiz\ResumenCoeficienteRequest;
use App\Http\Requests\Admin\Cotiz\ResumenColumnaSueldoRequest;
use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraResumenCeldaOverride;
use App\Models\Cotiz\ObraResumenCoeficiente;
use App\Models\Cotiz\ResumenBloqueColor;
use App\Models\Cotiz\ResumenColumna;
use App\Services\Cotiz\ResumenCalculator;
use App\Services\Cotiz\ResumenColumnaSync;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Resumen de Proyecto (M038, Fase 5): matriz filas × columnas auto-derivada. Una columna
 * por tarjeta (auto-sincronizada). El cálculo es autoritativo en PHP (ResumenCalculator).
 */
class ResumenController extends Controller
{
    public function index(Obra $obra, ResumenCalculator $calculator, ResumenColumnaSync $sync): Response
    {
        $sync->sincronizar($obra);

        $resultado = $calculator->calcular($obra->fresh());

        return Inertia::render('admin/cotiz/resumen/index', [
            'obra' => $obra->only(['id', 'nombre', 'op']),
            'filas' => $resultado['filas'],
            'columnas' => $resultado['columnas'],
            'matriz' => $resultado['matriz'],
            'overrides' => $resultado['overrides'],
            'totalesPorFila' => $resultado['totales_por_fila'],
            'obraTotales' => $resultado['obra_totales'],
            'importeTotalVenta' => $resultado['importe_total_venta'],
            'bloqueColores' => ResumenBloqueColor::query()->pluck('color', 'bloque'),
        ]);
    }

    public function updateCelda(ResumenCeldaRequest $request, Obra $obra): RedirectResponse
    {
        $data = $request->validated();

        if ($data['coef'] === null || $data['coef'] === '') {
            ObraResumenCeldaOverride::query()
                ->where('obra_id', $obra->id)
                ->where('fila_id', $data['fila_id'])
                ->where('columna_id', $data['columna_id'])
                ->delete();

            return back();
        }

        ObraResumenCeldaOverride::query()->updateOrCreate(
            ['obra_id' => $obra->id, 'fila_id' => $data['fila_id'], 'columna_id' => $data['columna_id']],
            ['coef' => $data['coef']],
        );

        return back();
    }

    public function updateCoeficiente(ResumenCoeficienteRequest $request, Obra $obra): RedirectResponse
    {
        $data = $request->validated();

        if ($data['coef'] === null || $data['coef'] === '') {
            ObraResumenCoeficiente::query()
                ->where('obra_id', $obra->id)
                ->where('fila_id', $data['fila_id'])
                ->delete();

            return back();
        }

        ObraResumenCoeficiente::query()->updateOrCreate(
            ['obra_id' => $obra->id, 'fila_id' => $data['fila_id']],
            ['coef' => $data['coef']],
        );

        return back();
    }

    public function updateColumnaSueldo(ResumenColumnaSueldoRequest $request, ResumenColumna $columna): RedirectResponse
    {
        $columna->update(['sueldo_mo_pza' => $request->validated('sueldo_mo_pza')]);

        return back();
    }
}
