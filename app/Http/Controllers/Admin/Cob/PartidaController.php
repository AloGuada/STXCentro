<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\PartidaStoreRequest;
use App\Http\Requests\Admin\Cob\PartidaUpdateRequest;
use App\Models\Cob\Partida;
use App\Models\Obra;
use Illuminate\Http\RedirectResponse;

class PartidaController extends Controller
{
    public function store(PartidaStoreRequest $request, Obra $obra): RedirectResponse
    {
        $obra->partidas()->create($request->validated());

        return back();
    }

    public function update(PartidaUpdateRequest $request, Obra $obra, Partida $partida): RedirectResponse
    {
        $partida->update($request->validated());

        return back();
    }

    public function destroy(Obra $obra, Partida $partida): RedirectResponse
    {
        $partida->delete();

        return back();
    }
}
