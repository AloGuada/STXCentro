<?php

namespace App\Http\Controllers\Admin\Costos;

use App\Http\Controllers\Controller;
use App\Services\Costos\TipoCambioService;
use Illuminate\Http\JsonResponse;

class TipoCambioController extends Controller
{
    public function __construct(private readonly TipoCambioService $tipoCambio) {}

    /**
     * Devuelve el tipo de cambio de referencia del día (MXN por unidad) para
     * autollenar el campo editable en solicitudes/requisiciones.
     */
    public function show(string $moneda): JsonResponse
    {
        $moneda = strtolower($moneda);

        if (! in_array($moneda, ['mxn', 'usd', 'eur'], true)) {
            return response()->json(['message' => 'Moneda no soportada.'], 422);
        }

        return response()->json([
            'moneda' => $moneda,
            'tipo_cambio' => $this->tipoCambio->mxnPorUnidad($moneda),
            'fecha' => now()->toDateString(),
        ]);
    }
}
