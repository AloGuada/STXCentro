<?php

namespace App\Services\Qal;

use App\Models\Qal\LoteAccesorio;
use App\Models\Qal\Obra;
use App\Models\Qal\Sublote;
use App\Models\Qal\SubloteDefecto;

/**
 * Los lotes de accesorios como los lee la pestaña Accesorios del tablero:
 * cuánto de cada marca llegó, cuánto se liberó y qué está detenido.
 *
 * Cada sublote físico va con todas sus inspecciones, de la primera a la
 * última; la última es la que manda.
 */
class AvanceDeAccesorios
{
    /**
     * @return array{obras: mixed, obraId: int|null, lotes: list<array<string, mixed>>}
     */
    public function tablero(?int $obraId): array
    {
        return [
            'obras' => Obra::opcionesDeSelector(soloActivas: false),
            'obraId' => $obraId,
            'lotes' => LoteAccesorio::query()
                ->with([
                    'obra:id,no',
                    'sublotes' => fn ($consulta) => $consulta->orderBy('fecha')->orderBy('id'),
                    'sublotes.inspector.usuario:id,name',
                    'sublotes.defectos.defecto',
                ])
                ->when($obraId, fn ($consulta) => $consulta->where('obra_id', $obraId))
                ->orderBy('marca')
                ->get()
                ->map(fn (LoteAccesorio $lote): array => $this->lote($lote))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function lote(LoteAccesorio $lote): array
    {
        return [
            'id' => $lote->id,
            'marca' => $lote->marca,
            'descripcion' => $lote->descripcion,
            'obra' => $lote->obra?->no,
            'total_unidades' => $lote->total_unidades,
            'kg_unitario' => $lote->kg_unitario,
            'elementos_unitarios' => $lote->elementos_unitarios,
            // Una por etapa: las mismas unidades se reciben soldadas en 2ª y
            // vuelven pintadas en 3ª, y sumadas pasarían del total del lote.
            'avance' => $lote->avancePorFase(),
            'grupos' => $lote->sublotes
                ->groupBy(fn (Sublote $sublote): int => $sublote->grupoId())
                ->map(fn ($grupo): array => $grupo
                    ->sortBy('numero_inspeccion')
                    ->map(fn (Sublote $sublote): array => $this->sublote($sublote))
                    ->values()
                    ->all())
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function sublote(Sublote $sublote): array
    {
        return [
            'id' => $sublote->id,
            'fase' => $sublote->fase->value,
            'numero_inspeccion' => $sublote->numero_inspeccion,
            'fecha' => $sublote->fecha->toDateString(),
            'unidades' => $sublote->unidades,
            'nivel' => $sublote->nivel->value,
            'muestra' => $sublote->muestra,
            'conformes' => $sublote->conformes,
            'rechazadas' => $sublote->rechazadas,
            'veredicto' => $sublote->veredicto?->value,
            'disposicion' => $sublote->disposicion,
            'liberado' => $sublote->liberado(),
            'sin_disposicion' => $sublote->sinDisposicion(),
            'inspector' => $sublote->inspector?->usuario?->name,
            'modulo' => $sublote->modulo,
            'linea' => $sublote->linea,
            // «Soldadura: Grieta (2), Traslape · Barrenos: Posición incorrecta»
            'defectos' => $sublote->defectos
                ->groupBy(fn (SubloteDefecto $defecto): string => $defecto->defecto->ambito->etiqueta())
                ->map(fn ($defectos, string $familia): string => $familia.': '.$defectos
                    ->countBy(fn (SubloteDefecto $defecto): string => $defecto->defecto->nombre)
                    ->map(fn (int $veces, string $nombre): string => $veces > 1 ? "{$nombre} ({$veces})" : $nombre)
                    ->implode(', '))
                ->implode(' · '),
        ];
    }
}
