<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\PenalizacionStoreRequest;
use App\Http\Requests\Admin\Cob\PenalizacionUpdateRequest;
use App\Models\Cob\Penalizacion;
use App\Models\Obra;
use Illuminate\Http\RedirectResponse;

class PenalizacionController extends Controller
{
    public function store(PenalizacionStoreRequest $request, Obra $obra): RedirectResponse
    {
        $obra->penalizaciones()->create($request->validated());

        return back();
    }

    public function update(PenalizacionUpdateRequest $request, Obra $obra, Penalizacion $penalizacion): RedirectResponse
    {
        $penalizacion->update($request->validated());

        return back();
    }

    public function destroy(Obra $obra, Penalizacion $penalizacion): RedirectResponse
    {
        $penalizacion->delete();

        return back();
    }
}
