<?php

namespace App\Services\Qal;

use App\Enums\Qal\EstatusModelo;
use App\Models\Prod\Pieza;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\ModeloMarca;
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
            // La marca en el modelo 3D más reciente ya convertido de la obra:
            // con ella la captura de soldado monta el visor con sus cordones.
            'modelo_marca_id' => ModeloMarca::query()
                ->where('marca', mb_strtoupper(trim((string) $pieza->marca->marca)))
                ->whereHas('modelo', fn ($modelo) => $modelo->where('obra_id', $obraId)->where('estatus', EstatusModelo::Listo->value))
                ->orderByDesc('modelo_id')
                ->value('id'),
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
