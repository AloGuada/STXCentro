<?php

namespace App\Http\Controllers\Admin\Cotiz;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cotiz\GeneradoraRegistroStoreRequest;
use App\Http\Requests\Admin\Cotiz\GeneradoraRegistroUpdateRequest;
use App\Models\Cotiz\Generadora;
use App\Models\Cotiz\GeneradoraRegistro;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class GeneradoraRegistroController extends Controller
{
    public function store(GeneradoraRegistroStoreRequest $request, Generadora $generadora): RedirectResponse
    {
        $generadora->registros()->create($request->validated());

        return to_route('admin.cotiz.generadoras.edit', $generadora);
    }

    public function update(GeneradoraRegistroUpdateRequest $request, GeneradoraRegistro $registro): RedirectResponse
    {
        $registro->update($request->validated());

        return to_route('admin.cotiz.generadoras.edit', $registro->generadora_id);
    }

    public function destroy(GeneradoraRegistro $registro): RedirectResponse
    {
        $generadoraId = $registro->generadora_id;
        $registro->delete();

        return to_route('admin.cotiz.generadoras.edit', $generadoraId);
    }

    public function validar(Request $request, GeneradoraRegistro $registro): RedirectResponse
    {
        $validado = $request->has('validado')
            ? $request->boolean('validado')
            : ! $registro->validado;

        $registro->update(['validado' => $validado]);

        return back();
    }
}
