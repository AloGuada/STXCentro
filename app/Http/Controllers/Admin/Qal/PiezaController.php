<?php

namespace App\Http\Controllers\Admin\Qal;

use App\Http\Controllers\Controller;
use App\Models\Prod\Pieza;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\TipoPieza;
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
    public function resolver(Request $request, ResolutorDePiezas $resolutor): JsonResponse
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

        return response()->json($this->ficha($candidatas->first()));
    }

    /**
     * @return array<string, mixed>
     */
    private function ficha(Pieza $pieza): array
    {
        $obraId = $pieza->catalogo->obra_id;

        return [
            'id' => $pieza->id,
            'qr' => $pieza->qr,
            'qs' => $pieza->qs,
            'etiqueta' => $pieza->etiqueta(),
            'obra_id' => $obraId,
            'concepto' => [
                'id' => $pieza->marca->id,
                'marca' => $pieza->marca->marca,
                'lote' => $pieza->marca->lote,
                'descripcion' => $pieza->marca->descripcion,
                'peso_unitario' => $pieza->marca->peso_unitario,
            ],
            'tipo_pieza_id' => TipoPieza::paraMarca((string) $pieza->marca->marca)?->id,
            // Con esto la captura muestra el número de inspección que toca sin
            // otra vuelta al servidor, y el inspector ve si la pieza ya se
            // rechazó antes.
            'inspecciones' => Inspeccion::query()
                ->where('obra_id', $obraId)
                ->where('qr', $pieza->qr)
                ->orderBy('fecha')
                ->orderBy('id')
                ->get(['folio', 'fase', 'subetapa', 'numero_inspeccion', 'estatus', 'fecha']),
        ];
    }
}
