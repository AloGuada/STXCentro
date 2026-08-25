<?php

namespace App\Services\Prod;

use App\Models\Obra;
use App\Models\Prod\Pieza;
use App\Models\Prod\Proceso;
use Illuminate\Support\Collection;

/**
 * Qué procesos paga cada obra.
 *
 * Capturar pintura en una obra que sólo suelda inventaría dinero que nadie
 * presupuestó, así que todo camino que escriba producción tiene que preguntar
 * aquí primero.
 *
 * El mapa se arma de una sola consulta y vive lo que dure el request: el import
 * de un archivo grande preguntaba una vez por renglón.
 */
class ProcesosPagadosPorObra
{
    /** @var array<int, list<int>>|null */
    private ?array $mapa = null;

    public function paga(int $obraId, int $procesoId): bool
    {
        return in_array($procesoId, $this->mapa()[$obraId] ?? [], true);
    }

    /**
     * El texto para la captura manual, donde se rechaza el lote completo si
     * alguna de sus obras no paga el proceso.
     *
     * @param  Collection<int, Pieza>  $piezas
     */
    public function error(Collection $piezas, Proceso $proceso): ?string
    {
        $obraIds = $piezas
            ->map(fn (Pieza $pieza): int => (int) $pieza->catalogo?->obra_id)
            ->filter()
            ->unique();

        foreach ($obraIds as $obraId) {
            if (! $this->paga($obraId, $proceso->id)) {
                return "La obra no paga el proceso \"{$proceso->nombre}\"; configúralo en la obra antes de capturar.";
            }
        }

        return null;
    }

    /**
     * @return array<int, list<int>>
     */
    private function mapa(): array
    {
        return $this->mapa ??= Obra::query()
            ->with('procesos:id')
            ->get(['id'])
            ->mapWithKeys(fn (Obra $obra): array => [
                (int) $obra->id => $obra->procesos->pluck('id')->map(fn ($id): int => (int) $id)->all(),
            ])
            ->all();
    }
}
