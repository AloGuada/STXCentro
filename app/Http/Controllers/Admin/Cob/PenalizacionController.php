<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\PenalizacionStoreRequest;
use App\Http\Requests\Admin\Cob\PenalizacionUpdateRequest;
use App\Models\Cob\Penalizacion;
use App\Models\Obra;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PenalizacionController extends Controller
{
    public function create(Obra $obra): Response
    {
        return Inertia::render('admin/cob/penalizaciones/create', [
            'obra' => $obra,
        ]);
    }

    public function store(PenalizacionStoreRequest $request, Obra $obra): RedirectResponse
    {
        $obra->penalizaciones()->create($request->validated());

        return to_route('admin.cob.obras.show', $obra);
    }

    public function edit(Obra $obra, Penalizacion $penalizacion): Response
    {
        return Inertia::render('admin/cob/penalizaciones/edit', [
            'obra' => $obra,
            'penalizacion' => $penalizacion,
        ]);
    }

    public function update(PenalizacionUpdateRequest $request, Obra $obra, Penalizacion $penalizacion): RedirectResponse
    {
        $penalizacion->update($request->validated());

        return to_route('admin.cob.obras.show', $obra);
    }

    public function destroy(Obra $obra, Penalizacion $penalizacion): RedirectResponse
    {
        $penalizacion->delete();

        return back();
    }
}
