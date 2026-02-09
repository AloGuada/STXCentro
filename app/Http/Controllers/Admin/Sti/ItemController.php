<?php

namespace App\Http\Controllers\Admin\Sti;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Sti\ItemStoreRequest;
use App\Http\Requests\Admin\Sti\ItemUpdateRequest;
use App\Models\Sti\Grupo;
use App\Models\Sti\Item;
use App\Models\Sti\ItemHistorial;
use App\Models\Sti\ItemTipo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ItemController extends Controller
{
    public function index(Request $request): Response
    {
        $items = Item::query()
            ->with(['tipo', 'grupo.equipo'])
            ->when($request->search, fn ($q, $s) => $q->where('descripcion', 'like', "%{$s}%")
                ->orWhere('no_serie', 'like', "%{$s}%"))
            ->when($request->tipo_id, fn ($q, $t) => $q->where('tipo_id', $t))
            ->when($request->estado, fn ($q, $e) => $q->where('estado', $e))
            ->orderBy('descripcion')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/sti/items/index', [
            'items' => $items,
            'tipos' => ItemTipo::orderBy('descripcion')->get(),
            'filters' => $request->only('search', 'tipo_id', 'estado'),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/sti/items/create', [
            'tipos' => ItemTipo::orderBy('descripcion')->get(),
        ]);
    }

    public function store(ItemStoreRequest $request): RedirectResponse
    {
        Item::create([
            'descripcion' => $request->descripcion,
            'tipo_id' => $request->tipo_id,
            'costo' => $request->costo,
            'no_serie' => $request->no_serie,
            'estado' => $request->estado,
        ]);

        return to_route('admin.sti.items.index');
    }

    public function edit(Item $item): Response
    {
        $item->load([
            'tipo',
            'grupo.equipo',
            'historial' => fn ($q) => $q->orderByDesc('fecha'),
            'historial.equipo',
            'historial.tecnico',
        ]);

        return Inertia::render('admin/sti/items/edit', [
            'item' => $item,
            'tipos' => ItemTipo::orderBy('descripcion')->get(),
        ]);
    }

    public function update(ItemUpdateRequest $request, Item $item): RedirectResponse
    {
        $item->update([
            'descripcion' => $request->descripcion,
            'tipo_id' => $request->tipo_id,
            'costo' => $request->costo,
            'no_serie' => $request->no_serie,
            'estado' => $request->estado,
        ]);

        return to_route('admin.sti.items.edit', $item);
    }

    public function destroy(Item $item): RedirectResponse
    {
        if ($item->grupo()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar un item que está asignado a un equipo.']);
        }

        if ($item->historial()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar un item que tiene historial de movimientos.']);
        }

        $item->delete();

        return to_route('admin.sti.items.index');
    }

    public function asignar(Request $request, Item $item): RedirectResponse
    {
        $request->validate([
            'equipo_id' => ['required', 'exists:sti_equipos,id'],
            'tecnico_id' => ['nullable', 'exists:sti_tecnicos,id'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($item->grupo()->exists()) {
            return back()->withErrors(['asignar' => 'Este item ya está asignado a un equipo.']);
        }

        Grupo::create([
            'equipo_id' => $request->equipo_id,
            'item_id' => $item->id,
        ]);

        $item->update(['estado' => 'instalado']);

        ItemHistorial::create([
            'item_id' => $item->id,
            'equipo_id' => $request->equipo_id,
            'tecnico_id' => $request->tecnico_id,
            'accion' => 'instalacion',
            'fecha' => now(),
            'observaciones' => $request->observaciones,
        ]);

        return back();
    }

    public function retirar(Request $request, Item $item): RedirectResponse
    {
        $request->validate([
            'tecnico_id' => ['nullable', 'exists:sti_tecnicos,id'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
        ]);

        $grupo = $item->grupo;

        if (! $grupo) {
            return back()->withErrors(['retirar' => 'Este item no está asignado a ningún equipo.']);
        }

        $equipoId = $grupo->equipo_id;
        $grupo->delete();

        $item->update(['estado' => 'disponible']);

        ItemHistorial::create([
            'item_id' => $item->id,
            'equipo_id' => $equipoId,
            'tecnico_id' => $request->tecnico_id,
            'accion' => 'retiro',
            'fecha' => now(),
            'observaciones' => $request->observaciones,
        ]);

        return back();
    }
}
