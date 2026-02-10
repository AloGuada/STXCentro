<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\GrupoPrecioStoreRequest;
use App\Http\Requests\Admin\Prod\GrupoPrecioUpdateRequest;
use App\Models\Obra;
use App\Models\Prod\GrupoPrecio;
use App\Models\Prod\MarcaGrupo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class GrupoPrecioController extends Controller
{
    public function index(Request $request): Response
    {
        $obras = Obra::query()
            ->withCount('piezas')
            ->when($request->search, fn ($q, $s) => $q->where('no', 'like', "%{$s}%")
                ->orWhere('descripcion', 'like', "%{$s}%"))
            ->orderBy('no')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/prod/grupo-precios/index', [
            'obras' => $obras,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/prod/grupo-precios/create');
    }

    public function store(GrupoPrecioStoreRequest $request): RedirectResponse
    {
        GrupoPrecio::create([
            'descripcion' => $request->descripcion,
            'precio' => $request->precio,
        ]);

        return to_route('admin.prod.grupo-precios.index');
    }

    public function showByObra(Obra $obra): Response
    {
        $obra->load(['piezas' => fn ($q) => $q->orderBy('marca')]);

        $grupoPrecios = GrupoPrecio::query()
            ->withCount('marcaGrupos')
            ->orderBy('descripcion')
            ->get();

        $marcaGrupos = MarcaGrupo::query()
            ->whereHas('pieza', fn ($q) => $q->where('obra_id', $obra->id))
            ->with(['pieza', 'grupoPrecio'])
            ->get();

        return Inertia::render('admin/prod/grupo-precios/show', [
            'obra' => $obra,
            'grupoPrecios' => $grupoPrecios,
            'marcaGrupos' => $marcaGrupos,
        ]);
    }

    public function edit(GrupoPrecio $grupoPrecio): Response
    {
        $grupoPrecio->load('marcaGrupos.pieza.obra');

        return Inertia::render('admin/prod/grupo-precios/edit', [
            'grupoPrecio' => $grupoPrecio,
            'obras' => Obra::with(['piezas' => fn ($q) => $q->orderBy('marca')])->orderBy('no')->get(),
        ]);
    }

    public function assignPiezas(Request $request, GrupoPrecio $grupoPrecio): RedirectResponse
    {
        $request->validate([
            'pieza_ids' => ['required', 'array', 'min:1'],
            'pieza_ids.*' => ['exists:piezas,id'],
        ]);

        foreach ($request->pieza_ids as $piezaId) {
            MarcaGrupo::firstOrCreate([
                'pieza_id' => $piezaId,
                'grupo_precio_id' => $grupoPrecio->id,
            ]);
        }

        return back()->with('success', 'Piezas asignadas correctamente.');
    }

    public function update(GrupoPrecioUpdateRequest $request, GrupoPrecio $grupoPrecio): RedirectResponse
    {
        $grupoPrecio->update([
            'descripcion' => $request->descripcion,
            'precio' => $request->precio,
        ]);

        return to_route('admin.prod.grupo-precios.index');
    }

    public function destroy(GrupoPrecio $grupoPrecio): RedirectResponse
    {
        if ($grupoPrecio->marcaGrupos()->exists()) {
            return back()->withErrors(['error' => 'No se puede eliminar un grupo de precios que tiene piezas asignadas.']);
        }

        $grupoPrecio->delete();

        return to_route('admin.prod.grupo-precios.index');
    }
}
