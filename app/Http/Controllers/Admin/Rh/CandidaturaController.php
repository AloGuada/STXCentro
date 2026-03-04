<?php

namespace App\Http\Controllers\Admin\Rh;

use App\Http\Controllers\Controller;
use App\Models\Rh\Candidatura;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CandidaturaController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('rh.candidaturas.ver');

        $candidaturas = Candidatura::query()
            ->with(['requisicion.puesto', 'persona'])
            ->when($request->search, fn ($q, $s) => $q
                ->whereHas('persona', fn ($pq) => $pq
                    ->where('nombre', 'like', "%{$s}%")
                    ->orWhere('apellido', 'like', "%{$s}%")
                )
            )
            ->orderByDesc('fecha_aplicacion')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/rh/candidaturas/index', [
            'candidaturas' => $candidaturas,
            'filters' => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('rh.candidaturas.crear');

        $request->validate([
            'requisicion_id' => ['required', 'exists:rh_requisiciones,id'],
            'persona_id' => ['required', 'exists:rh_personas,id'],
            'notas' => ['nullable', 'string'],
        ]);

        Candidatura::create([
            'requisicion_id' => $request->requisicion_id,
            'persona_id' => $request->persona_id,
            'fecha_aplicacion' => now(),
            'notas' => $request->notas,
        ]);

        return back();
    }

    public function destroy(Candidatura $candidatura): RedirectResponse
    {
        $this->authorize('rh.candidaturas.crear');

        $candidatura->delete();

        return back();
    }
}
