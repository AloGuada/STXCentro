<?php

namespace App\Services\Qal;

use App\Enums\Qal\ResultadoJunta;
use App\Models\Qal\Junta;

/**
 * Cómo va cada cordón de la plantilla, a partir de las juntas que se
 * capturaron sobre él.
 *
 * Una marca se fabrica muchas veces, así que un cordón tiene una junta por
 * cada pieza física que se inspeccionó. De cada pieza cuenta su última junta
 * (una reparada y reinspeccionada ya no está con defecto). El cordón queda con
 * defecto si alguna pieza lo tiene así ahora, correcto si todas las que se
 * vieron lo tienen bien, y sin junta si nadie lo ha revisado.
 */
class EstadoDeCordones
{
    /**
     * @param  list<int>  $cordones
     * @return array<int, array{estado: string, correctas: int, con_defecto: int}>
     */
    public function de(array $cordones): array
    {
        if ($cordones === []) {
            return [];
        }

        // Por bloques: un modelo trae decenas de miles de cordones y SQLite no
        // admite tantos parámetros en un solo IN.
        $ultimas = collect(array_chunk($cordones, 500))
            ->flatMap(fn (array $bloque) => Junta::query()
                ->whereIn('cordon_id', $bloque)
                ->join('qal_inspecciones', 'qal_inspecciones.id', '=', 'qal_juntas.inspeccion_id')
                ->orderBy('qal_juntas.id')
                ->get(['qal_juntas.id', 'qal_juntas.cordon_id', 'qal_juntas.resultado', 'qal_inspecciones.qr']))
            ->groupBy('cordon_id')
            ->map(fn ($juntas) => $juntas->keyBy(fn (Junta $junta): string => (string) ($junta->qr ?? $junta->id)));

        $estados = [];

        foreach ($cordones as $cordon) {
            $porPieza = $ultimas->get($cordon, collect());
            $conDefecto = $porPieza->filter(fn (Junta $junta): bool => $junta->resultado === ResultadoJunta::ConDefecto)->count();
            $correctas = $porPieza->count() - $conDefecto;

            $estados[$cordon] = [
                'estado' => $conDefecto > 0 ? 'defecto' : ($correctas > 0 ? 'correcta' : 'sin'),
                'correctas' => $correctas,
                'con_defecto' => $conDefecto,
            ];
        }

        return $estados;
    }
}
