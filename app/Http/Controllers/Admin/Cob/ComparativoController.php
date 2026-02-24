<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\ComparativoStoreRequest;
use App\Http\Requests\Admin\Cob\ComparativoUpdateRequest;
use App\Models\Cob\Comparativo;
use App\Models\Obra;
use Illuminate\Http\RedirectResponse;

class ComparativoController extends Controller
{
    public function store(ComparativoStoreRequest $request, Obra $obra): RedirectResponse
    {
        $obra->comparativos()->create($request->validated());

        return back();
    }

    public function update(ComparativoUpdateRequest $request, Obra $obra, Comparativo $comparativo): RedirectResponse
    {
        $comparativo->update($request->validated());

        return back();
    }

    public function destroy(Obra $obra, Comparativo $comparativo): RedirectResponse
    {
        $comparativo->delete();

        return back();
    }
}
