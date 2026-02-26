<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\DeduccionStoreRequest;
use App\Http\Requests\Admin\Cob\DeduccionUpdateRequest;
use App\Models\Cob\Deduccion;
use App\Models\Obra;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DeduccionController extends Controller
{
    public function create(Obra $obra): Response
    {
        return Inertia::render('admin/cob/deducciones/create', [
            'obra' => $obra,
        ]);
    }

    public function store(DeduccionStoreRequest $request, Obra $obra): RedirectResponse
    {
        $obra->deducciones()->create($request->validated());

        return to_route('admin.cob.obras.show', $obra);
    }

    public function edit(Obra $obra, Deduccion $deduccion): Response
    {
        return Inertia::render('admin/cob/deducciones/edit', [
            'obra' => $obra,
            'deduccion' => $deduccion,
        ]);
    }

    public function update(DeduccionUpdateRequest $request, Obra $obra, Deduccion $deduccion): RedirectResponse
    {
        $deduccion->update($request->validated());

        return to_route('admin.cob.obras.show', $obra);
    }

    public function destroy(Obra $obra, Deduccion $deduccion): RedirectResponse
    {
        $deduccion->delete();

        return back();
    }
}
