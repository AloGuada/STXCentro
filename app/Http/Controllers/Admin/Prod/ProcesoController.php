<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\ProcesoStoreRequest;
use App\Http\Requests\Admin\Prod\ProcesoUpdateRequest;
use App\Models\Prod\Proceso;
use App\Models\Prod\ProcesoEvento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catálogo de procesos que se pagan como destajo, con los eventos del export de
 * planta que los disparan.
 */
class ProcesoController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('admin/prod/procesos/index', [
            'procesos' => Proceso::query()
                ->with('eventos')
                ->withCount('registros')
                ->orderBy('orden')
                ->orderBy('nombre')
                ->get(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/prod/procesos/create');
    }

    public function store(ProcesoStoreRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $proceso = Proceso::create([
                'nombre' => $request->nombre,
                'orden' => $request->orden ?? 0,
                'activo' => $request->boolean('activo', true),
            ]);

            $this->guardarEventos($proceso, $request->input('eventos', []));
        });

        return to_route('admin.prod.procesos.index');
    }

    public function edit(Proceso $proceso): Response
    {
        $proceso->load('eventos');

        return Inertia::render('admin/prod/procesos/edit', [
            'proceso' => $proceso,
        ]);
    }

    public function update(ProcesoUpdateRequest $request, Proceso $proceso): RedirectResponse
    {
        DB::transaction(function () use ($request, $proceso): void {
            $proceso->update([
                'nombre' => $request->nombre,
                'orden' => $request->orden ?? $proceso->orden,
                'activo' => $request->boolean('activo', $proceso->activo),
            ]);

            $this->guardarEventos($proceso, $request->input('eventos', []));
        });

        return to_route('admin.prod.procesos.index');
    }

    public function destroy(Proceso $proceso): RedirectResponse
    {
        if ($proceso->registros()->exists()) {
            return back()->withErrors([
                'error' => 'No se puede eliminar un proceso que ya tiene producción capturada. Desactívalo.',
            ]);
        }

        $proceso->eventos()->delete();
        $proceso->delete();

        return to_route('admin.prod.procesos.index');
    }

    /**
     * Reemplaza la lista de eventos del proceso. Se borran los que ya no vienen
     * para que un número liberado quede disponible para otro proceso.
     *
     * @param  array<int, array{evento: string, descripcion?: ?string}>  $eventos
     */
    private function guardarEventos(Proceso $proceso, array $eventos): void
    {
        $numeros = [];

        foreach ($eventos as $evento) {
            $numero = trim((string) ($evento['evento'] ?? ''));

            if ($numero === '') {
                continue;
            }

            $numeros[] = $numero;

            ProcesoEvento::updateOrCreate(
                ['evento' => $numero],
                ['proceso_id' => $proceso->id, 'descripcion' => $evento['descripcion'] ?? null],
            );
        }

        $proceso->eventos()->whereNotIn('evento', $numeros ?: [''])->delete();
    }
}
