<?php

namespace App\Services\Qal;

use App\Enums\Qal\EstatusModelo;
use App\Models\Prod\Pieza;
use App\Models\Qal\Inspeccion;
use App\Models\Qal\Modelo;
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
        [$modeloMarcaId, $estado3d] = $this->modelo3d($obraId, (string) $pieza->marca->marca);

        return [
            'id' => $pieza->id,
            'qr' => $pieza->qr,
            'qs' => $pieza->qs,
            'linea' => $pieza->linea,
            'modulo' => $pieza->modulo,
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
            // con ella el mapeo de soldado monta el visor con sus cordones.
            'modelo_marca_id' => $modeloMarcaId,
            // Y cuando no la hay, por qué: el inspector no puede arreglarlo,
            // pero sí saber a quién pedírselo.
            'modelo_3d' => $estado3d,
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

    /**
     * La marca en el modelo convertido y, si no está, qué le falta a la obra:
     * `listo`, `convirtiendo`, `sin_marca` (el modelo no la trae), `error` o
     * `sin_modelo`.
     *
     * @return array{0: int|null, 1: string}
     */
    private function modelo3d(int $obraId, string $marca): array
    {
        $modeloMarcaId = ModeloMarca::query()
            ->where('marca', mb_strtoupper(trim($marca)))
            // Las marcas se guardan conforme el servicio las termina: la de un
            // modelo a medio convertir ya sirve para el visor.
            ->whereHas('modelo', fn ($modelo) => $modelo->where('obra_id', $obraId)->whereIn('estatus', [EstatusModelo::Listo->value, EstatusModelo::Procesando->value]))
            ->orderByDesc('modelo_id')
            ->value('id');

        if ($modeloMarcaId !== null) {
            return [$modeloMarcaId, 'listo'];
        }

        $estatus = Modelo::query()->where('obra_id', $obraId)->pluck('estatus');

        return [null, match (true) {
            $estatus->contains(fn (EstatusModelo $e): bool => in_array($e, [EstatusModelo::Pendiente, EstatusModelo::Procesando], true)) => 'convirtiendo',
            $estatus->contains(EstatusModelo::Listo) => 'sin_marca',
            $estatus->contains(EstatusModelo::Error) => 'error',
            default => 'sin_modelo',
        }];
    }
}
