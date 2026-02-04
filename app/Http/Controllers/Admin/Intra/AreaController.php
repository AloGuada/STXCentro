<?php

namespace App\Http\Controllers\Admin\Intra;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Intra\AreaStoreRequest;
use App\Http\Requests\Admin\Intra\AreaUpdateRequest;
use App\Models\Intra\Area;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AreaController extends Controller
{
    public function index(Request $request): Response
    {
        $areas = Area::query()
            ->with('parent')
            ->when($request->search, fn ($q, $s) => $q->where('descripcion', 'like', "%{$s}%"))
            ->orderBy('descripcion')
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('admin/intra/areas/index', [
            'areas' => $areas,
            'filters' => $request->only('search'),
        ]);
    }

    public function create(): Response
    {
        $parentAreas = Area::query()
            ->where('activo', true)
            ->orderBy('descripcion')
            ->get(['id', 'descripcion', 'parent_id']);

        return Inertia::render('admin/intra/areas/create', [
            'parentAreas' => $parentAreas,
        ]);
    }

    public function store(AreaStoreRequest $request): RedirectResponse
    {
        Area::create([
            'descripcion' => $request->descripcion,
            'parent_id' => $request->parent_id,
            'order' => 0,
            'activo' => $request->boolean('activo', true),
        ]);

        return to_route('admin.intra.areas.index');
    }

    public function edit(Area $area): Response
    {
        $excludeIds = $this->getDescendantIds($area);
        $excludeIds[] = $area->id;

        $parentAreas = Area::query()
            ->whereNotIn('id', $excludeIds)
            ->where('activo', true)
            ->orderBy('descripcion')
            ->get(['id', 'descripcion', 'parent_id']);

        return Inertia::render('admin/intra/areas/edit', [
            'area' => $area->load('parent'),
            'parentAreas' => $parentAreas,
        ]);
    }

    /**
     * Get all descendant IDs of an area recursively.
     *
     * @return array<int>
     */
    private function getDescendantIds(Area $area): array
    {
        $ids = [];
        foreach ($area->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->getDescendantIds($child));
        }

        return $ids;
    }

    public function update(AreaUpdateRequest $request, Area $area): RedirectResponse
    {
        // Prevent setting itself as parent
        if ($request->parent_id == $area->id) {
            return back()->withErrors(['parent_id' => 'No se puede asignar el área como su propio padre.']);
        }

        $area->update([
            'descripcion' => $request->descripcion,
            'parent_id' => $request->parent_id,
            'activo' => $request->boolean('activo', true),
        ]);

        return to_route('admin.intra.areas.index');
    }

    public function destroy(Area $area): RedirectResponse
    {
        // Check if area has children
        if ($area->children()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar un área que tiene subáreas.']);
        }

        // Check if area has documents
        if ($area->documentos()->exists()) {
            return back()->withErrors(['delete' => 'No se puede eliminar un área que tiene documentos.']);
        }

        $area->delete();

        return to_route('admin.intra.areas.index');
    }
}
