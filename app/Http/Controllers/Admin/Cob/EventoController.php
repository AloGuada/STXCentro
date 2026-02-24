<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\EventoStoreRequest;
use App\Http\Requests\Admin\Cob\EventoUpdateRequest;
use App\Models\Cob\Evento;
use App\Models\Obra;
use Illuminate\Http\RedirectResponse;

class EventoController extends Controller
{
    public function store(EventoStoreRequest $request, Obra $obra): RedirectResponse
    {
        $obra->eventos()->create($request->validated());

        return back();
    }

    public function update(EventoUpdateRequest $request, Obra $obra, Evento $evento): RedirectResponse
    {
        $evento->update($request->validated());

        return back();
    }

    public function destroy(Obra $obra, Evento $evento): RedirectResponse
    {
        $evento->delete();

        return back();
    }
}
