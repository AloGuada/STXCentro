<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\PiezaStoreRequest;
use App\Http\Requests\Admin\Prod\PiezaUpdateRequest;
use App\Models\Obra;
use App\Models\Pieza;
use App\Models\Prod\GrupoPrecio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PiezaController extends Controller
{
    public function index(Request $request): Response
    {
        $piezas = Pieza::query()
            ->with('obra')
            ->when($request->search, fn ($q, $s) => $q->where('marca', 'like', "%{$s}%")
                ->orWhere('descripcion', 'like', "%{$s}%"))
            ->when($request->obra_id, fn ($q, $obraId) => $q->where('obra_id', $obraId))
            ->orderBy('marca')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/prod/piezas/index', [
            'piezas' => $piezas,
            'obras' => Obra::orderBy('no')->get(),
            'filters' => $request->only(['search', 'obra_id']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/prod/piezas/create', [
            'obras' => Obra::orderBy('no')->get(),
        ]);
    }

    public function store(PiezaStoreRequest $request): RedirectResponse
    {
        Pieza::create([
            'obra_id' => $request->obra_id,
            'marca' => $request->marca,
            'descripcion' => $request->descripcion,
            'longitud' => $request->longitud,
            'peso' => $request->peso,
            'cantidad' => $request->cantidad,
            'version' => $request->version ?? 1,
        ]);

        return to_route('admin.prod.piezas.index');
    }

    public function edit(Pieza $pieza): Response
    {
        $pieza->load(['obra', 'marcaGrupos.grupoPrecio']);

        return Inertia::render('admin/prod/piezas/edit', [
            'pieza' => $pieza,
            'obras' => Obra::orderBy('no')->get(),
            'grupoPrecios' => GrupoPrecio::orderBy('descripcion')->get(),
        ]);
    }

    public function update(PiezaUpdateRequest $request, Pieza $pieza): RedirectResponse
    {
        $pieza->update([
            'obra_id' => $request->obra_id,
            'marca' => $request->marca,
            'descripcion' => $request->descripcion,
            'longitud' => $request->longitud,
            'peso' => $request->peso,
            'cantidad' => $request->cantidad,
            'version' => $request->version ?? $pieza->version,
        ]);

        return to_route('admin.prod.piezas.index');
    }

    public function destroy(Pieza $pieza): RedirectResponse
    {
        if ($pieza->fabricados()->exists()) {
            return back()->withErrors(['error' => 'No se puede eliminar una pieza que tiene fabricados asociados.']);
        }

        $pieza->marcaGrupos()->delete();
        $pieza->delete();

        return to_route('admin.prod.piezas.index');
    }
}
