<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\AdendaStoreRequest;
use App\Http\Requests\Admin\Cob\AdendaUpdateRequest;
use App\Models\Cob\Adenda;
use App\Models\Obra;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdendaController extends Controller
{
    public function create(Obra $obra): Response
    {
        return Inertia::render('admin/cob/adendas/create', [
            'obra' => $obra,
        ]);
    }

    public function store(AdendaStoreRequest $request, Obra $obra): RedirectResponse
    {
        $obra->adendas()->create($request->validated());

        return to_route('admin.cob.obras.show', $obra);
    }

    public function edit(Obra $obra, Adenda $adenda): Response
    {
        return Inertia::render('admin/cob/adendas/edit', [
            'obra' => $obra,
            'adenda' => $adenda,
        ]);
    }

    public function update(AdendaUpdateRequest $request, Obra $obra, Adenda $adenda): RedirectResponse
    {
        $adenda->update($request->validated());

        return to_route('admin.cob.obras.show', $obra);
    }

    public function destroy(Obra $obra, Adenda $adenda): RedirectResponse
    {
        $adenda->delete();

        return back();
    }
}
