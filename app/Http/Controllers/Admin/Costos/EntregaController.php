<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Costos\EntregaStoreRequest;
use App\Models\Costos\Factura;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class EntregaController extends Controller
{
    public function store(EntregaStoreRequest $request, Factura $factura): RedirectResponse
    {
        DB::transaction(function () use ($request, $factura) {
            $archivoPath = null;
            if ($request->hasFile('archivo')) {
                $archivoPath = $request->file('archivo')->store('costos/entregas', 'public');
            }

            $factura->entregas()->create([
                'recibido_por' => $request->user()->id,
                'fecha_entrega' => $request->input('fecha_entrega'),
                'observaciones' => $request->input('observaciones'),
                'archivo_path' => $archivoPath,
            ]);

            $factura->ordenCompra->recalcularEstatus();
        });

        return back()->with('success', 'Entrega registrada correctamente.');
    }
}
