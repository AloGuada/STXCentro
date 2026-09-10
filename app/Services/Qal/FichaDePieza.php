<?php

namespace App\Services\Qal;

use App\Models\Prod\Pieza;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\TipoPieza;

/**
 * La pieza física como la ve la captura: su marca, su peso, el tipo que
 * sugiere la marca y las inspecciones que ya lleva.
 *
 * La piden el lector de QR y la precarga de una reinspección; las dos llenan
 * la misma tarjeta.
 */
class FichaDePieza
{
    /**
     * @return array<string, mixed>
     */
    public function de(Pieza $pieza): array
    {
        $pieza->loadMissing(['marca', 'catalogo:id,obra_id,version']);
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
