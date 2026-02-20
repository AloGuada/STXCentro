<?php

namespace App\Http\Controllers\Admin\Infra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Infra\TurnoStoreRequest;
use App\Http\Requests\Admin\Infra\TurnoUpdateRequest;
use App\Models\Infra\Bomba;
use App\Models\Infra\Compresor;
use App\Models\Infra\Ptar;
use App\Models\Infra\Tanque;
use App\Models\Infra\Transformador;
use App\Models\Infra\Turno;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TurnoController extends Controller
{
    public function index(Request $request): Response
    {
        $turnos = Turno::query()
            ->when($request->search, fn ($q, $s) => $q->where('nombre', 'like', "%{$s}%"))
            ->with('diasSemana')
            ->orderBy('orden')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/infra/turnos/index', [
            'turnos' => $turnos,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/infra/turnos/create');
    }

    public function store(TurnoStoreRequest $request): RedirectResponse
    {
        $turno = Turno::create([
            'nombre' => $request->nombre,
            'hora_inicio' => $request->hora_inicio,
            'hora_fin' => $request->hora_fin,
            'orden' => $request->orden ?? 0,
            'activo' => true,
        ]);

        $turno->diasSemana()->createMany(
            collect($request->dias_semana)->map(fn ($dia) => ['dia_semana' => $dia])->all()
        );

        return to_route('admin.infra.turnos.index');
    }

    public function edit(Turno $turno): Response
    {
        $turno->load('diasSemana');

        return Inertia::render('admin/infra/turnos/edit', [
            'turno' => $turno,
        ]);
    }

    public function update(TurnoUpdateRequest $request, Turno $turno): RedirectResponse
    {
        $turno->update([
            'nombre' => $request->nombre,
            'hora_inicio' => $request->hora_inicio,
            'hora_fin' => $request->hora_fin,
            'orden' => $request->orden ?? 0,
        ]);

        $turno->diasSemana()->delete();
        $turno->diasSemana()->createMany(
            collect($request->dias_semana)->map(fn ($dia) => ['dia_semana' => $dia])->all()
        );

        return to_route('admin.infra.turnos.index');
    }

    public function destroy(Turno $turno): RedirectResponse
    {
        $hasRecords = Compresor::where('infra_turno_id', $turno->id)->exists()
            || Bomba::where('infra_turno_id', $turno->id)->exists()
            || Transformador::where('infra_turno_id', $turno->id)->exists()
            || Tanque::where('infra_turno_id', $turno->id)->exists()
            || Ptar::where('infra_turno_id', $turno->id)->exists();

        if ($hasRecords) {
            return back()->withErrors(['delete' => 'No se puede eliminar un turno que tiene registros asociados.']);
        }

        $turno->delete();

        return to_route('admin.infra.turnos.index');
    }
}
