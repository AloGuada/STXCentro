<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Http\Controllers\Controller;
use App\Models\Concepto;
use App\Models\Prod\Pieza;
use App\Services\Qal\ResolutorDePiezas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * El formulario «agregar al plan» del avance de producción: obra, marca y QR.
 *
 * Responde JSON y no una página porque lo pide el formulario mientras se
 * escribe el plan de la semana: recargar la pantalla perdería lo que no se ha
 * guardado. Sólo del catálogo vigente, que es el que está en la nave.
 */
class PiezasDelPlanController extends Controller
{
    /** Las marcas de la obra, en orden natural, con cuántas piezas tiene cada una. */
    public function marcas(Request $request): JsonResponse
    {
        return response()->json(Concepto::query()
            ->deCatalogoVigente()
            ->where('obra_id', $request->integer('obra'))
            ->where('activo', true)
            ->withCount(['piezas' => fn ($piezas) => $piezas->where('activo', true)])
            ->get(['id', 'marca', 'lote'])
            ->sortBy('marca', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->map(fn (Concepto $concepto): array => [
                'id' => $concepto->id,
                'marca' => $concepto->marca,
                'lote' => $concepto->lote,
                'piezas' => (int) $concepto->piezas_count,
            ]));
    }

    /** Las piezas físicas de una marca, por su QS. */
    public function piezas(Request $request): JsonResponse
    {
        return response()->json(Pieza::query()
            ->where('concepto_id', $request->integer('marca'))
            ->where('activo', true)
            ->orderBy('qs')
            ->orderBy('qr')
            ->get(['id', 'qr', 'qs'])
            ->map(fn (Pieza $pieza): array => ['id' => $pieza->id, 'qr' => $pieza->qr, 'qs' => $pieza->qs]));
    }

    /**
     * La pieza de un QR —o de un QS, que es como la nombra la gente de
     * planta— con su obra y su marca, para rellenar el formulario solo. Un QS
     * que se repite entre lotes no se adivina: se pide el QR.
     */
    public function pieza(Request $request, ResolutorDePiezas $resolutor): JsonResponse
    {
        $candidatas = $resolutor->candidatas((string) $request->query('codigo', ''), $request->integer('obra') ?: null);

        if ($candidatas->isEmpty()) {
            return response()->json(['message' => 'Ninguna pieza del catálogo vigente tiene ese código.'], 404);
        }

        if ($candidatas->count() > 1) {
            return response()->json([
                'message' => 'Ese código es de más de una pieza (un QS que se repite entre lotes). Escribe el QR.',
                'candidatas' => $candidatas->map(fn (Pieza $pieza): string => $pieza->etiqueta())->values(),
            ], 409);
        }

        $pieza = $candidatas->first();

        return response()->json([
            'obra_id' => $pieza->catalogo->obra_id,
            'marca_id' => $pieza->concepto_id,
            'marca' => $pieza->marca?->marca,
            'pieza_id' => $pieza->id,
            'qr' => $pieza->qr,
            'qs' => $pieza->qs,
        ]);
    }
}
