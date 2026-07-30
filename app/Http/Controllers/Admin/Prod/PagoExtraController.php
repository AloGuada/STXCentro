<?php

namespace App\Http\Controllers\Admin\Prod;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Prod\PagoExtraStoreRequest;
use App\Models\Prod\Destajo;
use App\Models\Prod\PagoExtra;
use Illuminate\Http\RedirectResponse;

class PagoExtraController extends Controller
{
    public function store(PagoExtraStoreRequest $request, Destajo $destajo): RedirectResponse
    {
        if ($destajo->cerrado) {
            return back()->withErrors(['error' => 'No se puede agregar un pago extra a un destajo cerrado.']);
        }

        PagoExtra::create([
            'descripcion' => $request->descripcion,
            'tipo_id' => $request->tipo_id,
            'destajo_id' => $destajo->id,
            'grupo_trabajo_id' => $request->grupo_trabajo_id,
            'precio' => $request->precio,
            'dias' => $request->dias,
            'personas' => $request->personas,
        ]);

        return to_route('admin.prod.destajos.show', $destajo);
    }

    public function destroy(Destajo $destajo, PagoExtra $pagoExtra): RedirectResponse
    {
        if ($destajo->cerrado) {
            return back()->withErrors(['error' => 'No se puede eliminar un pago extra de un destajo cerrado.']);
        }

        $pagoExtra->delete();

        return to_route('admin.prod.destajos.show', $destajo);
    }
}
