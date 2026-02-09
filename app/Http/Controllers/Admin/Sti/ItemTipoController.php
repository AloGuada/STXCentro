<?php

namespace App\Http\Controllers\Admin\Sti;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Sti\ItemTipoStoreRequest;
use App\Http\Requests\Admin\Sti\ItemTipoUpdateRequest;
use App\Models\Sti\ItemTipo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ItemTipoController extends Controller
{
    public function index(Request $request): Response
    {
        $tipos = ItemTipo::query()
            ->when($request->search, fn ($q, $s) => $q->where('descripcion', 'like', "%{$s}%"))
            ->orderBy('descripcion')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/sti/items-tipos/index', [
            'tipos' => $tipos,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/sti/items-tipos/create');
    }

    public function store(ItemTipoStoreRequest $request): RedirectResponse
    {
        ItemTipo::create([
            'descripcion' => $request->descripcion,
        ]);

        return to_route('admin.sti.items-tipos.index');
    }

    public function edit(ItemTipo $itemTipo): Response
    {
        return Inertia::render('admin/sti/items-tipos/edit', [
            'tipo' => $itemTipo,
        ]);
    }

    public function update(ItemTipoUpdateRequest $request, ItemTipo $itemTipo): RedirectResponse
    {
        $itemTipo->update([
            'descripcion' => $request->descripcion,
        ]);

        return to_route('admin.sti.items-tipos.index');
    }

    public function destroy(ItemTipo $itemTipo): RedirectResponse
    {
        if ($itemTipo->items()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar un tipo que tiene items asociados.']);
        }

        $itemTipo->delete();

        return to_route('admin.sti.items-tipos.index');
    }
}
