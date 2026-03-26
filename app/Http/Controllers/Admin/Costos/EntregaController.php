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
            $entrega = $factura->entregas()->create([
                'recibido_por' => $request->user()->id,
                'fecha_entrega' => $request->input('fecha_entrega'),
                'tipo' => $request->input('tipo'),
                'observaciones' => $request->input('observaciones'),
            ]);

            if ($request->hasFile('archivo')) {
                $file = $request->file('archivo');
                $entrega->media()->create([
                    'descripcion' => 'archivo',
                    'nombre_original' => $file->getClientOriginalName(),
                    'path' => $file->store('costos/entregas', 'public'),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            if ($request->input('tipo') === 'completa') {
                $factura->update(['estatus' => 'pendiente_aprobacion']);
            }

            $factura->ordenCompra->recalcularEstatus();
        });

        return back()->with('success', 'Entrega registrada correctamente.');
    }
}
