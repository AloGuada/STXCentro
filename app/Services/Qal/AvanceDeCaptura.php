<?php

namespace App\Services\Qal;

use App\Enums\Qal\EstatusInspeccion;
use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\Subetapa;
use App\Models\Concepto;
use App\Models\Prod\Pieza;
use App\Models\Qal\Inspeccion;
use Illuminate\Support\Collection;

/**
 * La pestaña Registros de la captura: qué va de cada marca de la obra, pieza
 * por pieza.
 *
 * Es «Lotes por marca» de la aplicación anterior, donde cada marca desplegaba
 * sus consecutivos con el estado de cada uno y un botón para registrar el que
 * faltaba. Aquí la pieza es el QR del catálogo de Producción, así que la lista
 * de piezas no la arma el inspector: ya existe, y lo que se pinta es cuáles
 * se han presentado y cómo salieron.
 *
 * Las marcas llegan con sus cuentas; las piezas de una marca se piden al
 * abrirla, porque una obra trae miles y nadie las va a mirar todas.
 */
class AvanceDeCaptura
{
    /**
     * Cada marca del catálogo vigente con cuántas piezas tiene y cómo van: la
     * última inspección de cada pieza decide en qué cuenta entra.
     *
     * @return list<array<string, mixed>>
     */
    public function marcasDeLaObra(int $obraId): array
    {
        $ultimas = $this->ultimaPorPieza($obraId)->groupBy('concepto_id');

        return Concepto::query()
            ->deCatalogoVigente()
            ->where('obra_id', $obraId)
            ->where('activo', true)
            ->withCount('piezas')
            ->get(['id', 'marca', 'lote', 'descripcion', 'cantidad'])
            ->map(function (Concepto $concepto) use ($ultimas): array {
                $deLaMarca = $ultimas->get($concepto->id, collect());

                return [
                    'id' => $concepto->id,
                    'marca' => $concepto->marca,
                    'lote' => $concepto->lote,
                    'descripcion' => $concepto->descripcion,
                    'piezas' => (int) $concepto->piezas_count,
                    'inspeccionadas' => $deLaMarca->count(),
                    'liberadas' => $deLaMarca->where('estatus', EstatusInspeccion::Liberado)->count(),
                    'rechazadas' => $deLaMarca->where('estatus', EstatusInspeccion::Rechazado)->count(),
                    'pendientes' => $deLaMarca->where('estatus', EstatusInspeccion::Pendiente)->count(),
                ];
            })
            ->sortBy('marca', SORT_NATURAL | SORT_FLAG_CASE)
            ->values()
            ->all();
    }

    /**
     * Las piezas de una marca con la última inspección de cada etapa: armado,
     * soldado y pintura. Una etapa sin fila es que la pieza no ha pasado por
     * ahí.
     *
     * @return list<array<string, mixed>>
     */
    public function piezasDeLaMarca(int $conceptoId): array
    {
        $inspecciones = Inspeccion::query()
            ->where('concepto_id', $conceptoId)
            ->whereNotNull('prod_pieza_id')
            ->orderBy('fecha')
            ->orderBy('id')
            ->get(['id', 'prod_pieza_id', 'folio', 'fase', 'subetapa', 'estatus', 'fecha', 'numero_inspeccion'])
            ->groupBy('prod_pieza_id');

        return Pieza::query()
            ->where('concepto_id', $conceptoId)
            ->where('activo', true)
            ->orderBy('qs')
            ->orderBy('qr')
            ->get(['id', 'qr', 'qs'])
            ->map(function (Pieza $pieza) use ($inspecciones): array {
                $etapas = $inspecciones->get($pieza->id, collect())
                    ->groupBy(fn (Inspeccion $inspeccion): string => $this->etapa($inspeccion))
                    ->map(fn (Collection $historia): array => $this->resumen($historia->last(), $historia->count()));

                return [
                    'id' => $pieza->id,
                    'qr' => $pieza->qr,
                    'qs' => $pieza->qs,
                    'etapas' => [
                        'armado' => $etapas->get('armado'),
                        'soldado' => $etapas->get('soldado'),
                        'pintura' => $etapas->get('pintura'),
                    ],
                ];
            })
            ->values()
            ->all();
    }

    /**
     * La última inspección de cada pieza de la obra, por fecha: la que dice
     * en qué está.
     *
     * @return Collection<int, Inspeccion>
     */
    private function ultimaPorPieza(int $obraId): Collection
    {
        return Inspeccion::query()
            ->where('obra_id', $obraId)
            ->whereNotNull('prod_pieza_id')
            ->whereIn('prod_pieza_id', fn ($consulta) => $consulta
                ->select('id')
                ->from('prod_piezas')
                ->whereIn('catalogo_id', fn ($catalogos) => $catalogos->select('id')->from('prod_catalogos')->where('obra_id', $obraId)->where('vigente', true)))
            ->orderBy('fecha')
            ->orderBy('id')
            ->get(['id', 'prod_pieza_id', 'concepto_id', 'estatus'])
            ->groupBy('prod_pieza_id')
            ->map(fn (Collection $historia): Inspeccion => $historia->last())
            ->values();
    }

    private function etapa(Inspeccion $inspeccion): string
    {
        if ($inspeccion->fase === FaseTransformacion::Tercera) {
            return 'pintura';
        }

        return $inspeccion->subetapa === Subetapa::ArmadoVestido ? 'armado' : 'soldado';
    }

    /**
     * @return array<string, mixed>
     */
    private function resumen(Inspeccion $inspeccion, int $veces): array
    {
        return [
            'id' => $inspeccion->id,
            'folio' => $inspeccion->folio,
            'estatus' => $inspeccion->estatus->value,
            'fecha' => $inspeccion->fecha->toDateString(),
            'numero_inspeccion' => (int) $inspeccion->numero_inspeccion,
            'inspecciones' => $veces,
        ];
    }
}
