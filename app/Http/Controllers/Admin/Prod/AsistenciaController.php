<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\AsistenciaStoreRequest;
use App\Models\Prod\Asistencia;
use App\Models\Prod\Destajo;
use App\Services\Prod\AsistenciaDelDestajo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AsistenciaController extends Controller
{
    public function show(Destajo $destajo, AsistenciaDelDestajo $asistencia): Response
    {
        return Inertia::render('admin/prod/destajos/asistencia', [
            'destajo' => $destajo,
            'grupos' => $asistencia->gruposCapturables($destajo),
            // Sólo estos bloquean el cierre: son los que van a cobrar algo.
            'participantes' => $asistencia->gruposParticipantes($destajo)->pluck('id'),
            'dias' => $asistencia->dias($destajo),
            'marcas' => $asistencia->marcas($destajo),
        ]);
    }

    public function store(AsistenciaStoreRequest $request, Destajo $destajo): RedirectResponse
    {
        if ($destajo->cerrado) {
            return back()->withErrors(['error' => 'No se puede modificar la asistencia de un destajo cerrado.']);
        }

        $inicio = $destajo->fecha_inicio->format('Y-m-d');
        $fin = $destajo->fecha_fin->format('Y-m-d');

        $marcas = collect($request->validated('marcas'))
            ->filter(fn (array $marca) => $marca['fecha'] >= $inicio && $marca['fecha'] <= $fin);

        if ($marcas->isEmpty()) {
            return back()->withErrors(['marcas' => 'Las fechas deben estar dentro del periodo del destajo.']);
        }

        DB::transaction(function () use ($marcas, $destajo): void {
            foreach ($marcas as $marca) {
                Asistencia::updateOrCreate(
                    [
                        'grupo_empleado_id' => $marca['grupo_empleado_id'],
                        // Carbon, no string: el cast `date` guarda la fecha como
                        // "Y-m-d H:i:s", así que buscar por "Y-m-d" nunca
                        // encontraría la fila y chocaría contra el unique.
                        'fecha' => Carbon::parse($marca['fecha'])->startOfDay(),
                    ],
                    [
                        'destajo_id' => $destajo->id,
                        'estado' => $marca['estado'],
                    ],
                );
            }
        });

        return back()->with('success', 'Asistencia guardada.');
    }
}
