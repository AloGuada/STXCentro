<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Http\Controllers\Controller;
use App\Models\Prod\Pieza;
use App\Services\Qal\FichaDePieza;
use App\Services\Qal\ResolutorDePiezas;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * La pieza física que se escaneó en 2ª o pintura.
 *
 * Responde JSON y no una página: lo pide el lector de QR de la captura a media
 * inspección, y lo que devuelve rellena la tarjeta de la pieza sin perder lo
 * que el inspector ya capturó.
 */
class PiezaController extends Controller
{
    public function resolver(Request $request, ResolutorDePiezas $resolutor, FichaDePieza $fichas): JsonResponse
    {
        $candidatas = $resolutor->candidatas(
            (string) $request->query('codigo', ''),
            $request->integer('obra_id') ?: null,
        );

        if ($candidatas->isEmpty()) {
            return response()->json([
                'message' => 'Ninguna pieza del catálogo vigente tiene ese código.',
            ], 404);
        }

        // Un QS repetido entre lotes, o un código que existe en dos obras: no
        // se elige por el inspector, se le enseña para que escanee el QR.
        if ($candidatas->count() > 1) {
            return response()->json([
                'message' => 'El código corresponde a más de una pieza. Escanea el QR o elige la obra primero.',
                'candidatas' => $candidatas->map(fn (Pieza $pieza): string => $pieza->etiqueta())->values(),
            ], 409);
        }

        return response()->json($fichas->de($candidatas->first()));
    }
}
