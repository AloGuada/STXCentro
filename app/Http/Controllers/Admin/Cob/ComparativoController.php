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
        ]);
    }

    public function store(ComparativoStoreRequest $request, Proyecto $proyecto): RedirectResponse
    {
        $proyecto->comparativos()->create($request->validated());

        return to_route('admin.cob.proyectos.show', $proyecto);
    }

    public function edit(Proyecto $proyecto, Comparativo $comparativo): Response
    {
        return Inertia::render('admin/cob/comparativos/edit', [
            'proyecto' => $proyecto->only('id', 'no', 'descripcion'),
            'comparativo' => $comparativo,
        ]);
    }

    public function update(ComparativoUpdateRequest $request, Proyecto $proyecto, Comparativo $comparativo): RedirectResponse
    {
        $comparativo->update($request->validated());

        return to_route('admin.cob.proyectos.show', $proyecto);
    }

    public function destroy(Proyecto $proyecto, Comparativo $comparativo): RedirectResponse
    {
        $comparativo->delete();

        return back();
    }
}
