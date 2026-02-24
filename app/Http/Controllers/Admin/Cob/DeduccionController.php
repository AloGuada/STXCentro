<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\DeduccionStoreRequest;
use App\Http\Requests\Admin\Cob\DeduccionUpdateRequest;
use App\Models\Cob\Deduccion;
use App\Models\Obra;
use Illuminate\Http\RedirectResponse;

class DeduccionController extends Controller
{
    public function store(DeduccionStoreRequest $request, Obra $obra): RedirectResponse
    {
        $obra->deducciones()->create($request->validated());

        return back();
    }

    public function update(DeduccionUpdateRequest $request, Obra $obra, Deduccion $deduccion): RedirectResponse
    {
        $deduccion->update($request->validated());

        return back();
    }

    public function destroy(Obra $obra, Deduccion $deduccion): RedirectResponse
    {
        $deduccion->delete();

        return back();
    }
}
