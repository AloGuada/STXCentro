<?php

namespace App\Http\Controllers\Admin\Rh;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Rh\RequisicionStoreRequest;
use App\Http\Requests\Admin\Rh\RequisicionUpdateRequest;
use App\Models\Rh\Candidatura;
use App\Models\Rh\Persona;
use App\Models\Rh\Puesto;
use App\Models\Rh\Requisicion;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RequisicionController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('rh.requisiciones.ver');

        $requisiciones = Requisicion::query()
            ->with('puesto')
            ->when($request->search, fn ($q, $s) => $q
                ->where('folio', 'like', "%{$s}%")
                ->orWhereHas('puesto', fn ($pq) => $pq->where('nombre', 'like', "%{$s}%"))
            )
            ->orderByDesc('created_at')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/rh/requisiciones/index', [
            'requisiciones' => $requisiciones,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('rh.requisiciones.crear');

        return Inertia::render('admin/rh/requisiciones/create', [
            'puestos' => Puesto::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(RequisicionStoreRequest $request): RedirectResponse
    {
        $this->authorize('rh.requisiciones.crear');

        $folio = 'REQ-'.date('Y').'-'.str_pad((string) (Requisicion::whereYear('created_at', date('Y'))->count() + 1), 4, '0', STR_PAD_LEFT);

        Requisicion::create(array_merge($request->validated(), [
            'folio' => $folio,
            'estado' => 'borrador',
            'fecha_creacion' => now(),
        ]));

        return to_route('admin.rh.requisiciones.index');
    }

    public function show(Requisicion $requisicion): Response
    {
        $this->authorize('rh.requisiciones.ver');

        $requisicion->load(['puesto.departamento', 'extra', 'candidaturas.persona']);

        return Inertia::render('admin/rh/requisiciones/show', [
            'requisicion' => $requisicion,
        ]);
    }

    public function edit(Requisicion $requisicion): Response
    {
        $this->authorize('rh.requisiciones.editar');

        $requisicion->load('extra');

        return Inertia::render('admin/rh/requisiciones/edit', [
            'requisicion' => $requisicion,
            'puestos' => Puesto::orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function update(RequisicionUpdateRequest $request, Requisicion $requisicion): RedirectResponse
    {
        $this->authorize('rh.requisiciones.editar');

        $requisicion->update($request->validated());

        if ($request->has('extra')) {
            $requisicion->extra()->updateOrCreate(
                ['requisicion_id' => $requisicion->id],
                $request->extra,
            );
        }

        return to_route('admin.rh.requisiciones.index');
    }

    public function destroy(Requisicion $requisicion): RedirectResponse
    {
        $this->authorize('rh.requisiciones.eliminar');

        if ($requisicion->candidaturas()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar una requisicion con candidaturas.']);
        }

        $requisicion->delete();

        return to_route('admin.rh.requisiciones.index');
    }

    public function candidatos(Requisicion $requisicion): Response
    {
        $this->authorize('rh.candidaturas.ver');

        $requisicion->load(['puesto', 'candidaturas.persona']);
        $personasDisponibles = Persona::whereDoesntHave('candidaturas', fn ($q) => $q->where('requisicion_id', $requisicion->id))
            ->orderBy('apellido')
            ->get(['id', 'nombre', 'apellido']);

        return Inertia::render('admin/rh/requisiciones/candidatos', [
            'requisicion' => $requisicion,
            'personasDisponibles' => $personasDisponibles,
        ]);
    }

    public function storeCandidatura(Request $request, Requisicion $requisicion): RedirectResponse
    {
        $this->authorize('rh.candidaturas.crear');

        $request->validate([
            'persona_id' => ['required', 'exists:rh_personas,id'],
            'notas' => ['nullable', 'string'],
        ]);

        Candidatura::create([
            'requisicion_id' => $requisicion->id,
            'persona_id' => $request->persona_id,
            'fecha_aplicacion' => now(),
            'notas' => $request->notas,
        ]);

        return back();
    }

    public function destroyCandidatura(Requisicion $requisicion, Candidatura $candidatura): RedirectResponse
    {
        $this->authorize('rh.candidaturas.crear');

        $candidatura->delete();

        return back();
    }
}
