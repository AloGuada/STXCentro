<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\TipoStoreRequest;
use App\Http\Requests\Admin\Prod\TipoUpdateRequest;
use App\Models\Prod\Tipo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TipoController extends Controller
{
    public function index(Request $request): Response
    {
        $tipos = Tipo::query()
            ->withCount('pagosExtra')
            ->when($request->search, fn ($q, $s) => $q->where('descripcion', 'like', "%{$s}%"))
            ->orderBy('orden')
            ->orderBy('descripcion')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/prod/tipos/index', [
            'tipos' => $tipos,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/prod/tipos/create');
    }

    public function store(TipoStoreRequest $request): RedirectResponse
    {
        Tipo::create([
            'descripcion' => $request->descripcion,
            'orden' => $request->orden ?? 0,
            'desgloce' => $request->boolean('desgloce'),
        ]);

        return to_route('admin.prod.tipos.index');
    }

    public function edit(Tipo $tipo): Response
    {
        return Inertia::render('admin/prod/tipos/edit', [
            'tipo' => $tipo,
        ]);
    }

    public function update(TipoUpdateRequest $request, Tipo $tipo): RedirectResponse
    {
        $tipo->update([
            'descripcion' => $request->descripcion,
            'orden' => $request->orden ?? $tipo->orden,
            'desgloce' => $request->boolean('desgloce'),
        ]);

        return to_route('admin.prod.tipos.index');
    }

    public function destroy(Tipo $tipo): RedirectResponse
    {
        if ($tipo->pagosExtra()->exists()) {
            return back()->withErrors(['error' => 'No se puede eliminar un tipo que tiene pagos extra asociados.']);
        }

        $tipo->delete();

        return to_route('admin.prod.tipos.index');
    }
}
