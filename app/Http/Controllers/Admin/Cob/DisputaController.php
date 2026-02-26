<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\DisputaStoreRequest;
use App\Http\Requests\Admin\Cob\DisputaUpdateRequest;
use App\Models\Cob\Disputa;
use App\Models\Obra;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class DisputaController extends Controller
{
    public function create(Obra $obra): Response
    {
        return Inertia::render('admin/cob/disputas/create', [
            'obra' => $obra,
        ]);
    }

    public function store(DisputaStoreRequest $request, Obra $obra): RedirectResponse
    {
        $obra->disputas()->create($request->validated());

        return to_route('admin.cob.obras.show', $obra);
    }

    public function edit(Obra $obra, Disputa $disputa): Response
    {
        return Inertia::render('admin/cob/disputas/edit', [
            'obra' => $obra,
            'disputa' => $disputa,
        ]);
    }

    public function update(DisputaUpdateRequest $request, Obra $obra, Disputa $disputa): RedirectResponse
    {
        $disputa->update($request->validated());

        return to_route('admin.cob.obras.show', $obra);
    }

    public function destroy(Obra $obra, Disputa $disputa): RedirectResponse
    {
        $disputa->delete();

        return back();
    }
}
