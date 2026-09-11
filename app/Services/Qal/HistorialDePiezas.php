<?php

namespace App\Services\Qal;

use App\Enums\Qal\EstatusInspeccion;
use App\Enums\Qal\FaseTransformacion;
use App\Enums\Qal\Subetapa;
use App\Models\Qal\Inspeccion;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * Las piezas como las ve el avance de producción: cada QR reducido a su
 * historia en una transformación.
 *
 * No es un registro de inspección sino el resumen de todas las de esa pieza:
 * una pieza reinspeccionada tres veces sigue siendo una pieza. La puerta de
 * cada plan es la de la aplicación anterior: en 2ª una pieza cuenta como
 * fabricada cuando se presenta a inspección de soldado, y en 3ª cuando pintura
 * la inspecciona.
 */
class HistorialDePiezas
{
    public function __construct(private readonly LectorDeProgramacion $lector) {}

    /**
     * @param  list<int>  $obras
     * @param  '2'|'3'  $fase
     * @return list<array<string, mixed>>
     */
    public function piezas(array $obras, string $fase): array
    {
        return $this->enLaPuerta($obras, $fase)
            ->with(['obra:id,no', 'inspector.usuario:id,name'])
            ->orderBy('fecha')
            ->orderBy('id')
            ->get(['id', 'obra_id', 'inspector_id', 'marca', 'qr', 'fecha', 'anio', 'semana', 'estatus', 'numero_inspeccion'])
            ->groupBy(fn (Inspeccion $inspeccion): string => $inspeccion->obra_id.'|'.$inspeccion->qr)
            ->map(fn (Collection $historia): array => $this->resumir($historia))
            ->values()
            ->all();
    }

    /**
     * Las piezas a medias, por `marca|obra_id`: en 2ª pasaron armado y todavía
     * no se presentan en soldado; en 3ª ya se liberaron en soldado y pintura no
     * las ha visto. No lo declara nadie: sale de los propios registros.
     *
     * @param  list<int>  $obras
     * @param  '2'|'3'  $fase
     * @return array<string, int>
     */
    public function enProceso(array $obras, string $fase): array
    {
        $yaEnLaPuerta = $this->enLaPuerta($obras, $fase)
            ->get(['obra_id', 'qr'])
            ->map(fn (Inspeccion $inspeccion): string => $inspeccion->obra_id.'|'.$inspeccion->qr)
            ->flip();

        return Inspeccion::query()
            ->whereIn('obra_id', $obras)
            ->whereNotNull('qr')
            ->where('fase', FaseTransformacion::Segunda->value)
            ->when(
                $fase === '2',
                fn (Builder $consulta) => $consulta->where('subetapa', Subetapa::ArmadoVestido->value),
                fn (Builder $consulta) => $consulta->where('subetapa', Subetapa::Soldado->value)->where('estatus', EstatusInspeccion::Liberado->value),
            )
            ->get(['obra_id', 'qr', 'marca'])
            ->unique(fn (Inspeccion $inspeccion): string => $inspeccion->obra_id.'|'.$inspeccion->qr)
            ->reject(fn (Inspeccion $inspeccion): bool => $yaEnLaPuerta->has($inspeccion->obra_id.'|'.$inspeccion->qr))
            ->countBy(fn (Inspeccion $inspeccion): string => $this->lector->normalizar((string) $inspeccion->marca).'|'.$inspeccion->obra_id)
            ->all();
    }

    /**
     * Las inspecciones que cuentan como la puerta del plan de esa transformación.
     *
     * @param  list<int>  $obras
     * @return Builder<Inspeccion>
     */
    private function enLaPuerta(array $obras, string $fase): Builder
    {
        return Inspeccion::query()
            ->whereIn('obra_id', $obras)
            ->whereNotNull('qr')
            ->when(
                $fase === '2',
                fn (Builder $consulta) => $consulta->where('fase', FaseTransformacion::Segunda->value)->where('subetapa', Subetapa::Soldado->value),
                fn (Builder $consulta) => $consulta->where('fase', FaseTransformacion::Tercera->value),
            );
    }

    /**
     * @param  Collection<int, Inspeccion>  $historia  de la primera a la última
     * @return array<string, mixed>
     */
    private function resumir(Collection $historia): array
    {
        $primera = $historia->first();
        $ultima = $historia->last();
        $liberada = $historia->first(fn (Inspeccion $inspeccion): bool => $inspeccion->estatus === EstatusInspeccion::Liberado);

        return [
            'marca' => $this->lector->normalizar((string) $ultima->marca),
            'obra_id' => (int) $ultima->obra_id,
            'obra' => $ultima->obra?->no,
            'qr' => (string) $ultima->qr,
            'semanaFabricada' => $this->semanaDe($primera),
            'semanaLiberada' => $liberada ? $this->semanaDe($liberada) : '',
            // Liberada en una inspección posterior a la primera: hubo retrabajo.
            'inspeccionLiberada' => $liberada?->numero_inspeccion,
            'semanasRechazada' => $historia
                ->filter(fn (Inspeccion $inspeccion): bool => $inspeccion->estatus === EstatusInspeccion::Rechazado)
                ->map(fn (Inspeccion $inspeccion): string => $this->semanaDe($inspeccion))
                ->unique()
                ->values()
                ->all(),
            'estatus' => ucfirst($ultima->estatus->value),
            'fechaUltima' => $ultima->fecha->toDateString(),
            'inspecciones' => $historia->count(),
            'inspector' => $ultima->inspector?->usuario?->name,
        ];
    }

    private function semanaDe(Inspeccion $inspeccion): string
    {
        return sprintf('%04d-S%02d', $inspeccion->anio, $inspeccion->semana);
    }
}
