<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\DisputaStoreRequest;
use App\Http\Requests\Admin\Cob\DisputaUpdateRequest;
use App\Models\Cob\Disputa;
use App\Models\Obra;
use Illuminate\Http\RedirectResponse;

class DisputaController extends Controller
{
    public function store(DisputaStoreRequest $request, Obra $obra): RedirectResponse
    {
        $obra->disputas()->create($request->validated());

        return back();
    }

    public function update(DisputaUpdateRequest $request, Obra $obra, Disputa $disputa): RedirectResponse
    {
        $disputa->update($request->validated());

        return back();
    }

    public function destroy(Obra $obra, Disputa $disputa): RedirectResponse
    {
        $disputa->delete();

        return back();
    }
}
