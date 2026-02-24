<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\AdendaStoreRequest;
use App\Http\Requests\Admin\Cob\AdendaUpdateRequest;
use App\Models\Cob\Adenda;
use App\Models\Obra;
use Illuminate\Http\RedirectResponse;

class AdendaController extends Controller
{
    public function store(AdendaStoreRequest $request, Obra $obra): RedirectResponse
    {
        $obra->adendas()->create($request->validated());

        return back();
    }

    public function update(AdendaUpdateRequest $request, Obra $obra, Adenda $adenda): RedirectResponse
    {
        $adenda->update($request->validated());

        return back();
    }

    public function destroy(Obra $obra, Adenda $adenda): RedirectResponse
    {
        $adenda->delete();

        return back();
    }
}
