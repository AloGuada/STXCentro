<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\PartidaStoreRequest;
use App\Http\Requests\Admin\Cob\PartidaUpdateRequest;
use App\Models\Cob\Partida;
use App\Models\Costos\Rubro;
use App\Models\Obra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PartidaController extends Controller
{
    public function create(Obra $obra): Response
    {
        return Inertia::render('admin/cob/partidas/create', [
            'obra' => $obra,
        ]);
    }

    public function store(PartidaStoreRequest $request, Obra $obra): RedirectResponse
    {
        $partida = $obra->partidas()->create($request->validated());

        $this->asegurarPresupuestoAdicional($obra, $partida);

        return to_route('admin.cob.obras.show', $obra);
    }

    public function edit(Obra $obra, Partida $partida): Response
    {
        return Inertia::render('admin/cob/partidas/edit', [
            'obra' => $obra,
            'partida' => $partida,
        ]);
    }

    public function update(PartidaUpdateRequest $request, Obra $obra, Partida $partida): RedirectResponse
    {
        $partida->update($request->validated());

        $this->asegurarPresupuestoAdicional($obra, $partida);

        return to_route('admin.cob.obras.show', $obra);
    }

    public function destroy(Obra $obra, Partida $partida): RedirectResponse
    {
        $partida->delete();

        return back();
    }

    /**
     * Al marcar una partida como adicional, le asigna su número correlativo
     * (adX por orden de creación) y crea su presupuesto propio con todos los
     * centros de costo del catálogo, espejo de {@see Obra::booted()}.
     */
    private function asegurarPresupuestoAdicional(Obra $obra, Partida $partida): void
    {
        if (! $partida->es_adicional) {
            return;
        }

        DB::transaction(function () use ($obra, $partida) {
            if ($partida->numero_adicional === null) {
                $max = (int) Partida::query()
                    ->where('obra_id', $obra->id)
                    ->where('es_adicional', true)
                    ->max('numero_adicional');

                $partida->update(['numero_adicional' => $max + 1]);
            }

            if ($partida->obraRubros()->exists()) {
                return;
            }

            $rubroIds = Rubro::query()
                ->where('ambito', $obra->es_planta ? 'planta' : 'obra')
                ->pluck('id');

            $partida->obraRubros()->createMany(
                $rubroIds->map(fn ($rubroId) => [
                    'obra_id' => $obra->id,
                    'rubro_id' => $rubroId,
                    'presupuestado' => 0,
                    'acumulado' => 0,
                ])->all()
            );
        });
    }
}
