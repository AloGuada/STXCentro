<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\ConceptoStoreRequest;
use App\Http\Requests\Admin\Prod\ConceptoUpdateRequest;
use App\Models\Concepto;
use App\Models\Obra;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConceptoController extends Controller
{
    public function index(Request $request): Response
    {
        $conceptos = Concepto::query()
            ->with('obra')
            ->when($request->search, fn ($q, $s) => $q->where('marca', 'like', "%{$s}%")
                ->orWhere('descripcion', 'like', "%{$s}%"))
            ->when($request->obra_id, fn ($q, $obraId) => $q->where('obra_id', $obraId))
            ->orderBy('marca')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/prod/conceptos/index', [
            'conceptos' => $conceptos,
            'obras' => Obra::orderBy('no')->get(),
            'filters' => $request->only(['search', 'obra_id']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/prod/conceptos/create', [
            'obras' => Obra::orderBy('no')->get(),
        ]);
    }

    public function store(ConceptoStoreRequest $request): RedirectResponse
    {
        Concepto::create([
            'obra_id' => $request->obra_id,
            'marca' => $request->marca,
            'descripcion' => $request->descripcion,
            'peso_unitario' => $request->peso_unitario,
            'version' => $request->version ?? 1,
            'activo' => $request->boolean('activo', true),
        ]);

        return to_route('admin.prod.conceptos.index');
    }

    public function edit(Concepto $concepto): Response
    {
        $concepto->load(['obra', 'grupoPrecioConceptos.grupoPrecio']);

        return Inertia::render('admin/prod/conceptos/edit', [
            'concepto' => $concepto,
            'obras' => Obra::orderBy('no')->get(),
        ]);
    }

    public function update(ConceptoUpdateRequest $request, Concepto $concepto): RedirectResponse
    {
        $concepto->update([
            'obra_id' => $request->obra_id,
            'marca' => $request->marca,
            'descripcion' => $request->descripcion,
            'peso_unitario' => $request->peso_unitario,
            'version' => $request->version ?? $concepto->version,
            'activo' => $request->boolean('activo', $concepto->activo),
        ]);

        return to_route('admin.prod.conceptos.index');
    }

    public function destroy(Concepto $concepto): RedirectResponse
    {
        if ($concepto->registros()->exists()) {
            return back()->withErrors(['error' => 'No se puede eliminar un concepto que tiene registros asociados.']);
        }

        $concepto->grupoPrecioConceptos()->delete();
        $concepto->delete();

        return to_route('admin.prod.conceptos.index');
    }
}
