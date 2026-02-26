<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\ComparativoStoreRequest;
use App\Http\Requests\Admin\Cob\ComparativoUpdateRequest;
use App\Models\Cob\Comparativo;
use App\Models\Obra;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ComparativoController extends Controller
{
    public function create(Obra $obra): Response
    {
        return Inertia::render('admin/cob/comparativos/create', [
            'obra' => $obra,
        ]);
    }

    public function store(ComparativoStoreRequest $request, Obra $obra): RedirectResponse
    {
        $obra->comparativos()->create($request->validated());

        return to_route('admin.cob.obras.show', $obra);
    }

    public function edit(Obra $obra, Comparativo $comparativo): Response
    {
        return Inertia::render('admin/cob/comparativos/edit', [
            'obra' => $obra,
            'comparativo' => $comparativo,
        ]);
    }

    public function update(ComparativoUpdateRequest $request, Obra $obra, Comparativo $comparativo): RedirectResponse
    {
        $comparativo->update($request->validated());

        return to_route('admin.cob.obras.show', $obra);
    }

    public function destroy(Obra $obra, Comparativo $comparativo): RedirectResponse
    {
        $comparativo->delete();

        return back();
    }
}
