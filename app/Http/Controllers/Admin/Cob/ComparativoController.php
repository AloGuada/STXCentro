<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\ComparativoStoreRequest;
use App\Http\Requests\Admin\Cob\ComparativoUpdateRequest;
use App\Models\Cob\Comparativo;
use App\Models\Proyecto;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ComparativoController extends Controller
{
    public function create(Proyecto $proyecto): Response
    {
        return Inertia::render('admin/cob/comparativos/create', [
            'proyecto' => $proyecto->only('id', 'no', 'descripcion'),
            'obras' => $this->obras($proyecto),
        ]);
    }

    public function store(ComparativoStoreRequest $request, Proyecto $proyecto): RedirectResponse
    {
        $data = $request->validated();
        abort_unless($proyecto->obras()->whereKey($data['obra_id'])->exists(), 404);

        $proyecto->comparativos()->create($data);

        return to_route('admin.cob.proyectos.show', $proyecto);
    }

    public function edit(Proyecto $proyecto, Comparativo $comparativo): Response
    {
        return Inertia::render('admin/cob/comparativos/edit', [
            'proyecto' => $proyecto->only('id', 'no', 'descripcion'),
            'obras' => $this->obras($proyecto),
            'comparativo' => $comparativo,
        ]);
    }

    public function update(ComparativoUpdateRequest $request, Proyecto $proyecto, Comparativo $comparativo): RedirectResponse
    {
        $data = $request->validated();
        abort_unless($proyecto->obras()->whereKey($data['obra_id'])->exists(), 404);

        $comparativo->update($data);

        return to_route('admin.cob.proyectos.show', $proyecto);
    }

    public function destroy(Proyecto $proyecto, Comparativo $comparativo): RedirectResponse
    {
        $comparativo->delete();

        return back();
    }

    /**
     * Obras del proyecto (base primero) para el selector del formulario.
     *
     * @return \Illuminate\Support\Collection<int, array{id: int, no: string, tipo: string}>
     */
    private function obras(Proyecto $proyecto): \Illuminate\Support\Collection
    {
        return $proyecto->obras()
            ->orderByRaw("CASE WHEN tipo = 'base' THEN 0 ELSE 1 END")
            ->orderBy('no')
            ->get(['id', 'no', 'tipo'])
            ->map(fn ($o) => ['id' => $o->id, 'no' => $o->no, 'tipo' => $o->tipo]);
    }
}
