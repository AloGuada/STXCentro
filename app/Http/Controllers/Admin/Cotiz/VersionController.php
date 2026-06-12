<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\ObraVersionStoreRequest;
use App\Models\Cotiz\Obra;
use App\Models\Cotiz\ObraVersion;
use App\Services\Cotiz\VersionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Versiones de una obra de Cotización (Fase 5.5, modelo LINEAL). Snapshots continuos del
 * árbol de inputs: crear, comparar dos y restaurar (sobrescribe con respaldo automático).
 */
class VersionController extends Controller
{
    public function index(Request $request, Obra $obra, VersionManager $manager): Response
    {
        $versiones = $obra->versiones()
            ->with('creadoPor:id,name')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (ObraVersion $v) => [
                'id' => $v->id,
                'nombre' => $v->nombre,
                'nota' => $v->nota,
                'auto' => $v->auto,
                'creado_por' => $v->creadoPor?->name,
                'created_at' => $v->created_at?->toIso8601String(),
            ]);

        // Comparación opcional vía ?a=&b=.
        $diff = null;
        $comparando = null;
        $aId = $request->integer('a');
        $bId = $request->integer('b');
        if ($aId && $bId) {
            $a = $obra->versiones()->find($aId);
            $b = $obra->versiones()->find($bId);
            if ($a !== null && $b !== null) {
                $diff = $manager->comparar($a->snapshot, $b->snapshot);
                $comparando = ['a' => $a->id, 'b' => $b->id, 'a_nombre' => $a->nombre, 'b_nombre' => $b->nombre];
            }
        }

        return Inertia::render('admin/cotiz/versiones/index', [
            'obra' => $obra->only(['id', 'nombre']),
            'versiones' => $versiones,
            'diff' => $diff,
            'comparando' => $comparando,
        ]);
    }

    public function store(ObraVersionStoreRequest $request, Obra $obra, VersionManager $manager): RedirectResponse
    {
        $manager->crear($obra, $request->validated('nombre'), $request->validated('nota'), $request->user()?->id);

        return back();
    }

    public function restaurar(Obra $obra, ObraVersion $version, VersionManager $manager, Request $request): RedirectResponse
    {
        abort_unless($version->obra_id === $obra->id, 404);

        $manager->restaurar($version, $request->user()?->id);

        return back();
    }

    public function destroy(Obra $obra, ObraVersion $version): RedirectResponse
    {
        abort_unless($version->obra_id === $obra->id, 404);

        $version->delete();

        return back();
    }
}
