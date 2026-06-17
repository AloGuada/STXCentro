<?php

namespace App\Http\Controllers\Admin\Cob;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Cob\EstimacionPagoStoreRequest;
use App\Models\Cob\Estimacion;
use App\Models\Proyecto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class EstimacionPagoController extends Controller
{
    public function store(EstimacionPagoStoreRequest $request, Proyecto $proyecto, Estimacion $estimacion): RedirectResponse
    {
        return DB::transaction(function () use ($request, $estimacion) {
            $estimacion = Estimacion::lockForUpdate()->find($estimacion->id);

            $montoPagado = (float) $request->validated('monto_pagado');
            $totalPagado = (float) $estimacion->monto_pagado + $montoPagado;
            $montoEstimado = (float) $estimacion->monto_estimado;

            if ($totalPagado > $montoEstimado) {
                return back()->withErrors(['monto_pagado' => 'El monto excede el saldo pendiente de la estimacion.']);
            }

            $data = $request->safe()->except('comprobante');

            $pago = $estimacion->pagos()->create($data);

            if ($request->hasFile('comprobante')) {
                $file = $request->file('comprobante');
                $pago->media()->create([
                    'descripcion' => 'comprobante',
                    'nombre_original' => $file->getClientOriginalName(),
                    'path' => $file->store("cob/proyectos/{$estimacion->proyecto_id}/pagos", 'public'),
                    'mime' => $file->getMimeType(),
                    'size' => $file->getSize(),
                ]);
            }

            $nuevoEstado = $estimacion->estado;
            if ($totalPagado >= $montoEstimado) {
                $nuevoEstado = 'pagado';
            } elseif ($totalPagado > 0 && $estimacion->estado === 'facturada') {
                $nuevoEstado = 'pago_parcial';
            }

            $estimacion->update([
                'monto_pagado' => $totalPagado,
                'estado' => $nuevoEstado,
                'fecha_ultimo_cambio_estado' => $nuevoEstado !== $estimacion->estado ? now() : $estimacion->fecha_ultimo_cambio_estado,
            ]);

            return back();
        });
    }
}
