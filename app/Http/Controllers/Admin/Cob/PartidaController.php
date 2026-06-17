<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\PartidaStoreRequest;
use App\Http\Requests\Admin\Cob\PartidaUpdateRequest;
use App\Models\Cob\Partida;
use App\Models\Obra;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PartidaController extends Controller
{
    public function create(Obra $obra): Response
    {
        return Inertia::render('admin/cob/partidas/create', [
            'obra' => $obra,
        ]);
    }

    public function store(PartidaStoreRequest $request, Obra $obra): RedirectResponse
    {
        $obra->partidas()->create($request->validated());

        return to_route('admin.cob.obras.show', $obra);
    }

    public function edit(Obra $obra, Partida $partida): Response
    {
        return Inertia::render('admin/cob/partidas/edit', [
            'obra' => $obra,
            'partida' => $partida,
        ]);
    }

    public function update(PartidaUpdateRequest $request, Obra $obra, Partida $partida): RedirectResponse
    {
        $partida->update($request->validated());

        return to_route('admin.cob.obras.show', $obra);
    }

    public function destroy(Obra $obra, Partida $partida): RedirectResponse
    {
        $partida->delete();

        return back();
    }
}
